<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$errors = [];
$old = $_POST;
$pdo = db();
$divisions = $pdo->query('SELECT id, name_bn, name_en FROM divisions ORDER BY name_bn')->fetchAll();
$districts = get_districts_with_area_flags($pdo);
$upazilas = $pdo->query('SELECT id, district_id, name_bn, name_en FROM upazilas ORDER BY name_bn')->fetchAll();
$unions = $pdo->query("SELECT id, upazila_id, type, name_bn, name_en FROM unions ORDER BY type, name_bn")->fetchAll();
$cities = $pdo->query('SELECT id, district_id, name_bn, name_en FROM city_corporations ORDER BY name_bn')->fetchAll();

if (is_post()) {
    if (!csrf_validate()) {
        $errors['_form'] = 'Invalid form security token.';
    } elseif ((string) ($_POST['password'] ?? '') !== (string) ($_POST['password_confirmation'] ?? '')) {
        $errors['password_confirmation'] = 'Passwords do not match.';
    } else {
        try {
            $result = register_user($_POST, $_FILES['photo'] ?? null);
            flash('success', $result['status'] === 'approved' ? t('registration_approved') : t('registration_success'));
            redirect('/login.php');
        } catch (InvalidArgumentException $exception) {
            $decoded = json_decode($exception->getMessage(), true);
            $errors = is_array($decoded) ? $decoded : ['_form' => $exception->getMessage()];
        } catch (Throwable $exception) {
            app_log('Registration failed', 'ERROR', ['exception' => $exception->getMessage()]);
            $errors['_form'] = 'Registration could not be completed. Please try again.';
        }
    }
}

$pageTitle = t('register_title');
$metaDescription = t('register_subtitle');
require __DIR__ . '/../app/views/layout/header.php';

$lang = current_lang();
$name = static fn (array $row): string => $lang === 'bn' ? (string) $row['name_bn'] : (string) $row['name_en'];
$err = static function (string $key) use ($errors): string {
    return isset($errors[$key]) ? '<p class="field-error" role="alert" style="display:flex">' . icon('alert', 14) . '<span>' . e((string) $errors[$key]) . '</span></p>' : '';
};
$cls = static fn (string $key): string => isset($errors[$key]) ? ' has-error' : '';
$val = static fn (string $key): string => e((string) ($old[$key] ?? ''));
$maxDob = (new DateTimeImmutable('today'))->modify('-18 years')->format('Y-m-d');
$today = date('Y-m-d');
$groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
$eye = static fn (string $target): string => '<button type="button" class="input-action" data-toggle-password="' . $target . '" aria-label="' . e(t('show_password')) . '"><span data-icon-show>' . icon('eye', 20) . '</span><span data-icon-hide>' . icon('eye-off', 20) . '</span></button>';
?>
<section class="card form-card">
    <div class="page-head">
        <h1><?= e(t('register_title')) ?></h1>
        <p><?= e(t('register_subtitle')) ?></p>
    </div>
    <?php if (isset($errors['_form'])): ?><div class="alert alert-error" role="alert"><?= icon('alert', 18) ?><span><?= e((string) $errors['_form']) ?></span></div><?php elseif ($errors !== []): ?><div class="alert alert-error" role="alert"><?= icon('alert', 18) ?><span><?= e(t('fix_errors')) ?></span></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" novalidate data-address-prefix="">
        <?= csrf_field() ?>

        <div class="form-section">
            <h2 class="section-title"><span class="step-no">1</span><?= e(t('reg_step_account')) ?></h2>
            <div class="form-grid">
                <div class="field span-2<?= $cls('phone') ?>">
                    <label for="phone"><?= e(t('phone')) ?> <span class="req">*</span></label>
                    <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="username" placeholder="01XXXXXXXXX" pattern="(\+?88)?01[3-9][0-9]{8}" value="<?= $val('phone') ?>" data-validate="phone" required>
                    <p class="field-hint"><?= e(t('phone_hint')) ?></p><?= $err('phone') ?>
                </div>
                <div class="field<?= $cls('password') ?>">
                    <label for="password"><?= e(t('password')) ?> <span class="req">*</span></label>
                    <div class="input-with-action"><input id="password" name="password" type="password" autocomplete="new-password" minlength="8" data-validate="password" required><?= $eye('password') ?></div>
                    <p class="field-hint"><?= e(t('password_hint')) ?></p><?= $err('password') ?>
                </div>
                <div class="field<?= $cls('password_confirmation') ?>">
                    <label for="password_confirmation"><?= e(t('confirm_password')) ?> <span class="req">*</span></label>
                    <div class="input-with-action"><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" data-validate="match" data-matches="password" required><?= $eye('password_confirmation') ?></div>
                    <?= $err('password_confirmation') ?>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2 class="section-title"><span class="step-no">2</span><?= e(t('reg_step_personal')) ?></h2>
            <div class="form-grid">
                <div class="field span-2<?= $cls('name') ?>">
                    <label for="name"><?= e(t('name')) ?> <span class="req">*</span></label>
                    <input id="name" name="name" type="text" autocomplete="name" value="<?= $val('name') ?>" data-validate="required" required><?= $err('name') ?>
                </div>
                <div class="field<?= $cls('date_of_birth') ?>">
                    <label for="date_of_birth"><?= e(t('date_of_birth')) ?> <span class="req">*</span></label>
                    <input id="date_of_birth" name="date_of_birth" type="date" max="<?= e($maxDob) ?>" value="<?= $val('date_of_birth') ?>" data-validate="dob" required>
                    <p class="field-hint"><?= e(t('dob_hint')) ?></p><?= $err('date_of_birth') ?>
                </div>
                <fieldset class="field<?= $cls('gender') ?>">
                    <legend><?= e(t('gender')) ?> <span class="req" style="color:var(--red-600)">*</span></legend>
                    <div class="radio-group">
                        <?php foreach (['male', 'female', 'other'] as $i => $gender): ?>
                            <label class="radio-pill"><input type="radio" name="gender" value="<?= $gender ?>" <?= ($old['gender'] ?? '') === $gender ? 'checked' : '' ?> required><span><?= e(t($gender)) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                    <?= $err('gender') ?>
                </fieldset>
                <fieldset class="field span-2<?= $cls('blood_group') ?>">
                    <legend><?= e(t('blood_group')) ?> <span class="req" style="color:var(--red-600)">*</span></legend>
                    <div class="chip-grid" style="max-width:420px">
                        <?php foreach ($groups as $group): ?>
                            <label class="chip"><input type="radio" name="blood_group" value="<?= e($group) ?>" <?= ($old['blood_group'] ?? '') === $group ? 'checked' : '' ?> required><span><?= e($group) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                    <?= $err('blood_group') ?>
                </fieldset>
                <div class="field span-2">
                    <label for="photo"><?= e(t('profile_photo')) ?> <span class="muted" style="font-weight:400">(<?= e(t('optional')) ?>)</span></label>
                    <div class="photo-upload">
                        <div class="avatar avatar-lg" id="photo-preview" aria-hidden="true"><?= icon('user', 36) ?></div>
                        <div style="flex:1"><input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" data-preview="#photo-preview"><p class="field-hint" style="margin-top:6px"><?= e(t('photo_hint')) ?></p></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2 class="section-title"><span class="step-no">3</span><?= e(t('reg_step_address')) ?></h2>
            <?= $err('location') ?>
            <p class="field-hint" style="margin-bottom:12px"><?= e(t('address_choose_hint')) ?></p>
            <div class="form-grid">
                <div class="field"><label for="division_id"><?= e(t('division')) ?> <span class="req">*</span></label><select id="division_id" name="division_id" required><option value="">—</option><?php foreach ($divisions as $row): ?><option value="<?= (int) $row['id'] ?>" <?= (string) ($old['division_id'] ?? '') === (string) $row['id'] ? 'selected' : '' ?>><?= e($name($row)) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="district_id"><?= e(t('district')) ?> <span class="req">*</span></label><select id="district_id" name="district_id" required><option value="">—</option><?php foreach ($districts as $row): ?><option data-parent="<?= (int) $row['division_id'] ?>" data-has-areas="<?= (int) $row['has_areas'] ?>" value="<?= (int) $row['id'] ?>" <?= (string) ($old['district_id'] ?? '') === (string) $row['id'] ? 'selected' : '' ?>><?= e($name($row)) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="upazila_id"><?= e(t('upazila')) ?></label><select id="upazila_id" name="upazila_id"><option value="">—</option><?php foreach ($upazilas as $row): ?><option data-parent="<?= (int) $row['district_id'] ?>" value="<?= (int) $row['id'] ?>" <?= (string) ($old['upazila_id'] ?? '') === (string) $row['id'] ? 'selected' : '' ?>><?= e($name($row)) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="union_id"><?= e(t('union_or_pourashava')) ?></label><select id="union_id" name="union_id"><option value="">—</option><?php foreach ($unions as $row): ?><option data-parent="<?= (int) $row['upazila_id'] ?>" value="<?= (int) $row['id'] ?>" <?= (string) ($old['union_id'] ?? '') === (string) $row['id'] ? 'selected' : '' ?>><?= e($name($row) . ($row['type'] === 'pourashava' ? ' (' . t('pourashava') . ')' : '')) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="city_corporation_id"><?= e(t('city_corporation')) ?></label><select id="city_corporation_id" name="city_corporation_id"><option value="">—</option><?php foreach ($cities as $row): ?><option data-parent="<?= (int) $row['district_id'] ?>" value="<?= (int) $row['id'] ?>" <?= (string) ($old['city_corporation_id'] ?? '') === (string) $row['id'] ? 'selected' : '' ?>><?= e($name($row)) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="city_corporation_area_id"><?= e(t('city_corporation_area')) ?></label><select id="city_corporation_area_id" name="city_corporation_area_id" data-selected="<?= e((string) ($old['city_corporation_area_id'] ?? '')) ?>" disabled><option value="">—</option></select></div>
                <div class="field<?= $cls('village') ?>"><label for="village"><?= e(t('village')) ?></label><input id="village" name="village" type="text" value="<?= $val('village') ?>"><?= $err('village') ?></div>
                <div class="field span-2<?= $cls('last_donation_date') ?>">
                    <label for="last_donation_date"><?= e(t('last_donation_date')) ?> <span class="muted" style="font-weight:400">(<?= e(t('optional')) ?>)</span></label>
                    <input id="last_donation_date" name="last_donation_date" type="date" max="<?= e($today) ?>" value="<?= $val('last_donation_date') ?>"><?= $err('last_donation_date') ?>
                </div>
            </div>
        </div>

        <details class="work-location-details disclosure">
            <summary class="disclosure-button" aria-label="<?= e(t('addr_add_another')) ?>">
                <span class="disclosure-leading" aria-hidden="true"><span class="disclosure-icon disclosure-icon-closed"><?= icon('plus', 18) ?></span><span class="disclosure-icon disclosure-icon-open"><?= icon('minus', 18) ?></span></span>
                <span class="disclosure-copy"><span class="disclosure-title"><?= e(t('addr_add_another')) ?></span><span class="disclosure-hint"><?= e(t('addr_add_another_hint')) ?></span></span>
                <span class="disclosure-chevron" aria-hidden="true"><?= icon('chevron-down', 20) ?></span>
            </summary>
            <div class="disclosure-content">
            <p class="field-hint"><?= e(t('work_location_help')) ?></p>
            <?= $err('work_location') ?>
            <div class="form-grid">
                <div class="field"><label for="work_division_id"><?= e(t('division')) ?></label><select id="work_division_id" name="work_division_id"><option value="">—</option><?php foreach ($divisions as $row): ?><option value="<?= (int) $row['id'] ?>" <?= (string) ($old['work_division_id'] ?? '') === (string) $row['id'] ? 'selected' : '' ?>><?= e($name($row)) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="work_district_id"><?= e(t('district')) ?></label><select id="work_district_id" name="work_district_id"><option value="">—</option><?php foreach ($districts as $row): ?><option data-parent="<?= (int) $row['division_id'] ?>" data-has-areas="<?= (int) $row['has_areas'] ?>" value="<?= (int) $row['id'] ?>" <?= (string) ($old['work_district_id'] ?? '') === (string) $row['id'] ? 'selected' : '' ?>><?= e($name($row)) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="work_upazila_id"><?= e(t('upazila')) ?></label><select id="work_upazila_id" name="work_upazila_id"><option value="">—</option><?php foreach ($upazilas as $row): ?><option data-parent="<?= (int) $row['district_id'] ?>" value="<?= (int) $row['id'] ?>" <?= (string) ($old['work_upazila_id'] ?? '') === (string) $row['id'] ? 'selected' : '' ?>><?= e($name($row)) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="work_union_id"><?= e(t('union_or_pourashava')) ?></label><select id="work_union_id" name="work_union_id"><option value="">—</option><?php foreach ($unions as $row): ?><option data-parent="<?= (int) $row['upazila_id'] ?>" value="<?= (int) $row['id'] ?>" <?= (string) ($old['work_union_id'] ?? '') === (string) $row['id'] ? 'selected' : '' ?>><?= e($name($row) . ($row['type'] === 'pourashava' ? ' (' . t('pourashava') . ')' : '')) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="work_city_corporation_id"><?= e(t('city_corporation')) ?></label><select id="work_city_corporation_id" name="work_city_corporation_id"><option value="">—</option><?php foreach ($cities as $row): ?><option data-parent="<?= (int) $row['district_id'] ?>" value="<?= (int) $row['id'] ?>" <?= (string) ($old['work_city_corporation_id'] ?? '') === (string) $row['id'] ? 'selected' : '' ?>><?= e($name($row)) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="work_city_corporation_area_id"><?= e(t('city_corporation_area')) ?></label><select id="work_city_corporation_area_id" name="work_city_corporation_area_id" data-selected="<?= e((string) ($old['work_city_corporation_area_id'] ?? '')) ?>" disabled><option value="">—</option></select></div>
                <div class="field span-2"><label for="work_area_text"><?= e(t('work_road_landmark')) ?></label><input id="work_area_text" name="work_area_text" maxlength="150" value="<?= $val('work_area_text') ?>"></div>
            </div>
            </div>
        </details>

        <div class="field<?= $cls('consent') ?>">
            <label class="checkbox-row"><input type="checkbox" name="consent" value="1" required <?= isset($old['consent']) ? 'checked' : '' ?>><span><?= e(t('consent')) ?></span></label>
            <?= $err('consent') ?>
        </div>
        <div class="button-row"><button class="button" type="submit"><?= icon('check', 18) ?><span><?= e(t('register_btn')) ?></span></button><a class="button button-ghost" href="<?= e(app_url('login.php')) ?>"><?= e(t('have_account')) ?></a></div>
    </form>
</section>
<script src="<?= e(app_url('assets/js/address.js?v=8')) ?>" defer></script>
<?php require __DIR__ . '/../app/views/layout/footer.php'; ?>
