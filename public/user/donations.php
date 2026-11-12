<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/donations.php';

$user = require_user();
$donations = list_user_donations((int) $user['id']);
$pageTitle = t('donation_history');
require __DIR__ . '/../../app/views/layout/header.php';
?>
<div class="page-head" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;max-width:860px;margin-inline:auto">
    <div><h1><?= e(t('donation_history')) ?></h1><p><?= e(t('donation_history_subtitle')) ?></p></div>
    <a class="button button-ghost" href="<?= e(app_url('user/dashboard.php')) ?>"><?= icon('arrow-left', 18) ?><?= e(t('back')) ?></a>
</div>
<section class="card form-card" aria-labelledby="add-title">
    <div class="card-head"><h2 id="add-title"><?= icon('plus', 20) ?> <?= e(t('add_donation')) ?></h2></div>
    <p class="muted"><?= e(t('donation_history_help')) ?></p>
    <form id="donation-form" class="form-grid" novalidate>
        <input type="hidden" name="action" value="add">
        <div class="field"><label for="donation_date"><?= e(t('donation_date')) ?> <span class="req">*</span></label><input id="donation_date" name="donation_date" type="date" max="<?= e(date('Y-m-d')) ?>" required></div>
        <div class="field"><label for="place"><?= e(t('donation_place')) ?></label><input id="place" name="place" type="text" maxlength="200"></div>
        <div class="field span-2"><label for="note"><?= e(t('donation_note')) ?></label><input id="note" name="note" type="text" maxlength="500"></div>
        <div class="span-2"><button class="button" type="submit"><?= icon('plus', 18) ?><span><?= e(t('add_donation')) ?></span></button></div>
    </form>
    <div id="donation-message" class="alert" role="status" hidden></div>
</section>
<section class="card donation-history" aria-labelledby="list-title">
    <div class="card-head"><h2 id="list-title"><?= e(t('donation_history')) ?></h2><span class="badge badge-unavailable" id="donation-count"><?= e(num(count($donations))) ?></span></div>
    <div id="donation-list">
        <?php if ($donations === []): ?><div class="empty-state"><div class="card-icon"><?= icon('droplet', 26) ?></div><p><?= e(t('no_donations')) ?></p></div><?php endif; ?>
        <?php foreach ($donations as $donation): $d = new DateTimeImmutable((string) $donation['donation_date']); ?>
            <article class="donation-row" data-id="<?= (int) $donation['id'] ?>">
                <div class="row-main">
                    <div class="date-chip"><div><b><?= e(num($d->format('j'))) ?></b><?= e(explode(',', t('months'))[(int) $d->format('n') - 1]) ?></div></div>
                    <div><strong><?= e(date_fmt($d)) ?></strong><p class="muted"><?= e((string) ($donation['place'] ?? '')) ?><?= $donation['note'] !== null && $donation['note'] !== '' ? ' · ' . e((string) $donation['note']) : '' ?></p></div>
                </div>
                <button class="button button-small button-secondary donation-delete" type="button" aria-label="<?= e(t('delete') . ' ' . date_fmt($d)) ?>"><?= icon('trash', 16) ?><span><?= e(t('delete')) ?></span></button>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<script>
window.BLOODLINK_CSRF = <?= json_encode(csrf_token(), JSON_THROW_ON_ERROR) ?>;
window.BLOODLINK_DONATIONS = <?= json_encode([
    'lang' => current_lang(), 'months' => explode(',', t('months')),
    'noDonations' => t('no_donations'), 'deleteConfirm' => t('delete_donation_confirm'),
    'added' => t('donation_added'), 'deleted' => t('donation_deleted'),
    'failed' => t('update_failed'), 'delete' => t('delete'),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= e(app_url('assets/js/donations.js?v=3')) ?>" defer></script>
<?php require __DIR__ . '/../../app/views/layout/footer.php'; ?>
