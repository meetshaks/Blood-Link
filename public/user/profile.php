<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/donations.php';
$user = require_user();
$badgeSummary = donor_badge_summary((int) $user['id']);
$workLocation = get_user_work_location((int) $user['id']);
$errors = [];
$old = $user;
$pdo = db();
$divisions = $pdo->query('SELECT id, name_bn, name_en FROM divisions ORDER BY name_bn')->fetchAll();
$districts = get_districts_with_area_flags($pdo);
$upazilas = $pdo->query('SELECT id, district_id, name_bn, name_en FROM upazilas ORDER BY name_bn')->fetchAll();
$unions = $pdo->query("SELECT id, upazila_id, type, name_bn, name_en FROM unions ORDER BY type, name_bn")->fetchAll();
$cities = $pdo->query('SELECT id, district_id, name_bn, name_en FROM city_corporations ORDER BY name_bn')->fetchAll();

if (is_post()) {
    $old = array_merge($user, $_POST);
    if (!csrf_validate()) {
        $errors['_form'] = 'Invalid form security token.';
    } elseif (($_POST['action'] ?? '') === 'remove_work') {
        delete_user_work_location((int) $user['id']);
        flash('success', t('work_location_removed'));
        redirect('/user/profile.php');
    } else {
        try {
            $result = update_user_profile((int) $user['id'], $_POST, $_FILES['photo'] ?? null);
            if (trim((string) ($_POST['work_district_id'] ?? '')) !== '') {
                save_user_work_location((int) $user['id'], $_POST);
            }
            if (($result['phone_changed'] ?? false) === true) {
                flash('success', sprintf(t('phone_changed_success'), num((string) $result['phone'])));
            } else {
                flash('success', t('profile_updated'));
            }
            redirect('/user/dashboard.php');
        } catch (InvalidArgumentException $exception) {
            $decoded = json_decode($exception->getMessage(), true);
            $errors = is_array($decoded) ? $decoded : ['_form' => $exception->getMessage()];
        } catch (Throwable $exception) {
            app_log('Profile update failed', 'ERROR', ['exception' => $exception->getMessage(), 'user_id' => $user['id']]);
            $errors['_form'] = 'Profile could not be updated. Please try again.';
        }
    }
}

$pageTitle = t('profile_title');
require __DIR__ . '/../../app/views/layout/header.php';

$lang = current_lang();
$name = static fn (array $row): string => $lang === 'bn' ? (string) $row['name_bn'] : (string) $row['name_en'];
$err = static fn (string $key): string => isset($errors[$key]) ? '<p class="field-error" role="alert" style="display:flex">' . icon('alert', 14) . '<span>' . e((string) $errors[$key]) . '</span></p>' : '';
$cls = static fn (string $key): string => isset($errors[$key]) ? ' has-error' : '';
$val = static fn (string $key): string => e((string) ($old[$key] ?? ''));
$photoUrl = !empty($user['photo']) ? app_url(ltrim((string) $user['photo'], '/')) : null;
$initial = mb_strtoupper(mb_substr((string) $user['name'], 0, 1));
$age = !empty($user['date_of_birth']) ? (int) (new DateTimeImmutable((string) $user['date_of_birth']))->diff(new DateTimeImmutable('today'))->y : null;
$workDisclosureOpen = $workLocation !== null || trim((string) ($_POST['work_district_id'] ?? '')) !== '' || isset($errors['work_location']);
?>
<section class="card form-card">
    <div class="page-head" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px">
        <div class="profile-summary">
            <div class="avatar avatar-lg" id="photo-preview"><?php if ($photoUrl !== null): ?><img src="<?= e($photoUrl) ?>" alt="<?= e(t('photo_of') . ' ' . $user['name']) ?>"><?php else: ?><?= e($initial) ?><?php endif; ?></div>
            <div>
                <h1><?= e(t('profile_title')) ?><?php if ($badgeSummary['tier'] !== null): ?><span class="donation-tier-chip <?= e('tier-' . $badgeSummary['tier']) ?>"><?= icon('award', 14) ?><?= e(t('badge_' . $badgeSummary['tier'])) ?></span><?php endif; ?></h1>
                <p class="muted" style="margin:0"><span class="blood-badge blood-badge-sm"><?= e($user['blood_group']) ?></span> <?= $age !== null ? e(num($age) . ' ' . t('years_short')) : '' ?></p>
            </div>
        </div>
        <a class="button button-ghost" href="<?= e(app_url('user/dashboard.php')) ?>"><?= icon('arrow-left', 18) ?><?= e(t('back')) ?></a>
    </div>
    <?php if ($errors !== []): ?><div class="alert alert-error" role="alert"><?= icon('alert', 18) ?><span><?= e((string) ($errors['_form'] ?? reset($errors))) ?></span></div><?php endif; ?>
    <div class="note-box" role="note"><?= icon('info', 18) ?><span><?= e(t('profile_identity_note')) ?> <a href="<?= e(app_url('user/requests.php')) ?>"><?= e(t('request_blood_group_link')) ?></a></span></div>
    <form method="post" enctype="multipart/form-data" novalidate data-address-prefix="" data-phone-change>
        <?= csrf_field() ?>
        <div class="form-section">
            <h2 class="section-title"><?= e(t('reg_step_personal')) ?></h2>
            <div class="form-grid">
                <div class="field<?= $cls('name') ?>"><label for="name"><?= e(t('name')) ?> <span class="req">*</span></label><input id="name" name="name" type="text" value="<?= $val('name') ?>" required><?= $err('name') ?></div>
                <div class="field<?= $cls('phone') ?>"><label for="phone"><?= e(t('phone')) ?></label><input id="phone" name="phone" type="tel" inputmode="tel" value="<?= $val('phone') ?>" required><p class="field-hint"><?= e(t('phone_change_note')) ?></p><?= $err('phone') ?></div>
                <div class="field<?= $cls('current_password') ?>" id="phone-password-field" hidden><label for="current_password"><?= e(t('current_password')) ?></label><input id="current_password" name="current_password" type="password" autocomplete="current-password"><?= $err('current_password') ?></div>
                <div class="field"><label for="blood_group"><?= e(t('blood_group')) ?> <span class="badge badge-pending" style="font-size:.7rem;padding:0 8px"><?= e(t('needs_approval_short')) ?></span></label><input id="blood_group" type="text" value="<?= e((string) ($user['blood_group'] ?? '')) ?>" readonly></div>
                <div class="field<?= $cls('date_of_birth') ?>"><label for="date_of_birth"><?= e(t('date_of_birth')) ?> <span class="req">*</span></label><input id="date_of_birth" name="date_of_birth" type="date" value="<?= $val('date_of_birth') ?>" required><?= $err('date_of_birth') ?></div>
                <fieldset class="field<?= $cls('gender') ?>">
                    <legend><?= e(t('gender')) ?> <span class="req" style="color:var(--red-600)">*</span></legend>
                    <div class="radio-group"><?php foreach (['male', 'female', 'other'] as $gender): ?><label class="radio-pill"><input type="radio" name="gender" value="<?= $gender ?>" <?= ($old['gender'] ?? '') === $gender ? 'checked' : '' ?> required><span><?= e(t($gender)) ?></span></label><?php endforeach; ?></div>
                    <?= $err('gender') ?>
                </fieldset>
                <div class="field"><label for="photo"><?= e(t('profile_photo')) ?></label><input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" data-preview="#photo-preview"><p class="field-hint"><?= e(t('photo_hint')) ?></p></div>
            </div>
        </div>
        <div class="form-section">
            <h2 class="section-title"><?= e(t('reg_step_address')) ?></h2>
            <?= $err('location') ?>
            <div class="form-grid">
                <div class="field"><label for="division_id"><?= e(t('division')) ?> <span class="req">*</span></label><select id="division_id" name="division_id" required><option value="">—</option><?php foreach ($divisions as $row): ?><option value="<?= (int) $row['id'] ?>" <?= (string) ($old['division_id'] ?? '') === (string) $row['id'] ? 'selected' : '' ?>><?= e($name($row)) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="district_id"><?= e(t('district')) ?> <span class="req">*</span></label><select id="district_id" name="district_id" required><option value="">—</option><?php foreach ($districts as $row): ?><option data-parent="<?= (int) $row['division_id'] ?>" data-has-areas="<?= (int) $row['has_areas'] ?>" value="<?= (int) $row['id'] ?>" <?= (string) ($old['district_id'] ?? '') === (string) $row['id'] ? 'selected' : '' ?>><?= e($name($row)) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="upazila_id"><?= e(t('upazila')) ?></label><select id="upazila_id" name="upazila_id"><option value="">—</option><?php foreach ($upazilas as $row): ?><option data-parent="<?= (int) $row['district_id'] ?>" value="<?= (int) $row['id'] ?>" <?= (string) ($old['upazila_id'] ?? '') === (string) $row['id'] ? 'selected' : '' ?>><?= e($name($row)) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="union_id"><?= e(t('union_or_pourashava')) ?></label><select id="union_id" name="union_id"><option value="">—</option><?php foreach ($unions as $row): ?><option data-parent="<?= (int) $row['upazila_id'] ?>" value="<?= (int) $row['id'] ?>" <?= (string) ($old['union_id'] ?? '') === (string) $row['id'] ? 'selected' : '' ?>><?= e($name($row) . ($row['type'] === 'pourashava' ? ' (' . t('pourashava') . ')' : '')) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="city_corporation_id"><?= e(t('city_corporation')) ?></label><select id="city_corporation_id" name="city_corporation_id"><option value="">—</option><?php foreach ($cities as $row): ?><option data-parent="<?= (int) $row['district_id'] ?>" value="<?= (int) $row['id'] ?>" <?= (string) ($old['city_corporation_id'] ?? '') === (string) $row['id'] ? 'selected' : '' ?>><?= e($name($row)) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="city_corporation_area_id"><?= e(t('city_corporation_area')) ?></label><select id="city_corporation_area_id" name="city_corporation_area_id" data-selected="<?= e((string) ($old['city_corporation_area_id'] ?? $user['city_corporation_area_id'] ?? '')) ?>" disabled><option value="">—</option></select></div>
                <div class="field<?= $cls('village') ?>"><label for="village"><?= e(t('village')) ?></label><input id="village" name="village" type="text" value="<?= $val('village') ?>"><?= $err('village') ?></div>
            </div>
        </div>
        <details class="disclosure work-location-details" id="work-location"<?= $workDisclosureOpen ? ' open' : '' ?>>
            <summary class="disclosure-button" aria-label="<?= e(t('addr_add_another')) ?>">
                <span class="disclosure-leading" aria-hidden="true"><span class="disclosure-icon disclosure-icon-closed"><?= icon('plus', 18) ?></span><span class="disclosure-icon disclosure-icon-open"><?= icon('minus', 18) ?></span></span>
                <span class="disclosure-copy"><span class="disclosure-title"><?= e($workLocation !== null ? t('work_location_saved_title') : t('addr_add_another')) ?></span><span class="disclosure-hint"><?= e(t('addr_add_another_hint')) ?></span></span>
                <span class="disclosure-chevron" aria-hidden="true"><?= icon('chevron-down', 20) ?></span>
            </summary>
            <div class="disclosure-content">
            <p class="field-hint"><?= e(t('work_location_help')) ?></p>
            <?= $err('work_location') ?>
            <div class="form-grid">
                <div class="field"><label for="work_division_id"><?= e(t('division')) ?></label><select id="work_division_id" name="work_division_id"><option value="">—</option><?php foreach ($divisions as $row): ?><option value="<?= (int) $row['id'] ?>" <?= (string) ($old['work_division_id'] ?? $workLocation['division_id'] ?? '') === (string) $row['id'] ? 'selected' : '' ?>><?= e($name($row)) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="work_district_id"><?= e(t('district')) ?></label><select id="work_district_id" name="work_district_id"><option value="">—</option><?php foreach ($districts as $row): ?><option data-parent="<?= (int) $row['division_id'] ?>" data-has-areas="<?= (int) $row['has_areas'] ?>" value="<?= (int) $row['id'] ?>" <?= (string) ($old['work_district_id'] ?? $workLocation['district_id'] ?? '') === (string) $row['id'] ? 'selected' : '' ?>><?= e($name($row)) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="work_upazila_id"><?= e(t('upazila')) ?></label><select id="work_upazila_id" name="work_upazila_id"><option value="">—</option><?php foreach ($upazilas as $row): ?><option data-parent="<?= (int) $row['district_id'] ?>" value="<?= (int) $row['id'] ?>" <?= (string) ($old['work_upazila_id'] ?? $workLocation['upazila_id'] ?? '') === (string) $row['id'] ? 'selected' : '' ?>><?= e($name($row)) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="work_union_id"><?= e(t('union_or_pourashava')) ?></label><select id="work_union_id" name="work_union_id"><option value="">—</option><?php foreach ($unions as $row): ?><option data-parent="<?= (int) $row['upazila_id'] ?>" value="<?= (int) $row['id'] ?>" <?= (string) ($old['work_union_id'] ?? $workLocation['union_id'] ?? '') === (string) $row['id'] ? 'selected' : '' ?>><?= e($name($row) . ($row['type'] === 'pourashava' ? ' (' . t('pourashava') . ')' : '')) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="work_city_corporation_id"><?= e(t('city_corporation')) ?></label><select id="work_city_corporation_id" name="work_city_corporation_id"><option value="">—</option><?php foreach ($cities as $row): ?><option data-parent="<?= (int) $row['district_id'] ?>" value="<?= (int) $row['id'] ?>" <?= (string) ($old['work_city_corporation_id'] ?? $workLocation['city_corporation_id'] ?? '') === (string) $row['id'] ? 'selected' : '' ?>><?= e($name($row)) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="work_city_corporation_area_id"><?= e(t('city_corporation_area')) ?></label><select id="work_city_corporation_area_id" name="work_city_corporation_area_id" data-selected="<?= e((string) ($old['work_city_corporation_area_id'] ?? $workLocation['city_corporation_area_id'] ?? '')) ?>" disabled><option value="">—</option></select></div>
                <div class="field span-2"><label for="work_area_text"><?= e(t('work_road_landmark')) ?></label><input id="work_area_text" name="work_area_text" maxlength="150" value="<?= e((string) ($old['work_area_text'] ?? $workLocation['area_text'] ?? '')) ?>"></div>
                </div>
            <div class="work-location-footer">
            <label class="switch-row switch-box work-visibility-row">
                <input class="sr-only" type="checkbox" name="work_is_visible" value="1" <?= $workLocation === null || (int) $workLocation['is_visible'] === 1 ? 'checked' : '' ?>>
                <span class="switch" aria-hidden="true"></span>
                <span class="switch-text"><span class="switch-label"><?= e(t('work_visibility')) ?></span><span class="switch-state-hint"><?= e(t('work_visible_hint')) ?></span></span>
            </label>
            <?php if ($workLocation !== null): ?><button class="button work-remove-button" type="submit" name="action" value="remove_work" data-confirm="<?= e(t('remove_work_location_confirm')) ?>" data-danger="1"><?= icon('trash', 16) ?><?= e(t('remove_work_location')) ?></button><?php endif; ?>
            </div>
            </div>
        </details>
        <div class="button-row"><button class="button" type="submit"><?= icon('check', 18) ?><span><?= e(t('save_changes')) ?></span></button><a class="button button-ghost" href="<?= e(app_url('user/dashboard.php')) ?>"><?= e(t('cancel')) ?></a></div>
    </form>
</section>
<script src="<?= e(app_url('assets/js/address.js?v=9')) ?>" defer></script>
<?php require __DIR__ . '/../../app/views/layout/footer.php'; ?>
