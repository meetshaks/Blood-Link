<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/admin.php';
require_once __DIR__ . '/../../app/views/partials/admin_ui.php';
$admin = require_admin();
$stats = admin_statistics();
$groups = admin_blood_group_counts();
$recent = admin_recent_users(10);
$max = max(1, ...array_values($groups));
$lowest = min($groups);
$divisionId = filter_var($_GET['division'] ?? null, FILTER_VALIDATE_INT);
$divisionId = $divisionId !== false && $divisionId > 0 ? $divisionId : null;
$matrix = admin_district_blood_matrix($divisionId);
$dashboardDivisions = db()->query('SELECT id, name_bn, name_en FROM divisions ORDER BY name_bn')->fetchAll();
$matrixGroups = array_keys($groups);
$gaps = [];
foreach ($matrix as $row) {
    $registered = array_sum($row['counts']);
    foreach ($row['counts'] as $group => $count) {
        if ($count <= 2) {
            $gaps[] = [
                'name' => current_lang() === 'bn' ? $row['name_bn'] : $row['name_en'],
                'group' => $group,
                'count' => $count,
                'registered' => $registered,
            ];
        }
    }
}
usort($gaps, static function (array $a, array $b): int {
    return ($a['count'] <=> $b['count'])
        ?: ($b['registered'] <=> $a['registered'])
        ?: strnatcasecmp($a['name'], $b['name'])
        ?: strcmp($a['group'], $b['group']);
});
$pageTitle = t('admin_dashboard');
$adminNav = 'dashboard';
require __DIR__ . '/../../app/views/layout/header.php';
?>
<div class="admin-heading"><div><h1><?= e(t('admin_dashboard')) ?></h1><p class="muted" style="margin:0"><?= e($admin['name']) ?> · <?= e($admin['role']) ?></p></div></div>

<section class="admin-stats" aria-label="<?= e(t('admin_statistics')) ?>">
    <article class="card stat-card"><span class="stat-label"><?= e(t('total_donors')) ?></span><strong><?= e(num($stats['total'])) ?></strong></article>
    <a class="card stat-card stat-pending" href="<?= e(app_url('admin/pending.php')) ?>"><span class="stat-label"><?= e(t('pending_donors')) ?><?= icon('chevron-right', 14) ?></span><strong><?= e(num($stats['pending'])) ?></strong></a>
    <article class="card stat-card stat-green"><span class="stat-label"><?= e(t('available_donors')) ?></span><strong><?= e(num($stats['available'])) ?></strong></article>
    <article class="card stat-card stat-red"><span class="stat-label"><?= e(t('suspended_donors')) ?></span><strong><?= e(num($stats['suspended'])) ?></strong></article>
</section>

<section class="card" aria-labelledby="shortage-title">
    <div class="shortage-card-head"><h2 id="shortage-title"><?= e(t('shortage_by_district')) ?></h2>
        <form method="get"><label class="sr-only" for="matrix-division"><?= e(t('division')) ?></label><select id="matrix-division" name="division" onchange="this.form.submit()"><option value=""><?= e(t('all_divisions')) ?></option><?php foreach ($dashboardDivisions as $division): ?><option value="<?= (int) $division['id'] ?>" <?= $divisionId === (int) $division['id'] ? 'selected' : '' ?>><?= e(current_lang() === 'bn' ? $division['name_bn'] : $division['name_en']) ?></option><?php endforeach; ?></select></form>
    </div>
    <?php if ($gaps !== []): ?><div class="shortage-major" aria-label="<?= e(t('shortage_major')) ?>">
        <div class="shortage-major-label"><?= icon('alert', 18) ?><strong><?= e(t('shortage_major')) ?></strong></div>
        <div class="shortage-chips">
            <?php foreach (array_slice($gaps, 0, 6) as $gap): ?><span class="shortage-chip"><span><?= e($gap['name']) ?></span><b><?= e($gap['group']) ?></b><span><?= e(num($gap['count'])) ?> <?= e(t('shortage_donors_unit')) ?></span></span><?php endforeach; ?>
        </div>
    </div><?php endif; ?>
    <div class="shortage-toolbar">
        <label class="shortage-switch"><input id="show-gaps" type="checkbox" class="sr-only"><span class="switch" aria-hidden="true"></span><span class="switch-label"><?= e(t('show_gaps_only')) ?></span></label>
        <div class="shortage-legend" aria-label="<?= e(t('shortage_legend')) ?>">
            <span><i class="shortage-zero"></i><?= e(t('shortage_legend_zero')) ?></span>
            <span><i class="shortage-low"></i><?= e(t('shortage_legend_low')) ?></span>
            <span><i class="shortage-medium"></i><?= e(t('shortage_legend_medium')) ?></span>
            <span><i class="shortage-high"></i><?= e(t('shortage_legend_high')) ?></span>
            <small><?= e(t('shortage_donors_unit')) ?></small>
        </div>
    </div>
    <div class="table-wrap shortage-wrap"><table class="data-table shortage-table"><colgroup><col class="shortage-district-column"><?php foreach ($matrixGroups as $group): ?><col><?php endforeach; ?></colgroup><thead><tr><th><?= e(t('district')) ?></th><?php foreach ($matrixGroups as $group): ?><th><?= e($group) ?></th><?php endforeach; ?></tr></thead><tbody>
    <?php foreach ($matrix as $row): $hasGap = in_array(0, $row['counts'], true); $districtName = current_lang() === 'bn' ? $row['name_bn'] : $row['name_en']; ?>
        <tr data-has-gap="<?= $hasGap ? '1' : '0' ?>"><th class="district-name" scope="row" data-district-name="<?= e($districtName) ?>" aria-label="<?= e($districtName) ?>"><?= e($districtName) ?></th><?php foreach ($row['counts'] as $group => $count): $level = $count === 0 ? 'zero' : ($count <= 2 ? 'low' : ($count <= 5 ? 'medium' : 'high')); ?><td class="shortage-cell shortage-<?= $level ?>" aria-label="<?= e($districtName . ', ' . $group . ', ' . $count) ?>"><?= e(num($count)) ?></td><?php endforeach; ?></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</section>
<section class="mini-stats" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px">
    <div class="mini-stat"><strong><?= e(num($stats['approved'])) ?></strong><span><?= e(t('approved_donors')) ?></span></div>
    <div class="mini-stat"><strong><?= e(num($stats['rejected'])) ?></strong><span><?= e(t('rejected_donors')) ?></span></div>
    <div class="mini-stat"><strong><?= e(num($stats['donations'])) ?></strong><span><?= e(t('total_donations')) ?></span></div>
</section>

<section class="card" aria-labelledby="chart-title">
    <div class="card-head"><h2 id="chart-title" style="font-size:1.1rem"><?= e(t('chart_title')) ?></h2><span class="badge badge-danger"><?= icon('alert', 14) ?><?= e(t('lowest_group_hint')) ?></span></div>
    <div class="bar-chart" role="img" aria-label="<?= e(t('chart_title')) ?>">
        <?php foreach ($groups as $group => $count): ?>
            <div class="bar-row <?= $count === $lowest ? 'is-low' : '' ?>">
                <span class="bar-name"><?= e($group) ?></span>
                <span class="bar-track"><span style="width:<?= (int) round($count / $max * 100) ?>%"></span></span>
                <span class="bar-val"><?= e(num($count)) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="card" aria-labelledby="recent-title" style="margin-top:20px">
    <div class="card-head"><h2 id="recent-title" style="font-size:1.1rem"><?= e(t('recent_registrations')) ?></h2><a href="<?= e(app_url('admin/users.php')) ?>"><?= e(t('view_all')) ?></a></div>
    <?php if ($recent === []): ?><p class="muted"><?= e(t('no_donors_yet')) ?></p><?php else: ?>
    <div class="table-wrap">
        <table class="data-table responsive-table">
            <thead><tr><th><?= e(t('col_donor')) ?></th><th><?= e(t('blood_group')) ?></th><th><?= e(t('col_area')) ?></th><th><?= e(t('col_status')) ?></th><th><?= e(t('col_registered')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($recent as $u): ?>
                <tr>
                    <td class="cell-main"><div class="cell-user"><?= admin_avatar($u) ?><div><strong><?= e($u['name']) ?></strong><span class="muted text-small"><?= e($u['phone']) ?></span></div></div></td>
                    <td data-label="<?= e(t('blood_group')) ?>"><span class="blood-badge blood-badge-sm"><?= e($u['blood_group']) ?></span></td>
                    <td data-label="<?= e(t('col_area')) ?>"><?= e(admin_district($u) ?: '—') ?></td>
                    <td data-label="<?= e(t('col_status')) ?>"><?= admin_status_badge($u) ?></td>
                    <td data-label="<?= e(t('col_registered')) ?>" class="log-time"><?= e(date_fmt((string) $u['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../../app/views/layout/footer.php'; ?>
