<?php
/**
 * Logout. POST-only and CSRF-checked so a forged GET, for example an <img src="/logout.php"> on a third-party page, cannot sign a staff member out.
 */

require_once __DIR__ . '/code/includes/auth.php';
require_once __DIR__ . '/code/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Avoid accidental GET logout. Send the user home, the nav's logout form is the supported path.
    header('Location: /index.php');
    exit;
}

requireCsrfToken();

auditLog('logout', 'staff_users', getUserId(), 'Staff logout');
logoutUser();

header('Location: /login.php');
exit;
