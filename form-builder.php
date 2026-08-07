<?php
/**
 * Form builder. Admin tool for creating new form definitions with custom fields. Each finalized form automatically gets its own PostgreSQL table.
 */

require_once __DIR__ . '/code/includes/db_connection.php';
require_once __DIR__ . '/code/includes/functions.php';
require_once __DIR__ . '/code/includes/auth.php';
require_once __DIR__ . '/code/models/FormDefinition.php';

// Admin only, staff cannot create form templates
requireAdmin();

$pageTitle = 'Form Builder';
$errors = [];

if (isPost()) {
    requireCsrfToken();

    // Collect top-level form data 
    $formName = trim(post('form_name'));
    $formDescription = trim(post('form_description'));

    // fields[] is a nested array built by JavaScript in the view
    $fields = $_POST['fields'] ?? [];

    // Server-side validation 
    if (empty($formName)) {
        $errors[] = 'Form name is required.';
    }

    // Validate each field individually (skip section_header / section_end rows)
    $realFieldCount = 0;
    foreach ($fields as $i => $field) {
        $type = $field['type'] ?? 'text';
        if ($type === 'section_header' || $type === 'section_end')
            continue;

        $realFieldCount++;
        $label = trim($field['label'] ?? '');
        $dbName = trim($field['db_name'] ?? '');

        if (empty($label)) {
            $errors[] = "Field {$realFieldCount}: label is required.";
        }
        if (empty($dbName)) {
            $errors[] = "Field {$realFieldCount}: database name is required.";
        }
        // Database names: letters, numbers, underscores only; cannot start with a number
        if (!empty($dbName) && !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $dbName)) {
            $errors[] = "Field {$realFieldCount}: database name '{$dbName}' is invalid. Use letters, numbers and underscores only.";
        }
    }

    if ($realFieldCount === 0) {
        $errors[] = 'At least one field is required.';
    }

    // Save if no errors
    if (empty($errors)) {
        try {
            $formDef = new FormDefinition();

            // create the form_definitions row
            $formId = $formDef->create($formName, $formDescription, getUserId());

            // Walk the flat ordered field list, tracking the current section heading. section_header rows set the active section, section_end rows clear it. Only real fields are written to form_fields.
            $currentSection = null;
            $fieldOrder = 0;

            foreach ($fields as $field) {
                $fieldType = $field['type'] ?? 'text';

                if ($fieldType === 'section_header') {
                    $currentSection = trim($field['label'] ?? '');
                    continue;
                }

                if ($fieldType === 'section_end') {
                    $currentSection = null;
                    continue;
                }

                $fieldOrder++;

                // Map the field type to the correct PostgreSQL data type
                $dataType = match ($fieldType) {
                    'number' => 'INTEGER',
                    'date' => 'DATE',
                    'datetime' => 'TIMESTAMP',
                    'textarea' => 'TEXT',
                    default => 'VARCHAR'
                };

                // Encode section label into field_options JSON (_section key). This is presentation-only and never becomes a DB column.
                $fieldOptions = null;
                if ($currentSection !== null && $currentSection !== '') {
                    $fieldOptions = json_encode(['_section' => $currentSection]);
                }

                $formDef->addField($formId, [
                    'field_label' => trim($field['label']),
                    'field_name' => trim($field['db_name']),
                    'field_type' => $fieldType,
                    'data_type' => $dataType,
                    'is_required' => isset($field['required']),
                    'field_order' => $fieldOrder,
                    'field_options' => $fieldOptions,
                ]);
            }

            // finalizeForm() calls DynamicTable to CREATE the actual PostgreSQL table
            $formDef->finalizeForm($formId);

            auditLog(
                'form_create',
                'form_definitions',
                $formId,
                "Created form \"{$formName}\" with {$fieldOrder} fields"
            );

            setFlash('success', "Form '{$formName}' created.");
            redirect('/form-list.php');

        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/code/views/layouts/header.php';
include __DIR__ . '/code/views/forms/builder.php';
include __DIR__ . '/code/views/layouts/footer.php';
