<?php
/**
 * MRN entry. After login, staff land here to choose which patient's records they want to work with for the rest of the session.
 */

require_once __DIR__ . '/code/includes/functions.php';
require_once __DIR__ . '/code/includes/auth.php';
require_once __DIR__ . '/code/includes/db_connection.php';

requireAuth(false);

$error = null;
$db = Database::getInstance();

// "Switch patient" clears the current patient from the session. POST plus CSRF, so a malicious site can't trigger it via a GET link.
if (isPost() && post('action') === 'switch') {
    requireCsrfToken();
    unset($_SESSION['patient_number'], $_SESSION['patient_id'], $_SESSION['patient_name']);
    redirect('/patient-entry.php');
}

if (hasPatientNumber()) {
    redirect('/index.php');
}

if (isPost()) {
    requireCsrfToken();

    $patientNumber = trim(post('patient_number'));

    if (empty($patientNumber)) {
        $error = 'Please enter your patient number.';
    } else {
        try {
            $patient = $db->fetchOne(
                "SELECT patient_id, patient_identifier, first_name, last_name
                 FROM patients
                 WHERE patient_identifier = :patientNumber AND is_active = true",
                ['patientNumber' => $patientNumber]
            );

            if ($patient) {
                setPatientSession($patient['patient_identifier'], $patient['patient_id']);
                $_SESSION['patient_name'] = $patient['first_name'] . ' ' . $patient['last_name'];
                auditLog(
                    'patient_select',
                    'patients',
                    $patient['patient_id'],
                    "Selected patient {$patient['patient_identifier']}"
                );
                setFlash('success', 'Now viewing records for patient: ' . $patient['first_name'] . ' ' . $patient['last_name']);
                redirect('/index.php');
            } else {
                // No demo-mode fallback. A clinical application must never silently accept an unknown patient identifier.
                auditLog(
                    'patient_select_failed',
                    'patients',
                    null,
                    "Lookup failed for identifier: {$patientNumber}"
                );
                $error = 'No active patient was found with that identifier. Please check the number and try again.';
            }
        } catch (Exception $e) {
            error_log('Patient lookup failed: ' . $e->getMessage());
            $error = 'A system error occurred while looking up the patient. Please try again, or contact an administrator.';
        }
    }
}

$pageTitle = 'Enter Patient Number';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter Patient Number - Healthcare Form Generator</title>
    <link rel="stylesheet" href="/css/main.css">
    <link rel="stylesheet" href="/css/pages/patient-entry.css">
</head>

<body>
    <div class="patient-entry-wrapper">
        <div class="patient-entry-container">
            <div class="patient-entry-header">
                <div class="brand-icon">+</div>
                <h1>Select Patient</h1>
                <p>Enter patient number to access their records</p>
            </div>

            <div class="user-info">
                <p>Logged in as:
                    <strong><?= escape($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User') ?></strong>
                </p>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <?php echo escape($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/patient-entry.php">
                <?= csrfField() ?>
                <div class="form-group">
                    <label for="patient_number">Patient Number / Medical Record Number</label>
                    <input type="text" id="patient_number" name="patient_number" class="form-control patient-id-input"
                        placeholder="e.g., MRN123456, DEMO001" required autofocus>
                    <div class="form-help">
                        Enter the patient's unique identifier to access their records
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    Continue
                </button>
            </form>

            <div class="help-text">
                <h4>Staff Instructions</h4>
                <p>Enter the patient number to access their medical records and forms.</p>
                <p>Patient numbers can be found in:</p>
                <p>• Patient ID cards</p>
                <p>• Medical records system</p>
                <p>• Registration documents</p>
            </div>

            <div class="logout-link">
                <form method="POST" action="/logout.php">
                    <?= csrfField() ?>
                    <button type="submit" class="logout-link-button">← Logout</button>
                </form>
            </div>
        </div>
    </div>
</body>

</html>