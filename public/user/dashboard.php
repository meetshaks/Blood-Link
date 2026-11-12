<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/donations.php';
$user = require_user();
$workLocation = get_user_work_location((int) $user['id']);
$workActive = $workLocation !== null && location_is_active($workLocation);
$suspended = (int) $user['is_suspended'] === 1
    && ($user['suspended_until'] === null || $user['suspended_until'] > date('Y-m-d'));

$gap = max(1, (int) setting_value('donation_gap_days', '120'));
$donations = list_user_donations((int) $user['id']);
$totalDonations = count($donations);
$badgeSummary = donor_badge_summary((int) $user['id']);
$pendingRequestsStmt = db()->prepare("SELECT COUNT(*) FROM change_requests WHERE user_id = ? AND status = 'pending'");
$pendingRequestsStmt->execute([(int) $user['id']]);
$pendingRequests = (int) $pendingRequestsStmt->fetchColumn();
$recent = array_slice($donations, 0, 3);
$listed = (int) $user['available_toggle'] === 1;

$daysSince = null;
$daysLeft = 0;
$progress = 100;
if (!empty($user['last_donation_date'])) {
    $daysSince = (int) (new DateTimeImmutable('today'))->diff(new DateTimeImmutable((string) $user['last_donation_date']))->days;
    $daysLeft = max(0, $gap - $daysSince);
    $progress = (int) min(100, round($daysSince / $gap * 100));
}
$eligible = $daysLeft === 0;
$cardClass = !$listed ? 'is-off' : ($eligible ? '' : 'is-wait');
$initial = mb_strtoupper(mb_substr((string) $user['name'], 0, 1));
$photoUrl = !empty($user['photo']) ? app_url(ltrim((string) $user['photo'], '/')) : null;

$pageTitle = t('dashboard_title');
require __DIR__ . '/../../app/views/layout/header.php';
?>
<div class="page-head">
    <div class="profile-summary">
        <div class="avatar avatar-lg"><?php if ($photoUrl !== null): ?><img src="<?= e($photoUrl) ?>" alt="<?= e(t('photo_of') . ' ' . $user['name']) ?>"><?php else: ?><?= e($initial) ?><?php endif; ?></div>
        <div>
            <p class="muted" style="margin:0"><?= e(t('greeting')) ?></p>
            <h1><?= e($user['name']) ?></h1>
            <p style="margin:2px 0 0"><span class="blood-badge blood-badge-sm"><?= e($user['blood_group']) ?></span> <span class="muted"><?= e($user['phone']) ?></span></p>
        </div>
    </div>
</div>

<?php if ($user['status'] === 'pending'): ?><div class="alert alert-warning" role="status"><?= icon('hourglass', 18) ?><span><?= e(t('account_pending')) ?></span></div><?php endif; ?>
<?php if ($user['status'] === 'rejected'): ?><div class="alert alert-error" role="alert"><?= icon('x-circle', 18) ?><span><?= e(t('account_rejected')) ?><?= !empty($user['reject_reason']) ? ' — ' . e((string) $user['reject_reason']) : '' ?></span></div><?php endif; ?>
<?php if ($suspended): ?><div class="alert alert-error" role="alert"><?= icon('ban', 18) ?><span><?= e(t('account_suspended')) ?><?= $user['suspended_until'] ? ' (' . e(date_fmt((string) $user['suspended_until'])) . ')' : '' ?><?= !empty($user['suspend_reason']) ? ' — ' . e((string) $user['suspend_reason']) : '' ?></span></div><?php elseif ((int) $user['is_suspended'] === 1): ?><div class="alert alert-info" role="status"><?= icon('info', 18) ?><span><?= e(t('suspension_expired')) ?></span></div><?php endif; ?>

<section id="status-card" class="card status-card <?= e($cardClass) ?>" aria-labelledby="status-title">
    <div class="status-head">
        <div>
            <h2 id="status-title"><?= e($eligible ? t('can_donate_now') : t('can_donate_in', ['n' => num($daysLeft)])) ?></h2>
            <p class="muted" style="margin:2px 0 0" id="availability-status"><?= e($listed ? t('listed_as_available') : t('not_listed')) ?></p>
        </div>
        <span class="badge <?= $eligible ? 'badge-available' : 'badge-upcoming' ?>"><?= icon($eligible ? 'check-circle' : 'clock', 15) ?><?= e($eligible ? t('available') : t('available_in', ['n' => num($daysLeft)])) ?></span>
    </div>
    <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $progress ?>" aria-label="<?= e(t('donation_gap_progress')) ?>"><span style="width:<?= $progress ?>%"></span></div>
    <div class="progress-meta"><span><?= $daysSince === null ? e(t('no_donation_yet')) : e(t('last_donation_date') . ': ' . date_fmt((string) $user['last_donation_date'])) ?></span><span><?= e(num($gap)) ?> <?= e(t('days')) ?></span></div>
    <label class="switch-row" for="availability-toggle" style="margin-top:14px;border-color:var(--border)">
        <span class="switch-text"><span class="switch-label"><?= e(t('availability')) ?></span><span class="switch-state" id="availability-state"><?= e($listed ? t('on') : t('off')) ?></span></span>
        <button id="availability-toggle" class="switch" type="button" role="switch" aria-checked="<?= $listed ? 'true' : 'false' ?>" data-available="<?= $listed ? '1' : '0' ?>" aria-label="<?= e(t('availability')) ?>"></button>
    </label>
</section>

<section class="card donation-badges-card" aria-labelledby="donation-badges-title">
    <div class="card-head"><h2 id="donation-badges-title"><?= e(t('donation_badges_title')) ?></h2></div>
    <p class="donation-badges-headline"><?= e(t('donation_count_headline', ['n' => num($badgeSummary['count'])])) ?></p>
    <?php if ($badgeSummary['count'] === 0): ?><p class="muted"><?= e(t('donation_badges_motivation')) ?></p><?php endif; ?>
    <div class="donation-medals" role="list" aria-label="<?= e(t('donation_badges_title')) ?>">
        <?php foreach (['bronze', 'silver', 'gold'] as $medal): $earned = $badgeSummary['tier'] !== null && array_search($medal, ['bronze', 'silver', 'gold'], true) <= array_search($badgeSummary['tier'], ['bronze', 'silver', 'gold'], true); ?>
            <div class="donation-medal<?= $earned ? ' is-earned' : '' ?>" role="listitem" aria-label="<?= e($earned ? t('donation_badge_earned', ['badge' => t('badge_' . $medal)]) : t('donation_badge_locked', ['badge' => t('badge_' . $medal)])) ?>">
                <span class="donation-medal-icon"><?= $earned ? icon('award', 20) : icon('lock', 18) ?></span>
                <span><?= e(t('badge_' . $medal)) ?></span>
                <small><?= e(num(donor_badge_threshold($medal))) ?></small>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if ($badgeSummary['tier'] === 'gold'): ?>
        <p class="donation-badges-congrats"><?= icon('check-circle', 17) ?><?= e(t('donation_badges_gold')) ?></p>
    <?php elseif ($badgeSummary['next_tier'] !== null): ?>
        <div class="donation-badges-progress" role="progressbar" aria-valuemin="0" aria-valuemax="<?= e((string) donor_badge_threshold($badgeSummary['next_tier'])) ?>" aria-valuenow="<?= e((string) $badgeSummary['count']) ?>" aria-label="<?= e(t('donation_badges_next', ['n' => num($badgeSummary['remaining']), 'badge' => t('badge_' . $badgeSummary['next_tier'])])) ?>"><span style="width:<?= e((string) min(100, round($badgeSummary['count'] / donor_badge_threshold($badgeSummary['next_tier']) * 100))) ?>%"></span></div>
        <p class="donation-badges-next"><?= e(t('donation_badges_next', ['n' => num($badgeSummary['remaining']), 'badge' => t('badge_' . $badgeSummary['next_tier'])])) ?></p>
    <?php endif; ?>
</section>

<?php if ($workActive): ?>
<section class="card present-at-card" aria-labelledby="present-at-title">
    <h2 id="present-at-title"><?= e(t('present_at_title')) ?></h2>
    <div class="button-row" role="group" aria-label="<?= e(t('present_at_title')) ?>">
        <button type="button" class="button <?= ($user['present_at'] ?? 'home') === 'home' ? '' : 'button-secondary' ?>" data-present-at="home"><?= e(t('home_location')) ?></button>
        <button type="button" class="button <?= ($user['present_at'] ?? 'home') === 'work' ? '' : 'button-secondary' ?>" data-present-at="work"><?= e(t('work_location')) ?></button>
    </div>
</section>
<?php else: ?>
<section class="card"><p><?= e(t('work_location_prompt')) ?></p><a class="button button-secondary" href="<?= e(app_url('user/profile.php#work-location')) ?>"><?= e(t('add_work_location')) ?></a></section>
<?php endif; ?>

<div class="dashboard-grid">
    <section class="card" aria-labelledby="quick-title">
        <div class="card-head"><h2 id="quick-title"><?= e(t('account_actions')) ?></h2></div>
        <div class="account-stats">
            <div class="account-stat"><span class="account-stat-icon"><?= icon('droplet', 16) ?></span><strong><?= e(num($totalDonations)) ?></strong><span><?= e(t('total_donations_count')) ?></span></div>
            <div class="account-stat"><span class="account-stat-icon"><?= icon('clock', 16) ?></span><strong><?= $daysSince === null ? '—' : e(num($daysSince)) ?></strong><span><?= e(t('days_since_last')) ?></span></div>
        </div>
        <a class="button account-primary-action" href="<?= e(app_url('user/donations.php')) ?>"><?= icon('plus', 18) ?><?= e(t('add_donation')) ?></a>
        <nav class="account-action-list" aria-label="<?= e(t('account_actions')) ?>">
            <a class="account-action" href="<?= e(app_url('user/profile.php')) ?>"><span class="account-action-icon"><?= icon('pencil', 18) ?></span><span class="account-action-label"><?= e(t('edit_profile')) ?></span><span class="account-action-chevron"><?= icon('chevron-right', 18) ?></span></a>
            <a class="account-action" href="<?= e(app_url('user/requests.php')) ?>"><span class="account-action-icon"><?= icon('droplet', 18) ?></span><span class="account-action-label"><?= e(t('request_blood_group_change')) ?><?php if ($pendingRequests > 0): ?><span class="badge badge-pending"><?= e(num($pendingRequests)) ?></span><?php endif; ?></span><span class="account-action-chevron"><?= icon('chevron-right', 18) ?></span></a>
            <a class="account-action" href="<?= e(app_url('user/password.php')) ?>"><span class="account-action-icon"><?= icon('key', 18) ?></span><span class="account-action-label"><?= e(t('password_change_title')) ?></span><span class="account-action-chevron"><?= icon('chevron-right', 18) ?></span></a>
        </nav>
    </section>

    <section class="card" aria-labelledby="recent-title">
        <div class="card-head"><h2 id="recent-title"><?= e(t('recent_donations')) ?></h2><?php if ($totalDonations > 3): ?><a href="<?= e(app_url('user/donations.php')) ?>"><?= e(t('view_all')) ?></a><?php endif; ?></div>
        <?php if ($recent === []): ?>
            <p class="muted"><?= e(t('no_donations')) ?></p>
        <?php endif; ?>
        <?php foreach ($recent as $donation): $d = new DateTimeImmutable((string) $donation['donation_date']); ?>
            <article class="list-row">
                <div class="row-main">
                    <div class="date-chip"><div><b><?= e(num($d->format('j'))) ?></b><?= e(explode(',', t('months'))[(int) $d->format('n') - 1]) ?></div></div>
                    <div><strong><?= e(date_fmt($d)) ?></strong><p class="muted"><?= e((string) ($donation['place'] ?? '')) ?></p></div>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</div>

<script>
window.BLOODLINK_CSRF = <?= json_encode(csrf_token(), JSON_THROW_ON_ERROR) ?>;
window.BLOODLINK_DASHBOARD = <?= json_encode([
    'listed' => t('listed_as_available'),
    'hidden' => t('not_listed'),
    'on' => t('on'),
    'off' => t('off'),
    'failed' => t('update_failed'),
    'saved' => t('availability_saved'),
    'presentSaved' => t('present_at_saved'),
    'presentFailed' => t('present_at_failed'),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= e(app_url('assets/js/dashboard.js?v=3')) ?>" defer></script>
<?php require __DIR__ . '/../../app/views/layout/footer.php'; ?>
