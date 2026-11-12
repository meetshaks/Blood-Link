<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/lib/admin.php';

$pageTitle = t('nav_about');
$pageDescription = t('about_meta_description');
$metaDescription = t('about_meta_description');
$stats = public_statistics();
$minAge = max(1, (int) setting_value('min_age', '18'));
$maxAge = max($minAge, (int) setting_value('max_age', '60'));
$donationGap = max(1, (int) setting_value('donation_gap_days', '120'));
$phoneVisibility = setting_value('phone_visibility', 'public');
$loggedIn = session_get('uid') !== null;
$shareUrl = app_url('index.php');
$copyUrl = app_url('index.php');
$shareText = t('share_about_text') . ' ' . $shareUrl;
$faq = [
    [t('faq_phone_question'), $phoneVisibility === 'public' ? t('faq_phone_public') : t('faq_phone_login')],
    [t('faq_hide_question'), t('faq_hide_answer')],
    [t('faq_cost_question'), t('faq_cost_answer')],
    [t('faq_frequency_question'), t('faq_frequency_answer', ['days' => num($donationGap)])],
    [t('faq_change_question'), t('faq_change_answer')],
    [t('faq_password_question'), t('faq_password_answer')],
    [t('faq_safety_question'), t('faq_safety_answer')],
];
$faqSchema = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(
    static fn (array $item): array => ['@type' => 'Question', 'name' => $item[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item[1]]],
    $faq
)];
$support = support_contact();
require __DIR__ . '/../app/views/layout/header.php';
?>
<div class="page-head">
    <h1><?= e(t('about_title')) ?></h1>
    <p class="lead"><?= e(t('about_lead')) ?></p>
</div>

<section class="about-stats" aria-label="<?= e(t('about_stats_title')) ?>">
    <article class="card about-stat"><strong><?= e(num($stats['approved'])) ?></strong><span><?= e(t('stat_approved_donors')) ?></span></article>
    <article class="card about-stat"><strong><?= e(num($stats['available'])) ?></strong><span><?= e(t('stat_available_donors')) ?></span></article>
    <article class="card about-stat"><strong><?= e(num($stats['donations'])) ?></strong><span><?= e(t('stat_total_donations')) ?></span></article>
    <article class="card about-stat"><strong><?= e(num($stats['districts'])) ?></strong><span><?= e(t('stat_districts')) ?></span></article>
</section>

<section class="about-grid" aria-labelledby="about-values-title">
    <h2 id="about-values-title" class="sr-only"><?= e(t('about_values_title')) ?></h2>
    <article class="card"><h3><?= icon('search', 20) ?><?= e(t('about_find_title')) ?></h3><p class="muted"><?= e(t('about_find_text')) ?></p></article>
    <article class="card"><h3><?= icon('heart-pulse', 20) ?><?= e(t('about_donate_title')) ?></h3><p class="muted"><?= e(t('about_donate_text')) ?></p></article>
    <article class="card"><h3><?= icon('shield', 20) ?><?= e(t('about_trust_title')) ?></h3><p class="muted"><?= e(t('about_trust_text')) ?></p></article>
</section>

<section class="card" style="margin-top:20px" aria-labelledby="how-title">
    <h2 id="how-title"><?= e(t('how_it_works_title')) ?></h2>
    <div class="how-steps">
        <article class="how-step"><h3><span class="step-no"><?= e(num(1)) ?></span><?= icon('search', 20) ?><?= e(t('how_search_title')) ?></h3><p class="muted"><?= e(t('how_search_text')) ?></p></article>
        <article class="how-step"><h3><span class="step-no"><?= e(num(2)) ?></span><?= icon('phone', 20) ?><?= e(t('how_call_title')) ?></h3><p class="muted"><?= e(t('how_call_text')) ?></p></article>
        <article class="how-step"><h3><span class="step-no"><?= e(num(3)) ?></span><?= icon('droplet', 20) ?><?= e(t('how_blood_title')) ?></h3><p class="muted"><?= e(t('how_blood_text')) ?></p></article>
    </div>
</section>

<section style="margin-top:20px" aria-labelledby="eligibility-title">
    <div class="page-head"><h2 id="eligibility-title"><?= e(t('eligibility_title')) ?></h2><p><?= e(t('eligibility_intro', ['min' => num($minAge), 'max' => num($maxAge), 'days' => num($donationGap)])) ?></p></div>
    <div class="eligibility-grid">
        <article class="card"><h3><?= icon('check-circle', 20) ?><?= e(t('eligibility_general_title')) ?></h3><ul class="eligibility-list"><li><?= e(t('eligibility_health')) ?></li><li><?= e(t('eligibility_weight')) ?></li><li><?= e(t('eligibility_gap', ['days' => num($donationGap)])) ?></li></ul></article>
        <article class="card"><h3><?= icon('alert', 20) ?><?= e(t('eligibility_no_title')) ?></h3><ul class="eligibility-list"><li><?= e(t('not_donate_unwell')) ?></li><li><?= e(t('not_donate_pregnancy')) ?></li><li><?= e(t('not_donate_surgery')) ?></li><li><?= e(t('not_donate_medicine')) ?></li><li><?= e(t('not_donate_vaccine')) ?></li><li><?= e(t('not_donate_hemoglobin')) ?></li></ul></article>
    </div>
    <p class="alert alert-info" style="margin-top:16px"><?= icon('info', 18) ?><span><?= e(t('eligibility_final_note')) ?></span></p>
    <p class="button-row"><a class="button button-secondary" href="<?= e(app_url('register.php')) ?>"><?= icon('user-plus', 18) ?><?= e(t('register_btn')) ?></a></p>
</section>

<section class="card" style="margin-top:20px" aria-labelledby="faq-title">
    <h2 id="faq-title"><?= e(t('faq_title')) ?></h2>
    <div class="faq-list">
        <?php foreach ($faq as [$question, $answer]): ?><details><summary><?= e($question) ?></summary><p><?= e($answer) ?></p></details><?php endforeach; ?>
    </div>
</section>

<section class="cta-band" aria-labelledby="cta-title">
    <h2 id="cta-title"><?= e(t('cta_title')) ?></h2>
    <p><?= e(t('cta_text')) ?></p>
    <div class="button-row">
        <?php if ($loggedIn): ?><a class="button" href="<?= e(app_url('user/dashboard.php')) ?>"><?= icon('user-circle', 18) ?><?= e(t('dashboard_title')) ?></a>
        <?php else: ?><a class="button" href="<?= e(app_url('register.php')) ?>"><?= icon('user-plus', 18) ?><?= e(t('nav_register')) ?></a><?php endif; ?>
        <a class="button button-secondary" href="<?= e(app_url()) ?>"><?= icon('search', 18) ?><?= e(t('nav_search')) ?></a>
    </div>
</section>

<section class="share-card" aria-labelledby="share-title">
    <h2 id="share-title"><?= e(t('share_title')) ?></h2>
    <div class="share-actions">
        <a class="button button-secondary button-small" target="_blank" rel="noopener noreferrer" aria-label="<?= e(t('share_facebook')) ?>" href="<?= e('https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($shareUrl)) ?>"><?= e(t('facebook')) ?></a>
        <a class="button button-secondary button-small" target="_blank" rel="noopener noreferrer" aria-label="<?= e(t('share_whatsapp')) ?>" href="<?= e('https://wa.me/?text=' . rawurlencode($shareText)) ?>"><?= e(t('whatsapp')) ?></a>
        <button class="button button-secondary button-small" type="button" data-copy-url="<?= e($copyUrl) ?>"><?= icon('copy', 16) ?><?= e(t('copy_link')) ?></button>
    </div>
</section>
<p class="disclaimer"><?= icon('info', 18) ?><span><?= e(t('disclaimer')) ?></span></p>
<script type="application/ld+json"><?= json_encode($faqSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?></script>
<?php require __DIR__ . '/../app/views/layout/footer.php'; ?>
