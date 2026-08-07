<?php
/**
 * Dashboard for the currently selected patient. Shows the count of available forms, the count of completed submissions, and the five most recent completions.
 */

require_once __DIR__ . '/code/includes/db_connection.php';
require_once __DIR__ . '/code/includes/functions.php';
require_once __DIR__ . '/code/includes/auth.php';

requireAuth(true);

$patientData = getSessionPatientData();
$patientId = $patientData['patient_id'];
$patientNumber = $patientData['patient_number'];

$pageTitle = 'My Dashboard';

$db = Database::getInstance();

$stats = [
    'available_forms' => 0,
    'completed_forms' => 0,
];

$recent_submissions = [];

try {
    $result = $db->fetchOne("SELECT COUNT(*) as count FROM form_definitions WHERE is_active = true");
    $stats['available_forms'] = $result['count'] ?? 0;

    if ($patientId) {
        $result = $db->fetchOne(
            "SELECT COUNT(*) as count FROM form_submissions
             WHERE patient_id = :patientId AND submission_status = 'completed' AND is_deleted = false",
            ['patientId' => $patientId]
        );
        $stats['completed_forms'] = $result['count'] ?? 0;

        $recent_submissions = $db->fetchAll("
            SELECT fs.submission_id, fs.submission_status, fs.submitted_at, fd.form_name
            FROM form_submissions fs
            LEFT JOIN form_definitions fd ON fs.form_id = fd.form_id
            WHERE fs.patient_id = :patientId
              AND fs.submission_status = 'completed'
              AND fs.is_deleted = false
            ORDER BY fs.submitted_at DESC
            LIMIT 5
        ", ['patientId' => $patientId]);
    }

} catch (Exception $e) {
    // First-run path: schema hasn't been loaded yet, fall back to a soft warning instead of crashing the dashboard.
    if (
        strpos($e->getMessage(), 'does not exist') !== false ||
        strpos($e->getMessage(), 'relation') !== false
    ) {
        setFlash('warning', 'Database not initialized. Using demo mode.');
    } else {
        setFlash('error', 'An error occurred while loading the dashboard.');
        error_log('Dashboard load failed: ' . $e->getMessage());
        $appConfig = require __DIR__ . '/config/app.php';
        if ($appConfig['debug_mode'] ?? false) {
            echo '<pre>' . escape($e->getMessage()) . '</pre>';
        }
    }
}

include __DIR__ . '/code/views/layouts/header.php';
include __DIR__ . '/code/views/dashboard/patient-dashboard.php';
include __DIR__ . '/code/views/layouts/footer.php';
