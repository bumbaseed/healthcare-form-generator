<?php
/**
 * Patient case file. Shows every completed form submission for the patient currently selected in the session. The "case file" itself is just a query over form_submissions, there is no separate entity for it.
 */

require_once __DIR__ . '/code/includes/db_connection.php';
require_once __DIR__ . '/code/includes/functions.php';
require_once __DIR__ . '/code/includes/auth.php';
require_once __DIR__ . '/code/models/FormSubmissions.php';

requireAuth(true);

$patientData = getSessionPatientData();
$patientId = $patientData['patient_id'];
$patientNumber = $patientData['patient_number'];

$pageTitle = 'Patient Case File';
$submissions = [];

try {
    $model = new FormSubmissions();
    $submissions = $model->getCompletedByPatient($patientId);
} catch (Exception $e) {
    setFlash('error', 'Could not load case file.');
}

include __DIR__ . '/code/views/layouts/header.php';
include __DIR__ . '/code/views/cases/list.php';
include __DIR__ . '/code/views/layouts/footer.php';
