<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
no_store_headers();

$error = null;
if (is_post()) {
    if (!csrf_validate()) {
        $error = 'Invalid form security token.';
    } else {
        $result = attempt_user_login((string) ($_POST['phone'] ?? ''), (string) ($_POST['password'] ?? ''));
        if (!$result['ok']) {
            $error = t('invalid_login');
        } else {
            $user = $result['user'];
            if ($user['status'] === 'pending') {
                destroy_session();
                flash('info', t('account_pending'));
            } elseif ($user['status'] === 'rejected') {
                destroy_session();
                flash('error', t('account_rejected'));
            } elseif ((int) $user['must_change_password'] === 1) {
                redirect('/user/password.php');
            } else {
                redirect('/user/dashboard.php');
            }
        }
    }
}

$pageTitle = t('login_title');
$metaDescription = t('login_subtitle');
require __DIR__ . '/../app/views/layout/header.php';
?>
<section class="card center-card">
    <div class="card-title-center">
        <div class="card-icon"><?= icon('lock', 26) ?></div>
        <h1><?= e(t('login_title')) ?></h1>
        <p><?= e(t('login_subtitle')) ?></p>
    </div>
    <?php if ($error !== null): ?><div class="alert alert-error" role="alert"><?= icon('alert', 18) ?><span><?= e($error) ?></span></div><?php endif; ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="form-grid" style="grid-template-columns:1fr">
            <div class="field"><label for="phone"><?= e(t('phone')) ?></label><input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="username" placeholder="01XXXXXXXXX" required></div>
            <div class="field">
                <label for="password"><?= e(t('password')) ?></label>
                <div class="input-with-action">
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                    <button type="button" class="input-action" data-toggle-password="password" aria-label="<?= e(t('show_password')) ?>"><span data-icon-show><?= icon('eye', 20) ?></span><span data-icon-hide><?= icon('eye-off', 20) ?></span></button>
                </div>
            </div>
        </div>
        <button class="button button-block" type="submit" style="margin-top:20px"><?= icon('log-in', 18) ?><span><?= e(t('login_btn')) ?></span></button>
    </form>
    <details id="recovery" class="forgot" style="margin-top:16px">
        <summary style="cursor:pointer;color:var(--red-700);font-weight:600"><?= e(t('forgot_password')) ?></summary>
        <?php $support = support_contact(); ?>
        <div class="recovery-guide"><p><?= e(t('account_help_intro')) ?></p><ol class="account-help-steps"><li><?= e(t('recovery_step_1')) ?></li><li><?= e(t('recovery_step_2')) ?></li><li><?= e(t('recovery_step_3')) ?></li></ol><p><?= e(t('account_help_security')) ?></p><p><?= e(t('recovery_phone_note')) ?></p>
        <?php if ($support['phone_href'] === '' && $support['email_href'] === '' && $support['hours'] === ''): ?><p class="muted"><?= e(t('support_contact_soon')) ?></p><?php else: ?><div class="support-card"><?php require __DIR__ . '/../app/views/partials/support_contact.php'; ?></div><?php endif; ?></div>
    </details>
    <p class="muted" style="margin:16px 0 0;text-align:center"><?= e(t('no_account')) ?> <a href="<?= e(app_url('register.php')) ?>"><?= e(t('nav_register')) ?></a></p>
</section>
<?php require __DIR__ . '/../app/views/layout/footer.php'; ?>
