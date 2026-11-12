<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';
no_store_headers();
require_once __DIR__ . '/../../app/lib/admin.php';
if (current_admin() !== null) redirect('/admin/dashboard.php');
$error = null;
if (is_post()) {
    if (!csrf_validate()) {
        $error = 'Invalid form security token.';
    } elseif (!attempt_admin_login((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''))['ok']) {
        $error = t('invalid_admin_login');
    } else {
        redirect('/admin/dashboard.php');
    }
}
$pageTitle = t('admin_login');
require __DIR__ . '/../../app/views/layout/header.php';
?>
<div class="admin-login-wrap">
<section class="card">
    <div class="card-title-center"><div class="card-icon"><?= icon('shield', 26) ?></div><h1><?= e(t('admin_login')) ?></h1><p><?= e(t('admin_login_subtitle')) ?></p></div>
    <?php if ($error !== null): ?><div class="alert alert-error" role="alert"><?= icon('alert', 18) ?><span><?= e($error) ?></span></div><?php endif; ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="form-grid" style="grid-template-columns:1fr">
            <div class="field"><label for="username"><?= e(t('username')) ?></label><input id="username" name="username" type="text" autocomplete="username" required></div>
            <div class="field"><label for="password"><?= e(t('password')) ?></label><div class="input-with-action"><input id="password" name="password" type="password" autocomplete="current-password" required><button type="button" class="input-action" data-toggle-password="password" aria-label="<?= e(t('show_password')) ?>"><span data-icon-show><?= icon('eye', 20) ?></span><span data-icon-hide><?= icon('eye-off', 20) ?></span></button></div></div>
        </div>
        <button class="button button-block" type="submit" style="margin-top:20px"><?= icon('log-in', 18) ?><span><?= e(t('admin_login')) ?></span></button>
    </form>
</section>
</div>
<?php require __DIR__ . '/../../app/views/layout/footer.php'; ?>
