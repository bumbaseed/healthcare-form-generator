<?php
/**
 * Read-only view of a single completed submission for the patient currently in the session. Looks up the metadata, the form definition, and the row of field values, then hands them to the view.
 */

require_once __DIR__ . '/code/includes/db_connection.php';
require_once __DIR__ . '/code/includes/functions.php';
require_once __DIR__ . '/code/includes/auth.php';
require_once __DIR__ . '/code/models/FormSubmissions.php';
require_once __DIR__ . '/code/models/FormDefinition.php';

requireAuth(true);

$patientData = getSessionPatientData();
$patientId = $patientData['patient_id'];
$patientNumber = $patientData['patient_number'];

$submissionId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($submissionId <= 0) {
    setFlash('error', 'Invalid submission.');
    header('Location: /my-cases.php');
    exit;
}

$pageTitle = 'View Submission';
$submission = null;
$form = null;
$data = null;

try {
    $subModel = new FormSubmissions();
    $formModel = new FormDefinition();

    $submission = $subModel->getSubmissionForPatient($submissionId, $patientId);

    if (!$submission) {
        setFlash('error', 'Submission not found for this patient.');
        header('Location: /my-cases.php');
        exit;
    }

    $form = $formModel->getFormWithFields($submission['form_id']);
    $data = $subModel->getSubmissionData($submission['table_name'], $submissionId);
} catch (Exception $e) {
    setFlash('error', 'Could not load submission.');
    header('Location: /my-cases.php');
    exit;
}

include __DIR__ . '/code/views/layouts/header.php';
include __DIR__ . '/code/views/submissions/view.php';
include __DIR__ . '/code/views/layouts/footer.php';
