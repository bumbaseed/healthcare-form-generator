<?php
/**
 * Login screen. Verifies staff credentials with bcrypt and forwards the user to MRN entry on success. Already-authenticated requests skip straight to the dashboard or MRN entry as appropriate.
 */

require_once __DIR__ . '/code/includes/functions.php';
require_once __DIR__ . '/code/includes/auth.php';

if (isLoggedIn()) {
    if (hasPatientNumber()) {
        redirect('/index.php');
    } else {
        redirect('/patient-entry.php');
    }
}

$error = null;

if (get('expired') === '1') {
    $error = 'Your session has expired. Please log in again.';
}

if (isPost()) {
    requireCsrfToken();

    $username = trim(post('username', ''));
    $password = post('password', '');

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $result = validateLogin($username, $password);

        if ($result['status'] === 'ok') {
            loginUser($result['user']);
            auditLog('login', 'staff_users', $result['user']['user_id'], 'Staff login');
            redirect('/patient-entry.php');
        } elseif ($result['status'] === 'locked') {
            // Unlock time arrives as a unix epoch from Postgres so the display is correct regardless of PHP and DB timezone drift.
            $unlockTs = (int) ($result['locked_until_epoch'] ?? 0);
            $unlockDisplay = $unlockTs > 0
                ? (date('Y-m-d', $unlockTs) === date('Y-m-d')
                    ? date('H:i', $unlockTs)
                    : date('d M Y H:i', $unlockTs))
                : 'shortly';
            $error = 'Account locked until: ' . $unlockDisplay . '. Too many failed attempts.';
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

$pageTitle = 'Login';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Healthcare Form Generator</title>
    <link rel="stylesheet" href="/css/main.css">
    <link rel="stylesheet" href="/css/pages/login.css">
</head>

<body>
    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-header">
                <!-- Inline rather than from the sprite: this page doesn't include header.php, so #icon-stethoscope isn't in the DOM here. -->
                <div class="brand-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M11 2v2" />
                        <path d="M5 2v2" />
                        <path d="M5 3H4a2 2 0 0 0-2 2v4a6 6 0 0 0 12 0V5a2 2 0 0 0-2-2h-1" />
                        <path d="M8 15a6 6 0 0 0 12 0v-3" />
                        <circle cx="20" cy="10" r="2" />
                    </svg>
                </div>
                <h1>Healthcare Portal</h1>
                <p>Staff Access Login</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <?= escape($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/login.php">
                <?= csrfField() ?>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" required autofocus
                        value="<?= escape(post('username', '')) ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    Login
                </button>
            </form>
        </div>
    </div>
</body>

</html>