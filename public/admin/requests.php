<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/admin.php';
$admin = require_admin();
$requests = db()->query("SELECT cr.*, u.name, u.phone FROM change_requests cr JOIN users u ON u.id = cr.user_id WHERE cr.status = 'pending' ORDER BY cr.created_at ASC")->fetchAll();
$pageTitle = t('change_requests');
$adminNav = 'requests';
require __DIR__ . '/../../app/views/layout/header.php';
?>
<div class="admin-heading"><div><h1><?= e(t('change_requests')) ?></h1><p class="muted" style="margin:0"><?= e(t('change_requests_help')) ?></p></div></div>
<section class="card">
    <?php if ($requests === []): ?><div class="empty-state"><div class="card-icon"><?= icon('inbox', 26) ?></div><h3><?= e(t('no_pending_requests')) ?></h3></div><?php endif; ?>
    <?php foreach ($requests as $request): ?>
        <article class="admin-list-row" style="flex-wrap:wrap">
            <div><strong><?= e($request['name']) ?></strong><p class="muted text-small"><?= e($request['phone']) ?> · <?= e(t($request['field_name'])) ?>: <span class="badge badge-unavailable"><?= e((string) $request['old_value']) ?></span> → <span class="badge badge-available"><?= e((string) $request['new_value']) ?></span></p></div>
            <div class="admin-inline-actions">
                <form method="post" action="<?= e(app_url('admin/request_action.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="approve_request"><input type="hidden" name="id" value="<?= (int) $request['id'] ?>"><button class="button button-small button-success" type="submit"><?= icon('check', 16) ?><?= e(t('approve_request')) ?></button></form>
                <form method="post" action="<?= e(app_url('admin/request_action.php')) ?>" data-confirm="<?= e(t('reject_request_confirm')) ?>" data-danger="1"><?= csrf_field() ?><input type="hidden" name="action" value="reject_request"><input type="hidden" name="id" value="<?= (int) $request['id'] ?>"><button class="button button-small button-secondary" type="submit"><?= icon('x-circle', 16) ?><?= e(t('reject_request')) ?></button></form>
            </div>
        </article>
    <?php endforeach; ?>
</section>
<?php require __DIR__ . '/../../app/views/layout/footer.php'; ?>
