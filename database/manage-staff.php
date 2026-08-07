<?php
/**
 * CLI staff management. Creates accounts, resets passwords, or lists users. Passwords are hashed with bcrypt (password_hash, PASSWORD_DEFAULT) before being written to staff_users.
 *
 *   php database/manage-staff.php create <username> <password> <full_name> [admin|staff]
 *   php database/manage-staff.php reset  <username> <new_password>
 *   php database/manage-staff.php list
 */

require_once __DIR__ . '/../code/includes/db_connection.php';

$action = $argv[1] ?? null;

if (!$action || !in_array($action, ['create', 'reset', 'list'])) {
    echo "Usage:\n";
    echo "  php manage-staff.php create <username> <password> <full_name> [admin|staff]\n";
    echo "  php manage-staff.php reset  <username> <new_password>\n";
    echo "  php manage-staff.php list\n";
    exit(1);
}

$db = Database::getInstance();

if ($action === 'create') {
    $username = $argv[2] ?? null;
    $password = $argv[3] ?? null;
    $fullName = $argv[4] ?? null;
    $role = $argv[5] ?? 'staff';

    if (!$username || !$password || !$fullName) {
        echo "Error: username, password, and full_name are required.\n";
        exit(1);
    }

    if (!in_array($role, ['admin', 'staff'])) {
        echo "Error: role must be 'admin' or 'staff'.\n";
        exit(1);
    }

    $existing = $db->fetchOne(
        "SELECT user_id FROM staff_users WHERE username = :u",
        ['u' => $username]
    );

    if ($existing) {
        echo "Error: username '{$username}' already exists.\n";
        exit(1);
    }

    $userId = $db->insert('staff_users', [
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'full_name' => $fullName,
        'role' => $role,
    ], 'user_id');

    echo "Created {$role} account '{$username}' (user_id: {$userId}).\n";
}

if ($action === 'reset') {
    $username = $argv[2] ?? null;
    $newPassword = $argv[3] ?? null;

    if (!$username || !$newPassword) {
        echo "Error: username and new_password are required.\n";
        exit(1);
    }

    $user = $db->fetchOne(
        "SELECT user_id FROM staff_users WHERE username = :u",
        ['u' => $username]
    );

    if (!$user) {
        echo "Error: user '{$username}' not found.\n";
        exit(1);
    }

    $db->execute(
        "UPDATE staff_users SET password_hash = :hash WHERE user_id = :id",
        ['hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => $user['user_id']]
    );

    echo "Password updated for '{$username}'.\n";
}

if ($action === 'list') {
    $users = $db->fetchAll(
        "SELECT user_id, username, full_name, role, is_active, last_login_at FROM staff_users ORDER BY user_id"
    );

    if (empty($users)) {
        echo "No staff accounts found.\n";
        exit(0);
    }

    echo str_pad('ID', 5) . str_pad('Username', 20) . str_pad('Name', 25) . str_pad('Role', 8) . str_pad('Active', 8) . "Last Login\n";
    echo str_repeat('-', 90) . "\n";

    foreach ($users as $u) {
        echo str_pad($u['user_id'], 5)
            . str_pad($u['username'], 20)
            . str_pad($u['full_name'], 25)
            . str_pad($u['role'], 8)
            . str_pad($u['is_active'] ? 'Yes' : 'No', 8)
            . ($u['last_login_at'] ?? 'Never') . "\n";
    }
}
