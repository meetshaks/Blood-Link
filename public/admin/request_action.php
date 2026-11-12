<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/admin.php';
$admin = require_admin();
if (!is_post() || !csrf_validate()) { flash('error', 'Invalid request.'); redirect('/admin/requests.php'); }
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
try {
    if ($id === false || $id < 1) throw new InvalidArgumentException('Invalid request.');
    $pdo = db();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT * FROM change_requests WHERE id = ? AND status = 'pending' FOR UPDATE");
    $stmt->execute([$id]); $request = $stmt->fetch();
    if (!is_array($request)) throw new InvalidArgumentException('Request was already decided.');
    $approve = ($_POST['action'] ?? '') === 'approve_request';
    if ($approve) $pdo->prepare("UPDATE users SET {$request['field_name']} = ? WHERE id = ?")->execute([$request['new_value'], $request['user_id']]);
    $pdo->prepare('UPDATE change_requests SET status = ?, decided_by = ?, decided_at = NOW() WHERE id = ?')->execute([$approve ? 'approved' : 'rejected', $admin['id'], $id]);
    $pdo->commit();
    log_admin_action((int) $admin['id'], $approve ? 'approve_change_request' : 'reject_change_request', (int) $request['user_id'], ['field' => $request['field_name']]);
    flash('success', t('admin_action_complete'));
} catch (Throwable $e) { if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack(); flash('error', $e->getMessage()); }
redirect('/admin/requests.php');
