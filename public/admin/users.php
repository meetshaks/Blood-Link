<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/admin.php';
require_once __DIR__ . '/../../app/views/partials/admin_ui.php';
$admin = require_admin();
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = admin_user_list($_GET, $page);
$temporaryPassword = session_get('admin_temp_password');
if (is_array($temporaryPassword)) session_forget('admin_temp_password');
$query = array_filter(['q' => (string) ($_GET['q'] ?? ''), 'status' => (string) ($_GET['status'] ?? ''), 'blood_group' => (string) ($_GET['blood_group'] ?? ''), 'division' => (string) ($_GET['division'] ?? ''), 'district' => (string) ($_GET['district'] ?? '')], static fn ($v) => $v !== '');
$divisions = db()->query('SELECT id, name_bn, name_en FROM divisions ORDER BY name_bn')->fetchAll();
$districts = [];
if (filter_var($_GET['division'] ?? null, FILTER_VALIDATE_INT)) {
    $stmt = db()->prepare('SELECT id, name_bn, name_en FROM districts WHERE division_id = ? ORDER BY name_bn');
    $stmt->execute([(int) $_GET['division']]);
    $districts = $stmt->fetchAll();
}
$pageTitle = t('donor_management');
$adminNav = 'users';
require __DIR__ . '/../../app/views/layout/header.php';
?>
<div class="admin-heading"><h1><?= e(t('donor_management')) ?></h1><div class="button-row"><a class="button button-secondary" href="<?= e(app_url('admin/users_export.php') . ($query !== [] ? '?' . http_build_query($query) : '')) ?>"><?= icon('download', 18) ?><?= e(t('export_csv')) ?></a><a class="button" href="<?= e(app_url('admin/user_add.php')) ?>"><?= icon('user-plus', 18) ?><?= e(t('add_donor')) ?></a></div></div>
<?php if (is_array($temporaryPassword)): ?><section class="card temp-password-card"><h2><?= e(t('temporary_password')) ?></h2><p><?= e(t('temporary_password_note')) ?> <?= e((string) $temporaryPassword['phone']) ?></p><div class="temp-password"><code id="admin-temp-password"><?= e((string) $temporaryPassword['password']) ?></code><button type="button" class="button button-small button-secondary" data-copy="#admin-temp-password"><?= icon('copy', 16) ?><?= e(t('copy')) ?></button></div></section><?php endif; ?>
<section class="card">
    <form class="admin-filters" method="get" role="search">
        <div class="field"><label class="sr-only" for="q"><?= e(t('search_donors')) ?></label><input id="q" name="q" type="search" value="<?= e((string) ($_GET['q'] ?? '')) ?>" placeholder="<?= e(t('search_donors')) ?>"></div>
        <div class="field"><label class="sr-only" for="status"><?= e(t('col_status')) ?></label><select id="status" name="status"><option value=""><?= e(t('all_statuses')) ?></option><?php foreach (['pending', 'approved', 'rejected'] as $status): ?><option value="<?= $status ?>" <?= ($_GET['status'] ?? '') === $status ? 'selected' : '' ?>><?= e(t($status)) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label class="sr-only" for="blood_group"><?= e(t('blood_group_filter')) ?></label><select id="blood_group" name="blood_group"><option value=""><?= e(t('blood_group_filter')) ?></option><?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $group): ?><option value="<?= e($group) ?>" <?= ($_GET['blood_group'] ?? '') === $group ? 'selected' : '' ?>><?= e($group) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label class="sr-only" for="division"><?= e(t('division')) ?></label><select id="division" name="division"><option value=""><?= e(t('all_divisions')) ?></option><?php foreach ($divisions as $division): ?><option value="<?= (int) $division['id'] ?>" <?= (string) ($_GET['division'] ?? '') === (string) $division['id'] ? 'selected' : '' ?>><?= e(current_lang() === 'bn' ? $division['name_bn'] : $division['name_en']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label class="sr-only" for="district"><?= e(t('district')) ?></label><select id="district" name="district"><option value=""><?= e(t('all_districts')) ?></option><?php foreach ($districts as $district): ?><option value="<?= (int) $district['id'] ?>" <?= (string) ($_GET['district'] ?? '') === (string) $district['id'] ? 'selected' : '' ?>><?= e(current_lang() === 'bn' ? $district['name_bn'] : $district['name_en']) ?></option><?php endforeach; ?></select></div>
        <button class="button" type="submit"><?= icon('search', 18) ?><?= e(t('filter')) ?></button>
    </form>
    <?php if ($query !== []): ?><div class="filter-chips"><a class="badge badge-pending" href="<?= e(app_url('admin/users.php')) ?>"><?= e(t('clear_filters')) ?></a></div><?php endif; ?>
    <?php if ($result['users'] === []): ?>
        <div class="empty-state"><div class="card-icon"><?= icon('users', 26) ?></div><h3><?= e(t('no_donors_found')) ?></h3></div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table responsive-table">
            <thead><tr><th><?= e(t('col_donor')) ?></th><th><?= e(t('blood_group')) ?></th><th><?= e(t('col_area')) ?></th><th><?= e(t('col_status')) ?></th><th><?= e(t('last_donation_date')) ?></th><th><span class="sr-only"><?= e(t('col_actions')) ?></span></th></tr></thead>
            <tbody>
            <?php foreach ($result['users'] as $donor):
                $isSuspended = (int) $donor['is_suspended'] === 1;
            ?>
                <tr>
                    <td class="cell-main"><div class="cell-user"><?= admin_avatar($donor) ?><div><strong><?= e($donor['name']) ?></strong><span class="muted text-small"><?= e($donor['phone']) ?></span></div></div></td>
                    <td data-label="<?= e(t('blood_group')) ?>"><span class="blood-badge blood-badge-sm"><?= e($donor['blood_group']) ?></span></td>
                    <td data-label="<?= e(t('col_area')) ?>"><?= e(admin_district($donor) ?: '—') ?><?php if (!empty($donor['work_district_bn'])): ?><span class="muted text-small"><?= e(t('work_short') . ': ' . (current_lang() === 'bn' ? $donor['work_district_bn'] : $donor['work_district_en'])) ?></span><?php endif; ?></td>
                    <td data-label="<?= e(t('col_status')) ?>"><?= admin_status_badge($donor) ?></td>
                    <td data-label="<?= e(t('last_donation_date')) ?>" class="log-time"><?= $donor['last_donation_date'] ? e(date_fmt((string) $donor['last_donation_date'])) : '—' ?></td>
                    <td data-label="<?= e(t('col_actions')) ?>">
                        <details class="row-actions">
                            <summary aria-label="<?= e(t('col_actions') . ': ' . $donor['name']) ?>"><?= icon('more', 20) ?></summary>
                            <div class="menu">
                                <?php if ($donor['status'] === 'pending'): ?>
                                    <?= admin_action_form('approve', (int) $donor['id'], t('approve'), 'check', 'users') ?>
                                    <?= admin_modal_button('modal-reject', (int) $donor['id'], (string) $donor['name'], t('reject'), 'x-circle') ?>
                                <?php endif; ?>
                                <?php if ($isSuspended): ?>
                                    <?= admin_action_form('unsuspend', (int) $donor['id'], t('unsuspend'), 'rotate', 'users') ?>
                                <?php else: ?>
                                    <?= admin_modal_button('modal-suspend', (int) $donor['id'], (string) $donor['name'], t('suspend'), 'ban') ?>
                                <?php endif; ?>
                                <?= admin_modal_button('modal-reset-password', (int) $donor['id'], (string) $donor['name'], t('reset_password'), 'key', 'menu-item', false, mask_phone((string) $donor['phone'])) ?>
                                <?= admin_action_form('delete', (int) $donor['id'], t('delete'), 'trash', 'users', t('delete_donor_confirm'), true) ?>
                            </div>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= admin_pagination($result['page'], $result['per_page'], $result['total'], $query, app_url('admin/users.php')) ?>
    <?php endif; ?>
</section>
<script>
document.querySelector('#division')?.addEventListener('change', event => {
    const url = new URL(window.location.href);
    if (event.target.value) url.searchParams.set('division', event.target.value);
    else url.searchParams.delete('division');
    url.searchParams.delete('district');
    url.searchParams.delete('page');
    window.location.href = url;
});
</script>
<?= admin_action_modals('users') ?>
<?php require __DIR__ . '/../../app/views/layout/footer.php'; ?>
