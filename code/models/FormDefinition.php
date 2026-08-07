<?php
/**
 * Form definitions and their fields.
 */

require_once dirname(__DIR__) . '/includes/db_connection.php';
require_once dirname(__DIR__) . '/includes/GitService.php';
require_once __DIR__ . '/DynamicTable.php';

class FormDefinition
{
    private $db;
    private $dynamicTable;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->dynamicTable = new DynamicTable();
    }

    /**
     * Insert a draft form_definitions row. The table_name is built from the form name and a version suffix; if a same-named form already exists, the version is bumped until a free name is found. Returns the new form_id.
     */
    public function create($formName, $formDescription = null, ?int $createdBy = null)
    {
        $sql = "INSERT INTO form_definitions (form_name, form_description, table_name, is_active, created_by)
                VALUES (:formName, :formDescription, :tableName, false, :createdBy)
                RETURNING form_id";

        try {
            // Human-readable schema-qualified table name, for example form_data.medication_form_v1.
            $version = 1;
            $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($formName)));
            $slug = trim($slug, '_');
            $tableName = 'form_data.' . $slug . '_v' . $version;

            while ($this->dynamicTable->tableExists($slug . '_v' . $version, 'form_data')) {
                $version++;
                $tableName = 'form_data.' . $slug . '_v' . $version;
            }

            $result = $this->db->fetchOne($sql, [
                'formName' => $formName,
                'formDescription' => $formDescription,
                'tableName' => $tableName,
                'createdBy' => $createdBy,
            ]);

            $formId = $result['form_id'];

            if ($version > 1) {
                $this->db->execute(
                    "UPDATE form_definitions SET form_version = :version WHERE form_id = :formId",
                    ['version' => $version, 'formId' => $formId]
                );
            }

            return $formId;

        } catch (Exception $e) {
            throw new Exception("Failed to create form definition: " . $e->getMessage());
        }
    }

    /**
     * Append a field to a form. Field order is set to the next free slot, derived from the current row count for the form. Returns the new field_id.
     */
    public function addField($formId, $fieldData)
    {
        $countResult = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM form_fields WHERE form_id = :formId",
            ['formId' => $formId]
        );
        $fieldOrder = ($countResult['count'] ?? 0) + 1;

        $sql = "INSERT INTO form_fields (
                    form_id, field_order, field_name, field_label, field_type,
                    data_type, field_options, is_required, default_value,
                    validation_rules, max_length, help_text
                ) VALUES (
                    :formId, :fieldOrder, :fieldName, :fieldLabel, :fieldType,
                    :dataType, :fieldOptions, :isRequired, :defaultValue,
                    :validationRules, :maxLength, :helpText
                ) RETURNING field_id";

        try {
            $result = $this->db->fetchOne($sql, [
                'formId' => $formId,
                'fieldOrder' => $fieldOrder,
                'fieldName' => $fieldData['field_name'],
                'fieldLabel' => $fieldData['field_label'],
                'fieldType' => $fieldData['field_type'],
                'dataType' => $fieldData['data_type'],
                'fieldOptions' => $fieldData['field_options'] ?? null,
                'isRequired' => $fieldData['is_required'] ?? false,
                'defaultValue' => $fieldData['default_value'] ?? null,
                'validationRules' => $fieldData['validation_rules'] ?? null,
                'maxLength' => $fieldData['max_length'] ?? null,
                'helpText' => $fieldData['help_text'] ?? null
            ]);

            $this->updateFieldCount($formId);

            return $result['field_id'];

        } catch (Exception $e) {
            throw new Exception("Failed to add field: " . $e->getMessage());
        }
    }

    /**
     * Finalize a draft form. Creates the dynamic table, writes the generated PHP file under forms/, optionally pushes a git branch, and flips is_active to true. The git step runs before the active flag is set so a git failure leaves the form unfinalized and re-runnable.
     */
    public function finalizeForm($formId)
    {
        try {
            $form = $this->getById($formId);
            if (!$form) {
                throw new Exception("Form not found");
            }

            if ($form['is_active']) {
                throw new Exception("Form is already finalized");
            }

            $fields = $this->getFormFields($formId);

            if (empty($fields)) {
                throw new Exception("Cannot finalize form with no fields");
            }

            // Drop any orphaned table left over from a previous failed attempt before recreating it.
            $tableName = $form['table_name'];
            $parts = explode('.', $tableName);
            $rawTable = count($parts) === 2 ? $parts[1] : $parts[0];
            $schema = count($parts) === 2 ? $parts[0] : 'form_data';

            if ($this->dynamicTable->tableExists($rawTable, $schema)) {
                $this->dynamicTable->dropTable($tableName);
            }
            $this->dynamicTable->createFormTable($formId, $fields, $tableName);

            // Write the editable PHP definition under forms/ and capture its repo-relative path for git.
            $fileRelPath = $this->exportToFile($form, $fields);

            // Optional git integration. Both the table drop and the file write above are idempotent so a retry after a git failure is safe.
            $this->createFormBranch($form, $fields, $fileRelPath);

            $this->db->execute(
                "UPDATE form_definitions SET is_active = true, updated_at = CURRENT_TIMESTAMP WHERE form_id = :formId",
                ['formId' => $formId]
            );

            return true;

        } catch (Exception $e) {
            throw new Exception("Failed to finalize form: " . $e->getMessage());
        }
    }

    /**
     * Write a self-contained PHP file for the form into the forms/ directory. The file is a readable, editable array plus a complete request handler. Returns the repo-relative path of the written file, for example "forms/test_v1.php".
     */
    private function exportToFile(array $form, array $fields): string
    {
        $formsDir = dirname(__DIR__, 2) . '/forms';

        $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($form['form_name'])));
        $slug = trim($slug, '_');
        $version = (int) ($form['form_version'] ?? 1);
        $filename = "{$slug}_v{$version}.php";

        $formArray = [
            'form_id' => (int) $form['form_id'],
            'form_name' => $form['form_name'],
            'form_description' => $form['form_description'] ?? '',
            'form_version' => $version,
            'table_name' => $form['table_name'],
            'fields' => [],
        ];

        foreach ($fields as $f) {
            $fieldEntry = [
                'field_label' => $f['field_label'],
                'field_name' => $f['field_name'],
                'field_type' => $f['field_type'],
                'data_type' => $f['data_type'],
                'is_required' => (bool) $f['is_required'],
            ];
            if (!empty($f['field_options'])) {
                $fieldEntry['field_options'] = $f['field_options'];
            }
            if (!empty($f['default_value'])) {
                $fieldEntry['default_value'] = $f['default_value'];
            }
            if (!empty($f['validation_rules'])) {
                $fieldEntry['validation_rules'] = $f['validation_rules'];
            }
            if (!empty($f['max_length'])) {
                $fieldEntry['max_length'] = (int) $f['max_length'];
            }
            if (!empty($f['help_text'])) {
                $fieldEntry['help_text'] = $f['help_text'];
            }
            $formArray['fields'][] = $fieldEntry;
        }

        $formArrayCode = $this->phpArrayLiteral($formArray, 0);

        $fieldHtmlBlocks = [];
        $currentSection = null;

        foreach ($fields as $f) {
            $name = $f['field_name'];
            $label = htmlspecialchars($f['field_label'], ENT_QUOTES);
            $type = $f['field_type'];
            $required = (bool) $f['is_required'];
            $section = null;

            if (!empty($f['field_options'])) {
                $opts = json_decode($f['field_options'], true);
                if (is_array($opts)) {
                    $section = $opts['_section'] ?? null;
                }
            }

            // Open or close the section wrapper as the section changes between adjacent fields.
            if ($section !== $currentSection) {
                if ($currentSection !== null) {
                    $fieldHtmlBlocks[] = '</div></div>';
                }
                if ($section !== null) {
                    $sectionTitle = htmlspecialchars($section, ENT_QUOTES);
                    $fieldHtmlBlocks[] = <<<HTML
                        <div class="form-section">
                            <div class="form-section-heading">{$sectionTitle}</div>
                            <div class="form-field-grid form-field-grid--sectioned">
                        HTML;
                }
                $currentSection = $section;
            }

            $req = $required ? ' required' : '';
            $reqStar = $required ? ' <span class="required">*</span>' : '';

            // Every input prefills from \$prefill() so the same template handles both a fresh form and a returning draft.
            $inputHtml = match ($type) {
                'textarea' => "<textarea id=\"{$name}\" name=\"{$name}\" class=\"form-control\" rows=\"4\"{$req}><?= escape(\$prefill('{$name}')) ?></textarea>",

                'number' => "<input type=\"number\" id=\"{$name}\" name=\"{$name}\" class=\"form-control\" value=\"<?= escape(\$prefill('{$name}')) ?>\"{$req}>",

                'date' => "<input type=\"date\" id=\"{$name}\" name=\"{$name}\" class=\"form-control\" value=\"<?= escape(\$prefill('{$name}')) ?>\"{$req}>",

                'datetime' => "<input type=\"datetime-local\" id=\"{$name}\" name=\"{$name}\" class=\"form-control\" value=\"<?= escape(\$prefill('{$name}')) ?>\"{$req}>",

                'email' => "<input type=\"email\" id=\"{$name}\" name=\"{$name}\" class=\"form-control\" value=\"<?= escape(\$prefill('{$name}')) ?>\"{$req}>",

                'phone' => "<input type=\"tel\" id=\"{$name}\" name=\"{$name}\" class=\"form-control\" value=\"<?= escape(\$prefill('{$name}')) ?>\"{$req}>",

                'boolean' => "<input type=\"checkbox\" id=\"{$name}\" name=\"{$name}\" value=\"1\" <?= \$prefill('{$name}') ? 'checked' : '' ?>>",

                default => "<input type=\"text\" id=\"{$name}\" name=\"{$name}\" class=\"form-control\" value=\"<?= escape(\$prefill('{$name}')) ?>\"{$req}>",
            };

            $fieldHtmlBlocks[] = <<<HTML
                <div class="form-group">
                    <label for="{$name}">{$label}{$reqStar}</label>
                    {$inputHtml}
                </div>
                HTML;
        }

        if ($currentSection !== null) {
            $fieldHtmlBlocks[] = '</div></div>';
        }

        $fieldsHtml = implode("\n", $fieldHtmlBlocks);

        // Sanitize form_name, table_name, and form_description for each context they're substituted into below. Without this, a form name containing */, ", $, or \ would break the generated PHP.
        $formName = htmlspecialchars($form['form_name'], ENT_QUOTES); // HTML context, <h1>
        $formDesc = htmlspecialchars($form['form_description'] ?? '', ENT_QUOTES);
        $formNameComment = str_replace('*/', '* /', $form['form_name']); // PHP block comment
        $tableNameComment = str_replace('*/', '* /', $form['table_name']);
        $formNamePhpStr = addcslashes($form['form_name'], "\$\"\\"); // PHP double-quoted string literal
        $formId = (int) $form['form_id'];
        $fieldCount = count($fields);

        $content = <<<PHPTPL
<?php
/**
 * Form: {$formNameComment}
 * Version: {$version}
 * Table: {$tableNameComment}
 * Generated: {$this->now()}
 *
 * Generated by the form builder as a self-contained page.
 *  - the \$form array to rename fields, change labels, or tweak types
 *  - the VALIDATION section for additional checks
 *  - the HTML below for layout, styling, or conditional logic
 */

require_once __DIR__ . '/../code/includes/db_connection.php';
require_once __DIR__ . '/../code/includes/functions.php';
require_once __DIR__ . '/../code/includes/auth.php';
require_once __DIR__ . '/../code/models/FormSubmissions.php';

requireAuth(true);

\$patientData = getSessionPatientData();
\$patientId = \$patientData['patient_id'];
\$patientNumber = \$patientData['patient_number'];

if (!\$patientId) {
    setFlash('error', 'Please select a valid patient before completing a form.');
    redirect('/patient-entry.php');
}


// -- Form Definition --
// Edit field labels, types, required status, or add/remove fields here. Changes to field_name or data_type may require a matching database migration.

\$form = {$formArrayCode};

\$pageTitle = 'Complete: ' . \$form['form_name'];
\$errors = [];

\$submissions = new FormSubmissions();


// POST handler. Two submit buttons share name="_action". A "draft" action saves the current values without validation. A "complete" action runs validation, persists the submission, and redirects to the dashboard on success.
if (isPost()) {
    requireCsrfToken();
    \$action = post('_action', 'complete');

    \$fieldNames = array_column(\$form['fields'], 'field_name');
    \$data = [];

    foreach (\$form['fields'] as \$field) {
        \$name = \$field['field_name'];
        \$value = post(\$name);

        if (\$field['field_type'] === 'checkbox' && is_array(\$value)) {
            \$value = json_encode(\$value);
        }
        if (\$field['field_type'] === 'boolean') {
            \$value = \$value ? '1' : '0';
        }

        \$data[\$name] = \$value;
    }

    if (\$action === 'draft') {
        try {
            \$draftId = \$submissions->saveDraft(
                \$form['form_id'],
                \$patientId,
                \$form['table_name'],
                \$fieldNames,
                \$data,
                getUserId()
            );

            auditLog('form_draft_save', 'form_submissions', \$draftId,
                "Draft saved for \\"{$formNamePhpStr}\\" / patient " . \$patientNumber);

            setFlash('success', 'Draft saved.');
            redirect(\$_SERVER['REQUEST_URI']);
        } catch (Exception \$e) {
            \$errors['_draft'] = 'Draft save failed: ' . \$e->getMessage();
        }
    } else {
        // -- VALIDATION --
        // Required-field checks for this form. Errors are keyed by field_name so the form-signature partial can match on key and mark its inputs as invalid via aria attributes.

        foreach (\$form['fields'] as \$field) {
            if (\$field['is_required']) {
                \$val = \$data[\$field['field_name']] ?? '';
                if (\$val === '' || \$val === null) {
                    \$errors[\$field['field_name']] = \$field['field_label'] . ' is required.';
                }
            }
        }

        // -- Electronic signature --
        // Both the typed name and the attestation checkbox are required for completion. Drafts (handled above) never reach this branch so they skip the check by design.
        \$signatureName = trim((string) post('signature_name', ''));
        \$signatureConfirmed = !empty(\$_POST['signature_confirmed']);

        if (\$signatureName === '') {
            \$errors['signature_name'] = 'Please type your full name to sign this submission.';
        }
        if (!\$signatureConfirmed) {
            \$errors['signature_confirmed'] = 'Please tick the box to confirm your electronic signature.';
        }

        // -- Custom validation --
        // Add developer-defined rules here. Examples:
        //
        // if (!empty(\$data['email']) && !filter_var(\$data['email'], FILTER_VALIDATE_EMAIL)) {
        //     \$errors['email'] = 'Please enter a valid email address.';
        // }
        //
        // if ((\$data['systolic'] ?? 0) > 300) {
        //     \$errors['systolic'] = 'Systolic reading seems unusually high - please verify.';
        // }

        // -- Save --
        if (empty(\$errors)) {
            try {
                \$submissionId = \$submissions->complete(
                    \$form['form_id'],
                    \$patientId,
                    \$form['table_name'],
                    \$fieldNames,
                    \$data,
                    getUserId(),
                    \$signatureName
                );

                auditLog('form_submit', 'form_submissions', \$submissionId,
                    "Submitted \\"{$formNamePhpStr}\\" for patient " . \$patientNumber);

                setFlash('success', "Submitted as #{\$submissionId}.");
                redirect('/index.php');
            } catch (Exception \$e) {
                \$errors['_submit'] = 'Submission failed: ' . \$e->getMessage();
            }
        }
    }
}


// -- Draft rehydration --
// On GET (or on a validation-failed POST), prefill inputs from any existing draft for this (form, patient). POST values always win over draft values.
\$existingDraft = \$submissions->getDraftForPatient(
    (int) \$form['form_id'],
    (int) \$patientId,
    \$form['table_name']
);
\$draftValues = \$existingDraft['data'] ?? [];
\$draftSavedAt = \$existingDraft ? date('d M Y H:i', strtotime(\$existingDraft['saved_at'])) : null;

\$prefill = function (string \$name) use (\$draftValues) {
    return post(\$name, \$draftValues[\$name] ?? '');
};


// -- HTML --
// All form CSS classes are defined in /css/main.css. Add form-specific overrides in a <style> block here if needed.
include __DIR__ . '/../code/views/layouts/header.php';
?>

<div class="form-page">

    <div class="form-back-link-wrap">
        <a href="/form-complete.php" class="form-back-link">
            &larr; Back to form list
        </a>
    </div>

    <!--  Patient Banner  -->
    <div class="patient-banner">
        <div class="patient-banner-left">
            <div class="patient-avatar">
                <?= strtoupper(substr(\$patientData['patient_name'] ?? 'P', 0, 1)) ?>
            </div>
            <div>
                <p class="patient-banner-name"><?= escape(\$patientData['patient_name'] ?? 'Unknown Patient') ?></p>
                <p class="patient-banner-sub">Selected patient</p>
            </div>
        </div>
        <div class="patient-banner-right">
            <div class="patient-chip">
                <span class="patient-chip-label">MRN</span>
                <span class="patient-chip-value"><?= escape(\$patientNumber) ?></span>
            </div>
            <div class="patient-chip">
                <span class="patient-chip-label">Staff</span>
                <span class="patient-chip-value"><?= escape(\$patientData['username'] ?? '') ?></span>
            </div>
            <div class="patient-chip">
                <span class="patient-chip-label">Date</span>
                <span class="patient-chip-value"><?= date('d M Y') ?></span>
            </div>
            <div class="patient-chip">
                <span class="patient-chip-label">Time</span>
                <span class="patient-chip-value"><?= date('H:i') ?></span>
            </div>
        </div>
    </div>

    <!--  Form Header  -->
    <div class="form-meta-header">
        <div class="form-meta-title">
            <h1>{$formName}</h1>
            <p>{$formDesc}</p>
        </div>
        <div class="form-meta-details">
            <span><span class="patient-chip-label">Form ID</span> <strong>#{$formId}</strong></span>
            <span><span class="patient-chip-label">Fields</span> <strong>{$fieldCount}</strong></span>
            <span><span class="patient-chip-label">Version</span> <strong>{$version}</strong></span>
        </div>
    </div>

    <!--  Form Body  -->
    <form method="POST" action="">
        <?= csrfField() ?>
        <div class="form-body">

            <?php if (!empty(\$errors)): ?>
                <div class="error-list">
                    <strong>Please correct the following before submitting:</strong>
                    <ul>
                        <?php foreach (\$errors as \$error): ?>
                            <li><?= escape(\$error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!--  Form Fields  -->
            <!-- Edit the HTML below to change layout, add conditional logic, -->
            <!-- insert custom elements, or override field rendering. -->

{$fieldsHtml}

        </div>

        <!--  Electronic Signature -->
        <!-- Sits in the same position on every generated form: below the last -->
        <!-- field, above the action bar. Edit the partial to change wording. -->
        <?php include __DIR__ . '/../code/views/layouts/form-signature.php'; ?>

        <!--  Form Footer  -->
        <!-- Standard action bar: Cancel / Print / Save Draft / Save & Complete. -->
        <!-- Edit the partial at code/views/layouts/form-actions.php to adjust   -->
        <!-- buttons across every generated form in one place. -->
        <?php include __DIR__ . '/../code/views/layouts/form-actions.php'; ?>
    </form>

</div>

<?php include __DIR__ . '/../code/views/layouts/footer.php'; ?>
PHPTPL;

        file_put_contents($formsDir . '/' . $filename, $content);

        return 'forms/' . $filename;
    }

    /**
     * Create a git branch off main that contains the newly-generated form file. 
     */
    private function createFormBranch(array $form, array $fields, string $fileRelPath): void
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';

        if (empty($config['git_integration_enabled'])) {
            return;
        }

        $repoRoot = dirname(__DIR__, 2);
        $git = new GitService(
            $repoRoot,
            $config['git_binary'] ?? 'git',
            $config['git_author_name'] ?? 'Form Builder',
            $config['git_author_email'] ?? 'forms@healthcare-portal.local'
        );

        if (!$git->isRepository()) {
            error_log("GitService: {$repoRoot} is not a git repository; skipping branch creation");
            return;
        }

        // forms/<slug>_v<n> matches the generated file's slug so the branch name is easy to find from the form name.
        $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($form['form_name'])));
        $slug = trim($slug, '_');
        $version = (int) ($form['form_version'] ?? 1);
        $prefix = $config['git_branch_prefix'] ?? 'forms/';
        $baseBranch = $config['git_base_branch'] ?? 'main';
        $branchName = rtrim($prefix, '/') . '/' . $slug . '_v' . $version;

        $message = "Add form: {$form['form_name']} v{$version}\n\n"
            . "Form ID: {$form['form_id']}\n"
            . "Table:   {$form['table_name']}\n"
            . "Fields:  " . count($fields) . "\n";

        try {
            $sha = $git->createBranchWithFile($branchName, $fileRelPath, $message, $baseBranch);
            error_log("GitService: created branch {$branchName} at {$sha}");
        } catch (GitBranchExistsException $e) {
            // The branch is already there from a previous finalize, leave it alone and treat the retry as a success.
            error_log("GitService: {$e->getMessage()} (treating as success on retry)");
        }
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Render a PHP value as a PHP source-code literal using short array syntax. Used by the form generator to embed the form definition as a normal-looking PHP array in the generated file.
     */
    private function phpArrayLiteral($value, int $depth): string
    {
        if (is_array($value)) {
            if (empty($value)) {
                return '[]';
            }
            $isList = array_keys($value) === range(0, count($value) - 1);
            $indent = str_repeat('    ', $depth);
            $childIndent = str_repeat('    ', $depth + 1);
            $lines = [];
            foreach ($value as $k => $v) {
                $prefix = $isList ? '' : var_export($k, true) . ' => ';
                $lines[] = $childIndent . $prefix . $this->phpArrayLiteral($v, $depth + 1);
            }
            return "[\n" . implode(",\n", $lines) . ",\n" . $indent . ']';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return var_export((string) $value, true);
    }

    public function getById($formId)
    {
        $sql = "SELECT * FROM form_definitions WHERE form_id = :formId";
        return $this->db->fetchOne($sql, ['formId' => $formId]);
    }

    public function getFormWithFields($formId)
    {
        $form = $this->getById($formId);
        if (!$form) {
            return null;
        }

        $form['fields'] = $this->getFormFields($formId);
        return $form;
    }

    public function getFormFields($formId)
    {
        $sql = "SELECT * FROM form_fields WHERE form_id = :formId ORDER BY field_order ASC";
        return $this->db->fetchAll($sql, ['formId' => $formId]);
    }

    public function getAll($activeOnly = null)
    {
        $sql = "SELECT
                    fd.*,
                    (SELECT COUNT(*) FROM form_submissions WHERE form_id = fd.form_id AND is_deleted = false) as submission_count
                FROM form_definitions fd";

        if ($activeOnly !== null) {
            $sql .= " WHERE is_active = :isActive";
        }

        $sql .= " ORDER BY created_at DESC";

        $params = $activeOnly !== null ? ['isActive' => $activeOnly] : [];
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Recompute and store field_count on the form_definitions row from the live form_fields rows. Logs and swallows any failure since this is a denormalised cache, not a source of truth.
     */
    private function updateFieldCount($formId)
    {
        $sql = "UPDATE form_definitions
                SET field_count = (SELECT COUNT(*) FROM form_fields WHERE form_id = :formId)
                WHERE form_id = :formId2";

        try {
            $this->db->execute($sql, ['formId' => $formId, 'formId2' => $formId]);
            return true;
        } catch (Exception $e) {
            error_log("Failed to update field count: " . $e->getMessage());
            return false;
        }
    }
}
