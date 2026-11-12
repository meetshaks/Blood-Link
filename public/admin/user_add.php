<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/admin.php';
$admin = require_admin();
$errors = [];
$password = null;
$pdo = db();
$divisions = $pdo->query('SELECT id,name_bn,name_en FROM divisions ORDER BY name_bn')->fetchAll();
$districts = get_districts_with_area_flags($pdo);
$upazilas = $pdo->query('SELECT id,district_id,name_bn,name_en FROM upazilas ORDER BY name_bn')->fetchAll();
$unions = $pdo->query("SELECT id,upazila_id,type,name_bn,name_en FROM unions ORDER BY type,name_bn")->fetchAll();
$cities = $pdo->query('SELECT id,district_id,name_bn,name_en FROM city_corporations ORDER BY name_bn')->fetchAll();
if (is_post()) {
    if (!csrf_validate()) $errors['_form'] = 'Invalid form security token.';
    else try { $password = admin_create_user((int) $admin['id'], $_POST); } catch (InvalidArgumentException $e) { $decoded = json_decode($e->getMessage(), true); $errors = is_array($decoded) ? $decoded : ['_form' => $e->getMessage()]; } catch (Throwable $e) { app_log('Admin create user failed', 'ERROR', ['exception' => $e->getMessage()]); $errors['_form'] = t('admin_action_failed'); }
}
$pageTitle = t('add_donor');
$adminNav = 'add';
require __DIR__ . '/../../app/views/layout/header.php';
$err = static fn (string $key): string => isset($errors[$key]) ? '<p class="field-error" role="alert" style="display:flex">' . icon('alert', 14) . '<span>' . e((string) $errors[$key]) . '</span></p>' : '';
$cls = static fn (string $key): string => isset($errors[$key]) ? ' has-error' : '';
$old = $_POST;
$val = static fn (string $key): string => e((string) ($old[$key] ?? ''));
$lang = current_lang();
$nm = static fn (array $r): string => $lang === 'bn' ? (string) $r['name_bn'] : (string) $r['name_en'];
?>
<div class="admin-heading"><h1><?= e(t('add_donor')) ?></h1></div>
<section class="card" style="max-width:860px">
<?php if ($password !== null): ?>
    <div class="alert alert-success" role="status"><?= icon('check-circle', 18) ?><span><?= e(t('donor_created')) ?></span></div>
    <p><?= e(t('temporary_password_note')) ?></p>
    <div class="temp-password"><span class="muted"><?= e(t('temporary_password')) ?></span><code id="temp-pass"><?= e($password) ?></code><button type="button" class="button button-small button-secondary" data-copy="#temp-pass"><?= icon('copy', 16) ?><?= e(t('copy')) ?></button></div>
    <div class="button-row"><a class="button" href="<?= e(app_url('admin/users.php')) ?>"><?= e(t('back_to_donors')) ?></a><a class="button button-secondary" href="<?= e(app_url('admin/user_add.php')) ?>"><?= icon('plus', 18) ?><?= e(t('add_another')) ?></a></div>
<?php else: ?>
    <?php if ($errors !== []): ?><div class="alert alert-error" role="alert"><?= icon('alert', 18) ?><span><?= e((string) ($errors['_form'] ?? reset($errors))) ?></span></div><?php endif; ?>
    <form method="post" novalidate data-address-prefix=""><?= csrf_field() ?>
        <div class="form-grid">
            <div class="field<?= $cls('name') ?>"><label for="name"><?= e(t('name')) ?> <span class="req">*</span></label><input id="name" name="name" value="<?= $val('name') ?>" required><?= $err('name') ?></div>
            <div class="field<?= $cls('phone') ?>"><label for="phone"><?= e(t('phone')) ?> <span class="req">*</span></label><input id="phone" name="phone" type="tel" inputmode="tel" value="<?= $val('phone') ?>" required><?= $err('phone') ?></div>
            <fieldset class="field span-2<?= $cls('blood_group') ?>"><legend><?= e(t('blood_group')) ?> <span class="req" style="color:var(--red-600)">*</span></legend>
                <div class="chip-grid admin-blood-group-grid"><?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $g): ?><label class="chip"><input type="radio" name="blood_group" value="<?= e($g) ?>" <?= ($old['blood_group'] ?? '') === $g ? 'checked' : '' ?> required><span><?= e($g) ?></span></label><?php endforeach; ?></div><?= $err('blood_group') ?></fieldset>
            <div class="field<?= $cls('date_of_birth') ?>"><label for="date_of_birth"><?= e(t('date_of_birth')) ?> <span class="req">*</span></label><input id="date_of_birth" name="date_of_birth" type="date" value="<?= $val('date_of_birth') ?>" required><?= $err('date_of_birth') ?></div>
            <fieldset class="field<?= $cls('gender') ?>"><legend><?= e(t('gender')) ?></legend><div class="radio-group"><?php foreach (['male','female','other'] as $g): ?><label class="radio-pill"><input type="radio" name="gender" value="<?= $g ?>" <?= (($old['gender'] ?? 'male') === $g) ? 'checked' : '' ?>><span><?= e(t($g)) ?></span></label><?php endforeach; ?></div><?= $err('gender') ?></fieldset>
            <div class="field"><label for="division_id"><?= e(t('division')) ?> <span class="req">*</span></label><select id="division_id" name="division_id" required><option value="">—</option><?php foreach ($divisions as $r): ?><option value="<?= (int) $r['id'] ?>" <?= (string) ($old['division_id'] ?? '') === (string) $r['id'] ? 'selected' : '' ?>><?= e($nm($r)) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="district_id"><?= e(t('district')) ?> <span class="req">*</span></label><select id="district_id" name="district_id" required><option value="">—</option><?php foreach ($districts as $r): ?><option data-parent="<?= (int) $r['division_id'] ?>" data-has-areas="<?= (int) $r['has_areas'] ?>" value="<?= (int) $r['id'] ?>" <?= (string) ($old['district_id'] ?? '') === (string) $r['id'] ? 'selected' : '' ?>><?= e($nm($r)) ?></option><?php endforeach; ?></select></div>
            <div class="field<?= $cls('location') ?>"><label for="upazila_id"><?= e(t('upazila')) ?> <span class="req">*</span></label><select id="upazila_id" name="upazila_id" required><option value="">—</option><?php foreach ($upazilas as $r): ?><option data-parent="<?= (int) $r['district_id'] ?>" value="<?= (int) $r['id'] ?>" <?= (string) ($old['upazila_id'] ?? '') === (string) $r['id'] ? 'selected' : '' ?>><?= e($nm($r)) ?></option><?php endforeach; ?></select><?= $err('location') ?></div>
            <div class="field"><label for="city_corporation_id"><?= e(t('city_corporation')) ?></label><select id="city_corporation_id" name="city_corporation_id"><option value="">—</option><?php foreach ($cities as $r): ?><option data-parent="<?= (int) $r['district_id'] ?>" value="<?= (int) $r['id'] ?>"><?= e($nm($r)) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="village"><?= e(t('village')) ?></label><input id="village" name="village" value="<?= $val('village') ?>"></div>
            <div class="field"><label for="city_corporation_area_id"><?= e(t('city_corporation_area')) ?></label><select id="city_corporation_area_id" name="city_corporation_area_id" disabled><option value="">—</option></select></div>
        </div>
        <details class="disclosure work-location-details">
            <summary class="disclosure-button" aria-label="<?= e(t('addr_add_another')) ?>">
                <span class="disclosure-leading" aria-hidden="true"><span class="disclosure-icon disclosure-icon-closed"><?= icon('plus', 18) ?></span><span class="disclosure-icon disclosure-icon-open"><?= icon('minus', 18) ?></span></span>
                <span class="disclosure-copy"><span class="disclosure-title"><?= e(t('addr_add_another')) ?></span><span class="disclosure-hint"><?= e(t('addr_add_another_hint')) ?></span></span>
                <span class="disclosure-chevron" aria-hidden="true"><?= icon('chevron-down', 20) ?></span>
            </summary>
            <div class="disclosure-content">
            <p class="field-hint"><?= e(t('work_location_help')) ?></p>
            <div class="form-grid">
                <div class="field"><label for="work_division_id"><?= e(t('division')) ?></label><select id="work_division_id" name="work_division_id"><option value="">—</option><?php foreach ($divisions as $r): ?><option value="<?= (int) $r['id'] ?>"><?= e($nm($r)) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="work_district_id"><?= e(t('district')) ?></label><select id="work_district_id" name="work_district_id"><option value="">—</option><?php foreach ($districts as $r): ?><option data-parent="<?= (int) $r['division_id'] ?>" data-has-areas="<?= (int) $r['has_areas'] ?>" value="<?= (int) $r['id'] ?>"><?= e($nm($r)) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="work_upazila_id"><?= e(t('upazila')) ?></label><select id="work_upazila_id" name="work_upazila_id"><option value="">—</option><?php foreach ($upazilas as $r): ?><option data-parent="<?= (int) $r['district_id'] ?>" value="<?= (int) $r['id'] ?>"><?= e($nm($r)) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="work_union_id"><?= e(t('union_or_pourashava')) ?></label><select id="work_union_id" name="work_union_id"><option value="">—</option><?php foreach ($unions as $r): ?><option data-parent="<?= (int) $r['upazila_id'] ?>" value="<?= (int) $r['id'] ?>"><?= e($nm($r)) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="work_city_corporation_id"><?= e(t('city_corporation')) ?></label><select id="work_city_corporation_id" name="work_city_corporation_id"><option value="">—</option><?php foreach ($cities as $r): ?><option data-parent="<?= (int) $r['district_id'] ?>" value="<?= (int) $r['id'] ?>"><?= e($nm($r)) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="work_city_corporation_area_id"><?= e(t('city_corporation_area')) ?></label><select id="work_city_corporation_area_id" name="work_city_corporation_area_id" disabled><option value="">—</option></select></div>
                <div class="field span-2"><label for="work_area_text"><?= e(t('work_road_landmark')) ?></label><input id="work_area_text" name="work_area_text" maxlength="150"></div>
            </div>
            </div>
        </details>
        <div class="button-row"><button class="button" type="submit"><?= icon('user-plus', 18) ?><span><?= e(t('create_donor')) ?></span></button></div>
    </form>
    <script src="<?= e(app_url('assets/js/address.js?v=8')) ?>" defer></script>
<?php endif; ?></section>
<?php require __DIR__ . '/../../app/views/layout/footer.php'; ?>
