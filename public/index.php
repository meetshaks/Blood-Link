<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/lib/admin.php';
header('Cache-Control: no-store');
$pageTitle = t('site_name') . ' · ' . t('org_tagline');
$pageDescription = t('search_subtitle');

$beforeMain = '<div class="page-hero"><div class="container"><div>'
    . '<h1>' . e(t('search_title')) . '</h1>'
    . '<p>' . e(t('search_subtitle')) . '</p>'
    . '</div><div><a class="button button-secondary" href="' . e(app_url('register.php')) . '">' . icon('heart-pulse', 18) . e(t('become_donor_btn')) . '</a></div></div></div>';

require __DIR__ . '/../app/views/layout/header.php';

$groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
$divisions = db()->query('SELECT id, name_bn, name_en FROM divisions ORDER BY name_bn')->fetchAll();
$stats = public_statistics();
$compatibility = [
    'A+' => ['A+', 'AB+'], 'A-' => ['A+', 'A-', 'AB+', 'AB-'],
    'B+' => ['B+', 'AB+'], 'B-' => ['B+', 'B-', 'AB+', 'AB-'],
    'AB+' => ['AB+'], 'AB-' => ['AB+', 'AB-'],
    'O+' => ['O+', 'A+', 'B+', 'AB+'], 'O-' => $groups,
];
$spinner = '<span class="load-spin" hidden>' . icon('spinner', 14) . '</span>';
?>
<div class="search-layout">
    <aside class="card filter-card" aria-labelledby="search-heading">
        <h2 id="search-heading" class="sr-only"><?= e(t('search_title')) ?></h2>
        <p class="field-hint"><?= e(t('search_location_help')) ?></p>
        <form id="search-form" action="<?= e(app_url('api/search.php')) ?>" method="get" novalidate>
            <div class="filter-block">
                <button class="button button-secondary button-block" type="button" id="use-location"><?= icon('locate', 18) ?><span><?= e(t('use_my_location')) ?></span></button>
                <p class="field-hint"><?= e(t('location_privacy_hint')) ?></p>
            </div>
            <div class="filter-block">
                <fieldset class="field">
                    <legend><?= e(t('blood_group')) ?> <span class="req" style="color:var(--red-600)">*</span></legend>
                    <div class="chip-grid" role="radiogroup" aria-label="<?= e(t('blood_group')) ?>">
                        <?php foreach ($groups as $i => $group): ?>
                            <label class="chip"><input type="radio" name="bg" value="<?= e($group) ?>" <?= $i === 0 ? 'required' : '' ?>><span><?= e($group) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                    <p class="field-error" id="bg-error" role="alert"><?= icon('alert', 14) ?><span><?= e(t('select_blood_group')) ?></span></p>
                </fieldset>
            </div>

            <div class="filter-block">
                <div class="field"><label for="division"><?= e(t('division')) ?></label><select id="division" name="division"><option value="">—</option><?php foreach ($divisions as $row): ?><option value="<?= (int) $row['id'] ?>"><?= e(current_lang() === 'bn' ? $row['name_bn'] : $row['name_en']) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="district" class="label-spin"><?= e(t('district')) ?> <?= $spinner ?></label><select id="district" name="district" disabled><option value="">—</option></select></div>

                <fieldset id="area-mode" class="field address-mode-field" aria-live="polite" hidden>
                    <legend><?= e(t('addr_mode_label')) ?></legend>
                    <div class="radio-group address-mode-group">
                        <label class="radio-pill"><input type="radio" name="address_mode" value="city"><span><?= e(t('addr_mode_inside')) ?></span></label>
                        <label class="radio-pill"><input type="radio" name="address_mode" value="upazila"><span><?= e(t('addr_mode_outside')) ?></span></label>
                    </div>
                    <p class="field-hint address-mode-hint" hidden><?= e(t('addr_mode_hint')) ?></p>
                </fieldset>

                <div id="city-wrap" class="field" hidden><label for="city-corporation"><?= e(t('city_corporation')) ?></label><select id="city-corporation" name="city_corporation" disabled><option value="">—</option></select></div>
                <div id="city-area-wrap" class="field" hidden><label for="city-corporation-area"><?= e(t('city_corporation_area')) ?></label><select id="city-corporation-area" name="city_corporation_area" disabled><option value="">—</option></select></div>
                <div id="upazila-wrap">
                    <div class="field"><label for="upazila" class="label-spin"><?= e(t('upazila')) ?> <?= $spinner ?></label><select id="upazila" name="upazila" disabled><option value="">—</option></select></div>
                    <div class="field"><label for="union" class="label-spin"><?= e(t('union_or_pourashava')) ?> <?= $spinner ?></label><select id="union" name="union" disabled><option value="">—</option></select></div>
                </div>
            </div>

            <div class="filter-block">
                <button class="button button-block" type="submit" id="search-submit"><?= icon('search', 18) ?><span><?= e(t('search_btn')) ?></span></button>
            </div>
            <div class="filter-block">
                <button class="button button-ghost button-block button-small" type="button" id="reset-filters" style="margin-top:6px;color:var(--muted)"><?= icon('rotate', 16) ?><?= e(t('reset_filters')) ?></button>
            </div>
        </form>
    </aside>

    <section id="search-results" aria-labelledby="results-heading" aria-live="polite">
        <div class="card result-bar">
            <h2 id="results-heading" tabindex="-1"><?= e(t('results')) ?></h2>
            <div class="legend">
                <div class="availability-filters" role="group" aria-label="<?= e(t('availability_filter')) ?>">
                    <button type="button" class="availability-filter is-active" data-availability="all"><?= e(t('all')) ?></button>
                    <button type="button" class="availability-filter" data-availability="available"><?= e(t('available')) ?></button>
                    <button type="button" class="availability-filter" data-availability="resting"><?= e(t('legend_upcoming')) ?></button>
                </div>
                <button class="button button-small button-secondary" type="button" id="share-search"><?= icon('share', 16) ?><?= e(t('share_search')) ?></button>
            </div>
        </div>
        <div id="results-list" aria-busy="false">
            <div id="welcome-panel" class="welcome-panel">
                <section class="welcome-stats" aria-label="<?= e(t('welcome_stats_title')) ?>">
                    <article class="welcome-stat"><strong><?= e(num($stats['approved'])) ?></strong><span><?= e(t('stat_approved_donors')) ?></span></article>
                    <article class="welcome-stat"><strong><?= e(num($stats['available'])) ?></strong><span><?= e(t('stat_available_donors')) ?></span></article>
                    <article class="welcome-stat"><strong><?= e(num($stats['districts'])) ?></strong><span><?= e(t('stat_districts')) ?></span></article>
                </section>
                <div class="welcome-grid">
                    <section class="card welcome-card" aria-labelledby="welcome-how-title">
                        <h2 id="welcome-how-title"><?= e(t('how_it_works_title')) ?></h2>
                        <div class="welcome-steps">
                            <article><span class="step-no"><?= e(num(1)) ?></span><?= icon('droplet', 20) ?><div><strong><?= e(t('welcome_step_group')) ?></strong><p><?= e(t('welcome_step_group_text')) ?></p></div></article>
                            <article><span class="step-no"><?= e(num(2)) ?></span><?= icon('map-pin', 20) ?><div><strong><?= e(t('welcome_step_area')) ?></strong><p><?= e(t('welcome_step_area_text')) ?></p></div></article>
                            <article><span class="step-no"><?= e(num(3)) ?></span><?= icon('phone', 20) ?><div><strong><?= e(t('welcome_step_call')) ?></strong><p><?= e(t('welcome_step_call_text')) ?></p></div></article>
                        </div>
                        <div class="welcome-tip"><?= icon('info', 16) ?><span><?= e(t('welcome_tip')) ?></span></div>
                    </section>
                    <section class="card welcome-card compatibility-card" aria-labelledby="compatibility-title">
                        <h2 id="compatibility-title"><?= e(t('compatibility_title')) ?></h2>
                        <p class="muted compatibility-note"><?= e(t('compatibility_note')) ?></p>
                        <div class="compatibility-list" role="list">
                            <?php foreach ($compatibility as $from => $to): ?><div class="compatibility-row" role="listitem"><button type="button" class="compatibility-badge compatibility-source" data-select-blood="<?= e($from) ?>" aria-label="<?= e($from) ?>"><?= e($from) ?></button><span aria-hidden="true">→</span><span class="compatibility-targets"><?php foreach ($to as $target): ?><span class="compatibility-badge"><?= e($target) ?></span><?php endforeach; ?></span></div><?php endforeach; ?>
                        </div>
                    </section>
                </div>
                <div class="welcome-cta"><strong><?= e(t('welcome_cta')) ?></strong><?php if ($loggedIn): ?><a class="button button-secondary button-small" href="<?= e(app_url('user/dashboard.php')) ?>"><?= e(t('dashboard_title')) ?></a><?php else: ?><a class="button button-small" href="<?= e(app_url('register.php')) ?>"><?= e(t('register_btn')) ?></a><?php endif; ?></div>
            </div>
        </div>
        <nav id="pagination" class="pagination" aria-label="<?= e(t('pagination')) ?>"></nav>
    </section>
</div>
<p class="disclaimer"><?= icon('info', 18) ?><span><?= e(t('disclaimer')) ?></span></p>
<script>
window.BLOODLINK = <?= json_encode(['baseUrl' => rtrim((string) bloodlink_config()['base_url'], '/'), 'lang' => current_lang(), 'months' => explode(',', t('months')), 'labels' => [
    'loading' => t('loading'), 'noResults' => t('no_results'), 'noResultsHint' => t('no_results_hint'), 'error' => t('search_error'),
    'available' => t('available'), 'availableIn' => t('available_in'), 'hidden' => t('number_hidden'),
    'donationCount' => t('donation_count_line'), 'badgeBronze' => t('badge_bronze'), 'badgeSilver' => t('badge_silver'), 'badgeGold' => t('badge_gold'),
    'callNow' => t('call_now'), 'copy' => t('copy_number'), 'copied' => t('copied'), 'locationNotAvailable' => t('location_not_available'),
    'previous' => t('previous'), 'next' => t('next'), 'found' => t('results_count'), 'page' => t('page_of'),
    'lastDonation' => t('last_donation_date'), 'never' => t('no_donation_yet'), 'years' => t('years_short'),
    'reset' => t('reset_filters'), 'pourashava' => t('pourashava'),
    'male' => t('male'), 'female' => t('female'), 'other' => t('other'), 'photoOf' => t('photo_of'), 'ward' => t('ward'),
    'promptTitle' => t('search_prompt_title'), 'promptText' => t('search_prompt_text'),
    'locationDenied' => t('location_denied'), 'locationUnavailable' => t('location_unavailable'),
    'locationTimeout' => t('location_timeout'), 'outsideBangladesh' => t('outside_bangladesh'),
    'locationSet' => t('location_set'), 'linkCopied' => t('link_copied'), 'useLocation' => t('use_my_location'),
    'home' => t('home_location'), 'work' => t('work_location'), 'workShort' => t('work_short'),
    'homeShort' => t('home_short'), 'locationUpdated' => t('work_location_updated'),
    'locWork' => t('loc_work'), 'locHome' => t('loc_home'), 'matchedArea' => t('matched_area'),
]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= e(app_url('assets/js/search.js?v=14')) ?>" defer></script>
<?php require __DIR__ . '/../app/views/layout/footer.php'; ?>
