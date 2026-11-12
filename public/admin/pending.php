<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/admin.php';
require_once __DIR__ . '/../../app/views/partials/admin_ui.php';
$admin = require_admin();
$pending = db()->query(
    "SELECT u.id, u.name, u.phone, u.blood_group, u.created_at, u.photo, u.status, u.is_suspended, u.suspended_until,
            d.name_bn AS district_bn, d.name_en AS district_en
     FROM users u LEFT JOIN districts d ON d.id = u.district_id
     WHERE u.status = 'pending' ORDER BY u.created_at ASC LIMIT 100"
)->fetchAll();
$pageTitle = t('pending_donors');
$adminNav = 'pending';
require __DIR__ . '/../../app/views/layout/header.php';
?>
<div class="admin-heading"><div><h1><?= e(t('pending_donors')) ?></h1><p class="muted" style="margin:0"><?= e(t('pending_help')) ?></p></div></div>
<section class="card">
    <?php if ($pending === []): ?>
        <div class="empty-state"><div class="card-icon"><?= icon('check-circle', 26) ?></div><h3><?= e(t('no_pending_donors')) ?></h3></div>
    <?php else: ?>
    <form method="post" action="<?= e(app_url('admin/bulk_action.php')) ?>" id="bulk-pending-form" data-confirm="<?= e(t('bulk_approve_confirm')) ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="approve">
        <div class="bulk-toolbar">
            <label><input type="checkbox" id="select-all-pending"> <?= e(t('select_all')) ?></label>
            <span id="pending-selected" aria-live="polite"><?= e(t('selected_count', ['n' => 0])) ?></span>
            <button class="button button-small button-success" type="submit" id="approve-selected" disabled><?= icon('check', 16) ?><?= e(t('approve_selected')) ?></button>
        </div>
    </form>
    <div id="pending-rows">
    <?php endif; ?>
    <?php foreach ($pending as $donor): ?>
        <article class="admin-list-row" style="flex-wrap:wrap">
            <div class="row-main"><label class="sr-only" for="pending-<?= (int) $donor['id'] ?>"><?= e(t('select_all') . ': ' . $donor['name']) ?></label><input id="pending-<?= (int) $donor['id'] ?>" form="bulk-pending-form" type="checkbox" name="ids[]" value="<?= (int) $donor['id'] ?>" class="pending-check"><?= admin_avatar($donor) ?><div><strong><?= e($donor['name']) ?></strong> <span class="blood-badge blood-badge-sm"><?= e($donor['blood_group']) ?></span><p class="muted text-small"><?= e($donor['phone']) ?> · <?= e(admin_district($donor) ?: '—') ?> · <?= e(date_fmt((string) $donor['created_at'])) ?></p></div></div>
            <div class="admin-inline-actions">
                <?= admin_action_form('approve', (int) $donor['id'], t('approve'), 'check', 'pending', null, false, 'admin/action.php', 'button button-small button-success') ?>
                <?= admin_modal_button('modal-reject', (int) $donor['id'], (string) $donor['name'], t('reject'), 'x-circle', 'button button-small button-secondary') ?>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if ($pending !== []): ?></div><?php endif; ?>
</section>
<?= admin_action_modals('pending') ?>
<?php require __DIR__ . '/../../app/views/layout/footer.php'; ?>
