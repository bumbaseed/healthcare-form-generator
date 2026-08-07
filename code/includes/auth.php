<?php
/**
 * Auth helpers backed by staff_users. Passwords are bcrypt (password_hash / password_verify), failures are counted per account, and an active session holds the logged-in user plus the currently selected patient.
 */

if (!isset($_SESSION)) {
    // Tighten the session cookie before starting. Secure auto-enables on HTTPS; on plain HTTP (local dev) setting it would silently break the session, so honour the actual transport.
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? 0) == 443);

    session_set_cookie_params([
        'lifetime' => 3600,
        'path' => '/',
        'httponly' => true,
        'secure' => $isHttps,
        'samesite' => 'Strict',
    ]);
    session_start();
}

define('SESSION_LIFETIME', 3600); // 1 hour

function enforceSessionTimeout(): void
{
    if (!isset($_SESSION['login_time']))
        return;

    if (time() - $_SESSION['login_time'] > SESSION_LIFETIME) {
        logoutUser();
        header('Location: /login.php?expired=1');
        exit();
    }

    // Sliding window: bump the timer so active users don't get kicked out.
    $_SESSION['login_time'] = time();
}

enforceSessionTimeout();


function isLoggedIn(): bool
{
    return isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
}

function hasPatientNumber(): bool
{
    return isset($_SESSION['patient_number']) && !empty($_SESSION['patient_number']);
}

function getUserId(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

function getUserRole(): ?string
{
    return $_SESSION['user_role'] ?? null;
}

function isAdmin(): bool
{
    return getUserRole() === 'admin';
}


// Throttling. Five failures locks the account for fifteen minutes.
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCKOUT_SECONDS = 900;

/**
 * Validate a username/password against staff_users.
 *
 * Returns:
 *   ['status' => 'ok','user' => array]
 *   ['status' => 'locked', 'locked_until_epoch' => int]
 *   ['status' => 'failed']
 *
 * On the LOGIN_MAX_ATTEMPTS consecutive failure the account is locked  for LOGIN_LOCKOUT_SECONDS. A correct password resets the counter, and an expired lockout also resets it so the user gets a fresh budget rather than being re-locked on the next miss.
 */
function validateLogin(string $username, string $password): array
{
    require_once __DIR__ . '/db_connection.php';
    $db = Database::getInstance();

    // Compute is_locked and the unlock epoch in SQL so both sides of the  comparison share PostgreSQL's clock. Doing it in PHP would depend on date.timezone matching the DB session timezone, on Windows where PHP often defaults to UTC and Postgres to local time.
    $user = $db->fetchOne(
        "SELECT user_id, username, password_hash, full_name, role, is_active,
                failed_attempts,
                (locked_until IS NOT NULL AND locked_until > CURRENT_TIMESTAMP) AS is_locked,
                EXTRACT(EPOCH FROM locked_until)::bigint AS locked_until_epoch,
                (locked_until IS NOT NULL AND locked_until <= CURRENT_TIMESTAMP) AS lockout_expired
         FROM staff_users
         WHERE username = :username",
        ['username' => $username]
    );

    // Generic failure for unknown or inactive accounts. The response is intentionally indistinguishable from a wrong password so the endpoint doesn't leak which usernames exist.
    if (!$user || !$user['is_active']) {
        auditLog(
            'login_failed',
            'staff_users',
            $user['user_id'] ?? null,
            "Failed login for username '{$username}' (unknown or inactive account)",
            $user['user_id'] ?? null
        );
        return ['status' => 'failed'];
    }

    // Honour an active lockout before spending time on password_verify.
    if ($user['is_locked']) {
        return ['status' => 'locked', 'locked_until_epoch' => (int) $user['locked_until_epoch']];
    }

    // If the previous lockout has elapsed, start the budget at zero rather than carrying over the high-water failed_attempts from last time.
    $previousAttempts = $user['lockout_expired']
        ? 0
        : (int) ($user['failed_attempts'] ?? 0);

    if (!password_verify($password, $user['password_hash'])) {
        $attempts = $previousAttempts + 1;

        if ($attempts >= LOGIN_MAX_ATTEMPTS) {
            // Threshold hit: lock the account and read back the unlock time as an epoch, for the same timezone-safety reasons as above.
            $row = $db->fetchOne(
                "UPDATE staff_users
                 SET failed_attempts = :attempts,
                     locked_until = CURRENT_TIMESTAMP + (:lockSeconds || ' seconds')::interval
                 WHERE user_id = :userId
                 RETURNING EXTRACT(EPOCH FROM locked_until)::bigint AS locked_until_epoch",
                [
                    'attempts' => $attempts,
                    'lockSeconds' => (string) LOGIN_LOCKOUT_SECONDS,
                    'userId' => $user['user_id'],
                ]
            );
            auditLog(
                'login_locked',
                'staff_users',
                $user['user_id'],
                "Account locked after {$attempts} failed attempts",
                $user['user_id']
            );
            return [
                'status' => 'locked',
                'locked_until_epoch' => (int) ($row['locked_until_epoch'] ?? 0),
            ];
        }

        $db->execute(
            "UPDATE staff_users SET failed_attempts = :attempts WHERE user_id = :userId",
            ['attempts' => $attempts, 'userId' => $user['user_id']]
        );
        auditLog(
            'login_failed',
            'staff_users',
            $user['user_id'],
            "Failed login for user '{$user['username']}' (attempt {$attempts} of " . LOGIN_MAX_ATTEMPTS . ")",
            $user['user_id']
        );
        return ['status' => 'failed'];
    }

    // Success: reset the throttle and stamp last_login_at.
    $db->execute(
        "UPDATE staff_users
         SET last_login_at = CURRENT_TIMESTAMP,
             failed_attempts = 0,
             locked_until = NULL
         WHERE user_id = :userId",
        ['userId' => $user['user_id']]
    );

    auditLog(
        'login_success',
        'staff_users',
        $user['user_id'],
        "User '{$user['username']}' logged in successfully",
        $user['user_id']
    );

    return ['status' => 'ok', 'user' => $user];
}

/**
 * Bind a validated user to the current session. Regenerates the session ID to prevent fixation.
 */
function loginUser(array $user): bool
{
    session_regenerate_id(true);

    $_SESSION['user_logged_in'] = true;
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['login_time'] = time();

    return true;
}

function setPatientSession(string $patientNumber, ?int $patientId = null): bool
{
    $_SESSION['patient_number'] = $patientNumber;
    $_SESSION['patient_id'] = $patientId;
    return true;
}

function logoutUser(): bool
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
    return true;
}


function requireAuth(bool $requirePatient = true): void
{
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit();
    }

    if ($requirePatient && !hasPatientNumber()) {
        header('Location: /patient-entry.php');
        exit();
    }
}

/**
 * Require admin role. Non-admins are redirected to the dashboard with an error flash. Patient context is also enforced, so an admin without an MRN selected is sent through /patient-entry.php first like everyone else.
 */
function requireAdmin(): void
{
    requireAuth(true);

    if (!isAdmin()) {
        require_once __DIR__ . '/functions.php';
        setFlash('error', 'You do not have permission to access that page.');
        header('Location: /index.php');
        exit();
    }
}

function getSessionPatientData(): ?array
{
    if (!hasPatientNumber()) {
        return null;
    }

    return [
        'patient_id' => $_SESSION['patient_id'] ?? null,
        'patient_number' => $_SESSION['patient_number'] ?? null,
        'patient_name' => $_SESSION['patient_name'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'full_name' => $_SESSION['full_name'] ?? null,
    ];
}


/**
 * Insert a row into audit_log. $actorUserId lets the caller name a user who isn't (or won't be) in the session, for example a failed-login lockout where loginUser() never ran. If omitted, the current session user is used.
 */
function auditLog(
    string $action,
    ?string $entityType = null,
    ?int $entityId = null,
    ?string $details = null,
    ?int $actorUserId = null
): void {
    try {
        require_once __DIR__ . '/db_connection.php';
        $db = Database::getInstance();

        $db->insert('audit_log', [
            'user_id' => $actorUserId ?? getUserId(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ], 'log_id');
    } catch (Exception $e) {
        // Audit failure must never break the application.
        error_log('Audit log failed: ' . $e->getMessage());
    }
}
