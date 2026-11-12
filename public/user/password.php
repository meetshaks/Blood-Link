<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';
$user = require_user(true);
$forcedPasswordChange = (int) $user['must_change_password'] === 1;
$error = null;
if (is_post()) {
    if (!csrf_validate()) {
        $error = 'Invalid form security token.';
    } elseif ((string) ($_POST['new_password'] ?? '') !== (string) ($_POST['new_password_confirmation'] ?? '')) {
        $error = 'New passwords do not match.';
    } elseif (((int) $user['must_change_password'] === 1 && !complete_forced_password_change((int) $user['id'], (string) ($_POST['new_password'] ?? ''))) || ((int) $user['must_change_password'] !== 1 && !change_user_password((int) $user['id'], (string) ($_POST['current_password'] ?? ''), (string) ($_POST['new_password'] ?? '')))) {
        $error = 'Current password is incorrect or the new password is too short.';
    } else {
        flash('success', t('password_changed'));
        redirect('/user/dashboard.php');
    }
}
$pageTitle = $forcedPasswordChange ? t('forced_password_title') : t('password_change_title');
require __DIR__ . '/../../app/views/layout/header.php';
?>
<section class="card center-card">
    <div class="card-title-center">
        <div class="card-icon"><?= icon('key', 26) ?></div>
        <h1><?= e($forcedPasswordChange ? t('forced_password_title') : t('password_change_title')) ?></h1>
        <p><?= e($forcedPasswordChange ? t('forced_password_help') : t('password_change_help')) ?></p>
    </div>
    <?php if ($error !== null): ?><div class="alert alert-error" role="alert"><?= icon('alert', 18) ?><span><?= e($error) ?></span></div><?php endif; ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="form-grid" style="grid-template-columns:1fr">
            <?php if (!$forcedPasswordChange): ?>
            <div class="field"><label for="current_password"><?= e(t('current_password')) ?></label><div class="input-with-action"><input id="current_password" name="current_password" type="password" autocomplete="current-password" required><button type="button" class="input-action" data-toggle-password="current_password" aria-label="<?= e(t('show_password')) ?>"><span data-icon-show><?= icon('eye', 20) ?></span><span data-icon-hide><?= icon('eye-off', 20) ?></span></button></div></div>
            <?php endif; ?>
            <div class="field"><label for="new_password"><?= e(t('new_password')) ?></label><div class="input-with-action"><input id="new_password" name="new_password" type="password" autocomplete="new-password" minlength="8" data-validate="password" required><button type="button" class="input-action" data-toggle-password="new_password" aria-label="<?= e(t('show_password')) ?>"><span data-icon-show><?= icon('eye', 20) ?></span><span data-icon-hide><?= icon('eye-off', 20) ?></span></button></div><p class="field-hint"><?= e(t('password_hint')) ?></p></div>
            <div class="field"><label for="new_password_confirmation"><?= e(t('confirm_password')) ?></label><div class="input-with-action"><input id="new_password_confirmation" name="new_password_confirmation" type="password" autocomplete="new-password" data-validate="match" data-matches="new_password" required><button type="button" class="input-action" data-toggle-password="new_password_confirmation" aria-label="<?= e(t('show_password')) ?>"><span data-icon-show><?= icon('eye', 20) ?></span><span data-icon-hide><?= icon('eye-off', 20) ?></span></button></div></div>
        </div>
        <button class="button button-block" type="submit" style="margin-top:20px"><?= icon('check', 18) ?><span><?= e(t('change_password_btn')) ?></span></button>
    </form>
</section>
<?php require __DIR__ . '/../../app/views/layout/footer.php'; ?>
