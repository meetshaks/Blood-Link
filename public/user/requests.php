<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';
require __DIR__ . '/../../app/lib/settings.php';
$user = require_user();
$error = null;
if (is_post()) {
    try {
        if (!csrf_validate()) throw new InvalidArgumentException('Invalid form security token.');
        create_change_request((int) $user['id'], 'blood_group', (string) ($_POST['new_value'] ?? ''));
        flash('success', t('request_submitted'));
        redirect('/user/requests.php');
    } catch (Throwable $e) { $error = $e->getMessage(); }
}
$stmt = db()->prepare('SELECT field_name, old_value, new_value, status, created_at FROM change_requests WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([(int) $user['id']]);
$requests = $stmt->fetchAll();
$pageTitle = t('change_requests');
require __DIR__ . '/../../app/views/layout/header.php';
$badge = static fn (string $status): string => match ($status) { 'approved' => 'badge-available', 'rejected' => 'badge-danger', default => 'badge-pending' };
?>
<div class="page-head" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;max-width:860px;margin-inline:auto">
    <div><h1><?= e(t('change_requests')) ?></h1><p><?= e(t('change_requests_help')) ?></p></div>
    <a class="button button-ghost" href="<?= e(app_url('user/dashboard.php')) ?>"><?= icon('arrow-left', 18) ?><?= e(t('back')) ?></a>
</div>
<section class="card form-card">
    <div class="card-head"><h2><?= e(t('submit_request')) ?></h2></div>
    <?php if ($error !== null): ?><div class="alert alert-error" role="alert"><?= icon('alert', 18) ?><span><?= e($error) ?></span></div><?php endif; ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field"><label for="field_name"><?= e(t('field')) ?></label><select id="field_name" name="field_name"><option value="blood_group"><?= e(t('blood_group')) ?></option></select></div>
            <div class="field"><label for="new_value"><?= e(t('new_value')) ?></label><input id="new_value" name="new_value" required></div>
        </div>
        <div class="button-row"><button class="button" type="submit"><?= icon('check', 18) ?><span><?= e(t('submit_request')) ?></span></button></div>
    </form>
</section>
<section class="card donation-history">
    <div class="card-head"><h2><?= e(t('request_history')) ?></h2></div>
    <?php if ($requests === []): ?><div class="empty-state"><div class="card-icon"><?= icon('inbox', 26) ?></div><p><?= e(t('no_requests_yet')) ?></p></div><?php endif; ?>
    <?php foreach ($requests as $request): ?>
        <article class="list-row">
            <div><strong><?= e(t($request['field_name'])) ?></strong><p class="muted"><?= e((string) $request['old_value']) ?> → <?= e((string) $request['new_value']) ?> · <?= e(date_fmt((string) $request['created_at'])) ?></p></div>
            <span class="badge <?= e($badge((string) $request['status'])) ?>"><?= e(t((string) $request['status'])) ?></span>
        </article>
    <?php endforeach; ?>
</section>
<?php require __DIR__ . '/../../app/views/layout/footer.php'; ?>
