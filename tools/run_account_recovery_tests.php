<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/lib/admin.php';

$admin = db()->query("SELECT id FROM admins WHERE is_active = 1 ORDER BY id LIMIT 1")->fetch();
$user = db()->query("SELECT * FROM users WHERE status = 'approved' ORDER BY id LIMIT 1")->fetch();
if (!is_array($admin) || !is_array($user)) {
    fwrite(STDERR, "An active admin and approved donor are required.\n");
    exit(1);
}
$userId = (int) $user['id'];
$adminId = (int) $admin['id'];
$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};

try {
    session_set('admin_password_resets', ['started' => time(), 'count' => 0]);
    $password = admin_reset_password($adminId, $userId, 'Automated recovery test');
    $fresh = db()->prepare('SELECT password_hash, must_change_password, password_changed_at, session_version FROM users WHERE id = ?');
    $fresh->execute([$userId]);
    $row = $fresh->fetch();
    $assert(is_array($row) && password_verify($password, (string) $row['password_hash']), 'Temporary password was not stored as a valid hash.');
    $assert((int) $row['must_change_password'] === 1 && $row['password_changed_at'] === null, 'Forced-change flags were not set.');
    $assert(attempt_user_login((string) $user['phone'], $password)['ok'] === true, 'Temporary password did not log in.');
    $log = db()->prepare("SELECT details FROM admin_logs WHERE admin_id = ? AND target_user_id = ? AND action = 'user_password_reset' ORDER BY id DESC LIMIT 1");
    $log->execute([$adminId, $userId]);
    $details = (string) $log->fetchColumn();
    $assert(!str_contains($details, $password), 'Temporary password appeared in the audit log.');
} finally {
    db()->prepare('UPDATE users SET password_hash = ?, must_change_password = ?, password_changed_at = ?, session_version = ? WHERE id = ?')
        ->execute([$user['password_hash'], $user['must_change_password'], $user['password_changed_at'], $user['session_version'], $userId]);
    db()->prepare("DELETE FROM admin_logs WHERE admin_id = ? AND target_user_id = ? AND action = 'user_password_reset' AND details LIKE '%Automated recovery test%'")
        ->execute([$adminId, $userId]);
    session_forget('admin_password_resets');
    destroy_session();
}
echo "account recovery tests: PASS\n";
