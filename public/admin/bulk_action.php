<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/admin.php';

$admin = require_admin();
if (!is_post()) redirect('/admin/pending.php');
if (!csrf_validate()) {
    flash('error', t('invalid_token'));
    redirect('/admin/pending.php');
}

$action = (string) ($_POST['action'] ?? '');
$ids = $_POST['ids'] ?? [];
if (!in_array($action, ['approve', 'reject'], true) || !is_array($ids) || count($ids) > 100) {
    flash('error', t('admin_action_failed'));
    redirect('/admin/pending.php');
}

$validIds = [];
foreach ($ids as $id) {
    $value = filter_var($id, FILTER_VALIDATE_INT);
    if ($value !== false && $value > 0) $validIds[] = (int) $value;
}
$validIds = array_values(array_unique($validIds));
if ($validIds === []) {
    flash('error', t('admin_action_failed'));
    redirect('/admin/pending.php');
}

$pdo = db();
$count = 0;
try {
    $pdo->beginTransaction();
    $check = $pdo->prepare("SELECT id FROM users WHERE id = ? AND status = 'pending' FOR UPDATE");
    foreach ($validIds as $id) {
        $check->execute([$id]);
        if ($check->fetchColumn() === false) continue;
        admin_update_user((int) $admin['id'], $id, $action, (string) ($_POST['reason'] ?? ''));
        $count++;
    }
    $pdo->commit();
    flash('success', t('bulk_action_summary', ['n' => num($count)]));
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    app_log('Bulk admin action failed', 'ERROR', ['exception' => $exception->getMessage()]);
    flash('error', t('admin_action_failed'));
}
redirect('/admin/pending.php');
