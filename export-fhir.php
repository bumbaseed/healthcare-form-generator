<?php
/**
 * FHIR QuestionnaireResponse export for a single submission.
 *
 *   /export-fhir.php?id=123 - inline HTML preview
 *   /export-fhir.php?id=123&download=1 - application/fhir+json file download
 *
 * Auth and ownership checks reuse the same models the submission view uses, so a logged-in user can only export submissions that belong to the patient they currently have selected.
 */

require_once __DIR__ . '/code/includes/db_connection.php';
require_once __DIR__ . '/code/includes/functions.php';
require_once __DIR__ . '/code/includes/auth.php';
require_once __DIR__ . '/code/models/FormSubmissions.php';
require_once __DIR__ . '/code/models/FormDefinition.php';
require_once __DIR__ . '/code/models/FhirExporter.php';

requireAuth(true);

$patientData = getSessionPatientData();
$patientId = $patientData['patient_id'];
$patientNumber = $patientData['patient_number'];

$submissionId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$download = !empty($_GET['download']);

if ($submissionId <= 0) {
    setFlash('error', 'Invalid submission.');
    redirect('/my-cases.php');
}

try {
    $subModel = new FormSubmissions();
    $formModel = new FormDefinition();

    $submission = $subModel->getSubmissionForPatient($submissionId, $patientId);
    if (!$submission) {
        setFlash('error', 'Submission not found for this patient.');
        redirect('/my-cases.php');
    }

    $form = $formModel->getFormWithFields($submission['form_id']);
    $data = $subModel->getSubmissionData($submission['table_name'], $submissionId);

    $db = Database::getInstance();
    $patient = $db->fetchOne(
        "SELECT patient_id, patient_identifier, first_name, last_name
           FROM patients
          WHERE patient_id = :patientId",
        ['patientId' => $patientId]
    ) ?: [
        'patient_id' => $patientId,
        'patient_identifier' => $patientNumber,
        'first_name' => '',
        'last_name' => '',
    ];

    $exporter = new FhirExporter();
    $resource = $exporter->buildQuestionnaireResponse($submission, $form, $patient, $data);
    $json = json_encode($resource, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    auditLog(
        'form_submission_fhir_export',
        'form_submissions',
        $submissionId,
        "Exported submission {$submissionId} as FHIR QuestionnaireResponse"
    );
} catch (Exception $e) {
    error_log('FHIR export failed: ' . $e->getMessage());
    setFlash('error', 'Could not generate FHIR export.');
    redirect('/submission-view.php?id=' . $submissionId);
}

if ($download) {
    $filename = 'questionnaire-response-' . $submissionId . '.json';
    header('Content-Type: application/fhir+json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($json));
    echo $json;
    exit;
}

$pageTitle = 'FHIR Export';
include __DIR__ . '/code/views/layouts/header.php';
?>
<div class="form-page">
    <div class="patient-banner">
        <div class="patient-chip">
            Patient: <strong><?= escape($patientNumber) ?></strong>
        </div>
        <a href="/submission-view.php?id=<?= (int) $submissionId ?>" class="btn btn-sm btn-outline">
            <?= icon('arrow-left') ?> Back to submission
        </a>
    </div>

    <div class="card form-body">
        <div class="form-meta-header">
            <h1>FHIR QuestionnaireResponse</h1>
            <p class="fhir-export-desc">
                Structural FHIR R4 export of
                <strong><?= escape($submission['form_name']) ?></strong>
                (submission #<?= (int) $submissionId ?>).
            </p>
            <p class="fhir-export-note">
                Structural FHIR export only. No NHS Number or UK Core profiles are used.
            </p>
        </div>

        <div class="form-actions fhir-export-actions">
            <a href="/export-fhir.php?id=<?= (int) $submissionId ?>&amp;download=1" class="btn btn-primary">
                Download JSON
            </a>
            <button type="button" class="btn btn-outline"
                onclick="navigator.clipboard.writeText(document.getElementById('fhir-output').textContent)">
                Copy to clipboard
            </button>
        </div>

        <pre id="fhir-output" class="fhir-preview"><?= escape($json) ?></pre>
    </div>
</div>
<?php include __DIR__ . '/code/views/layouts/footer.php'; ?>