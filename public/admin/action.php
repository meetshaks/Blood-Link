<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/admin.php';
$admin = require_admin();
$returnTo = in_array((string) ($_POST['return'] ?? ''), ['users', 'pending'], true) ? (string) $_POST['return'] : 'users';
$returnUrl = '/admin/' . $returnTo . '.php';
if (!is_post()) redirect('/admin/users.php');
if (!csrf_validate()) { flash('error', t('invalid_token')); redirect($returnUrl); }
$action = (string) ($_POST['action'] ?? '');
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
try {
    if ($id === false || $id < 1) throw new InvalidArgumentException('Invalid donor account.');
    if ($action === 'delete') admin_delete_user((int) $admin['id'], (int) $id);
    elseif ($action === 'reset_password') {
        if ((string) ($_POST['verified'] ?? '') !== '1') throw new InvalidArgumentException(t('recovery_verification_required'));
        $password = admin_reset_password((int) $admin['id'], (int) $id, (string) ($_POST['note'] ?? ''));
        $stmt = db()->prepare('SELECT name, phone FROM users WHERE id = ?'); $stmt->execute([(int) $id]); $donor = $stmt->fetch();
        session_set('admin_temp_password', ['password' => $password, 'phone' => is_array($donor) ? (string) $donor['phone'] : '']);
    }
    else admin_update_user((int) $admin['id'], (int) $id, $action, (string) ($_POST['reason'] ?? ''), (int) ($_POST['months'] ?? 0));
    flash('success', t('admin_action_complete'));
} catch (Throwable $exception) {
    app_log('Admin donor action failed', 'ERROR', ['exception' => $exception->getMessage()]);
    flash('error', $exception instanceof InvalidArgumentException ? $exception->getMessage() : t('admin_action_failed'));
}
redirect($returnUrl);
