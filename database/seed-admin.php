<?php
/**
 * Creates the default admin account. Defaults: admin / admin123. Change the password after first login.
 *  php database/seed-admin.php
 */

require_once __DIR__ . '/../code/includes/db_connection.php';

$db = Database::getInstance();

$username = 'admin';
$password = 'admin123';
$fullName = 'System Administrator';
$role = 'admin';
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$existing = $db->fetchOne(
    "SELECT user_id FROM staff_users WHERE username = :username",
    ['username' => $username]
);

if ($existing) {
    echo "Admin account already exists (user_id: {$existing['user_id']}). Skipping.\n";
    exit(0);
}

$userId = $db->insert('staff_users', [
    'username' => $username,
    'password_hash' => $passwordHash,
    'full_name' => $fullName,
    'role' => $role,
], 'user_id');

echo "Admin account created successfully (user_id: {$userId}).\n";
echo "Username: {$username}\n";
echo "Password: {$password}\n";
echo "** Change this password immediately after first login. **\n";
