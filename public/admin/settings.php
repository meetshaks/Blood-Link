<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/admin.php';
require_once __DIR__ . '/../../app/lib/settings.php';
$admin = require_admin('super');
$settings = all_settings();
$error = null;
if (is_post()) {
    if (!csrf_validate()) $error = 'Invalid form security token.';
    else try { update_settings((int) $admin['id'], $_POST); $settings = all_settings(); flash('success', t('settings_saved')); } catch (Throwable $e) { $error = $e instanceof InvalidArgumentException ? $e->getMessage() : t('settings_failed'); }
}
$pageTitle = t('system_settings');
$adminNav = 'settings';
require __DIR__ . '/../../app/views/layout/header.php';
$cur = static fn (string $k, string $d = ''): string => (string) ($settings[$k] ?? $d);
?>
<div class="admin-heading"><h1><?= e(t('system_settings')) ?></h1></div>
<section class="card form-card" style="max-width:none">
<?php if ($error !== null): ?><div class="alert alert-error" role="alert"><?= icon('alert', 18) ?><span><?= e($error) ?></span></div><?php endif; ?>
<form method="post" novalidate><?= csrf_field() ?>
    <div class="settings-grid">
        <fieldset class="field"><legend><?= e(t('approval_mode')) ?></legend>
            <div class="radio-group">
                <label class="radio-pill"><input type="radio" name="approval_mode" value="manual" <?= $cur('approval_mode', 'manual') === 'manual' ? 'checked' : '' ?>><span>Manual</span></label>
                <label class="radio-pill"><input type="radio" name="approval_mode" value="auto" <?= $cur('approval_mode') === 'auto' ? 'checked' : '' ?>><span>Auto</span></label>
            </div>
            <p class="field-hint"><?= e(t('approval_mode_help')) ?></p>
        </fieldset>
        <fieldset class="field"><legend><?= e(t('phone_visibility')) ?></legend>
            <div class="radio-group">
                <label class="radio-pill"><input type="radio" name="phone_visibility" value="public" <?= $cur('phone_visibility', 'public') === 'public' ? 'checked' : '' ?>><span><?= e(t('visibility_public')) ?></span></label>
                <label class="radio-pill"><input type="radio" name="phone_visibility" value="login" <?= $cur('phone_visibility') === 'login' ? 'checked' : '' ?>><span><?= e(t('visibility_login')) ?></span></label>
            </div>
        </fieldset>
        <div class="field"><label for="donation_gap_days"><?= e(t('donation_gap_days')) ?></label><input id="donation_gap_days" name="donation_gap_days" type="number" min="1" max="1000" value="<?= e($cur('donation_gap_days', '120')) ?>"></div>
        <div class="field"><label for="default_lang"><?= e(t('default_language')) ?></label><select id="default_lang" name="default_lang"><option value="bn" <?= $cur('default_lang', 'bn') === 'bn' ? 'selected' : '' ?>>বাংলা</option><option value="en" <?= $cur('default_lang') === 'en' ? 'selected' : '' ?>>English</option></select></div>
        <div class="field"><label for="min_age"><?= e(t('min_age')) ?></label><input id="min_age" name="min_age" type="number" min="1" max="100" value="<?= e($cur('min_age', '18')) ?>"></div>
        <div class="field"><label for="max_age"><?= e(t('max_age')) ?></label><input id="max_age" name="max_age" type="number" min="1" max="120" value="<?= e($cur('max_age', '60')) ?>"></div>
        <div class="field"><label for="support_phone"><?= e(t('support_phone')) ?></label><input id="support_phone" name="support_phone" value="<?= e($cur('support_phone')) ?>"></div>
        <div class="field"><label for="support_email"><?= e(t('support_email')) ?></label><input id="support_email" name="support_email" type="email" value="<?= e($cur('support_email')) ?>"></div>
        <div class="field"><label for="support_hours"><?= e(t('support_hours')) ?></label><input id="support_hours" name="support_hours" maxlength="100" value="<?= e($cur('support_hours')) ?>"></div>
    </div>
    <div class="button-row"><button class="button" type="submit"><?= icon('check', 18) ?><span><?= e(t('save_settings')) ?></span></button></div>
</form></section>
<?php require __DIR__ . '/../../app/views/layout/footer.php'; ?>
