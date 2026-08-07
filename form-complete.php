<?php
/**
 * Generic form completion page. Lets staff pick an active form and fill it in for the patient currently selected in the session.
 *
 * GET  ?form_id=X render the form
 * POST ?form_id=X validate and persist the submission
 */

require_once __DIR__ . '/code/includes/db_connection.php';
require_once __DIR__ . '/code/includes/functions.php';
require_once __DIR__ . '/code/includes/auth.php';
require_once __DIR__ . '/code/models/FormDefinition.php';
require_once __DIR__ . '/code/models/FormSubmissions.php';
require_once __DIR__ . '/code/includes/form_renderer.php';

requireAuth(true);

$patientData = getSessionPatientData();
$patientId = $patientData['patient_id'];
$patientNumber = $patientData['patient_number'];

// Submissions must be linked to a real patient row. If the session has a patient_number that doesn't resolve to one, send the user back to MRN entry rather than persisting an orphan record.
if (!$patientId) {
    setFlash('error', 'Patient "' . $patientNumber . '" was not found in the database. Please select a valid patient before completing a form.');
    redirect('/patient-entry.php');
}

$pageTitle = 'Complete Form';
$errors = [];
$formDef = new FormDefinition();

$formId = (int) get('form_id', 0);

if (!$formId) {
    $activeForms = $formDef->getAll(true);
    include __DIR__ . '/code/views/layouts/header.php';
    include __DIR__ . '/code/views/forms/select.php';
    include __DIR__ . '/code/views/layouts/footer.php';
    exit;
}

$form = $formDef->getFormWithFields($formId);

if (!$form || !$form['is_active']) {
    setFlash('error', 'Form not found or is not active.');
    redirect('/form-complete.php');
}

$pageTitle = 'Complete: ' . $form['form_name'];

$submissions = new FormSubmissions();

// Two submit buttons share name="_action". A "draft" action saves the current values without validation and re-renders the page with a notice. A "complete" action runs validation, finalizes the submission, and redirects to the dashboard on success.
if (isPost()) {
    requireCsrfToken();
    $action = post('_action', 'complete');

    $fieldNames = array_column($form['fields'], 'field_name');

    $data = [];
    foreach ($form['fields'] as $field) {
        $name = $field['field_name'];
        $value = post($name);

        // Multi-select checkbox arrays are stored as JSON.
        if ($field['field_type'] === 'checkbox' && is_array($value)) {
            $value = json_encode($value);
        }

        // A missing boolean checkbox means "off"; coerce to '0' rather than null so the column always has a defined value.
        if ($field['field_type'] === 'boolean') {
            $value = $value ? '1' : '0';
        }

        $data[$name] = $value;
    }

    if ($action === 'draft') {
        try {
            $draftId = $submissions->saveDraft(
                $formId,
                $patientId,
                $form['table_name'],
                $fieldNames,
                $data,
                getUserId()
            );

            auditLog(
                'form_draft_save',
                'form_submissions',
                $draftId,
                "Draft saved for \"{$form['form_name']}\" / patient {$patientNumber}"
            );

            setFlash('success', 'Draft saved.');
            redirect($_SERVER['REQUEST_URI']);
        } catch (Exception $e) {
            $errors['_draft'] = 'Draft save failed: ' . $e->getMessage();
        }
    } else {
        // Server-side required-field validation. Errors are keyed by field_name so the renderer can attach aria-invalid + a per-field .form-error message linked via aria-describedby.
        foreach ($form['fields'] as $field) {
            if ($field['is_required']) {
                $val = $data[$field['field_name']] ?? '';
                if ($val === '' || $val === null) {
                    $errors[$field['field_name']] = $field['field_label'] . ' is required.';
                }
            }
        }

        // Electronic-signature validation. Both the typed name and the attestation checkbox are required to complete a submission. They are NOT validated on draft saves because a draft is by definition not yet finalized.
        $signatureName = trim((string) post('signature_name', ''));
        $signatureConfirmed = !empty($_POST['signature_confirmed']);

        if ($signatureName === '') {
            $errors['signature_name'] = 'Please type your full name to sign this submission.';
        }
        if (!$signatureConfirmed) {
            $errors['signature_confirmed'] = 'Please tick the box to confirm your electronic signature.';
        }

        // Save if valid
        if (empty($errors)) {
            try {
                $submissionId = $submissions->complete(
                    $formId,
                    $patientId,
                    $form['table_name'],
                    $fieldNames,
                    $data,
                    getUserId(),
                    $signatureName
                );

                auditLog(
                    'form_submit',
                    'form_submissions',
                    $submissionId,
                    "Submitted \"{$form['form_name']}\" for patient {$patientNumber}"
                );

                setFlash('success', "Submitted as #{$submissionId}.");
                redirect('/index.php');
            } catch (Exception $e) {
                $errors['_submit'] = 'Submission failed: ' . $e->getMessage();
            }
        }
    }
}

// Draft rehydration on GET (or after a failed-validation POST), pull the latest draft for this (form, patient) so the renderer pre-fills from it. POST values always win over draft values.
$existingDraft = $submissions->getDraftForPatient(
    (int) $formId,
    (int) $patientId,
    $form['table_name']
);
$draftValues = $existingDraft['data'] ?? [];
$draftSavedAt = $existingDraft ? date('d M Y H:i', strtotime($existingDraft['saved_at'])) : null;

// POST overrides draft when re-rendering after a validation failure.
$prefillData = array_merge($draftValues, $_POST ?: []);

// Render form
$renderer = new FormRenderer();

include __DIR__ . '/code/views/layouts/header.php';
include __DIR__ . '/code/views/forms/complete.php';
include __DIR__ . '/code/views/layouts/footer.php';