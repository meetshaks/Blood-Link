<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function location_is_active(array $row): bool
{
    return (int) ($row['is_visible'] ?? 0) === 1;
}

function get_user_work_location(int $userId): ?array
{
    $stmt = db()->prepare(
        'SELECT wl.*, d.name_bn AS division_bn, d.name_en AS division_en,
                dr.name_bn AS district_bn, dr.name_en AS district_en,
                up.name_bn AS upazila_bn, up.name_en AS upazila_en,
                cc.name_bn AS cc_bn, cc.name_en AS cc_en
                , ca.name_bn AS cc_area_bn, ca.name_en AS cc_area_en, ca.ward_no AS cc_area_ward
         FROM user_locations wl
         JOIN divisions d ON d.id = wl.division_id
         JOIN districts dr ON dr.id = wl.district_id
         LEFT JOIN upazilas up ON up.id = wl.upazila_id
         LEFT JOIN city_corporations cc ON cc.id = wl.city_corporation_id
         LEFT JOIN city_corporation_areas ca ON ca.id = wl.city_corporation_area_id
         WHERE wl.user_id = ? LIMIT 1'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function validate_work_location(array $data): array
{
    $division = filter_var($data['work_division_id'] ?? null, FILTER_VALIDATE_INT);
    $district = filter_var($data['work_district_id'] ?? null, FILTER_VALIDATE_INT);
    $upazila = ($data['work_upazila_id'] ?? '') === '' ? null : filter_var($data['work_upazila_id'], FILTER_VALIDATE_INT);
    $union = ($data['work_union_id'] ?? '') === '' ? null : filter_var($data['work_union_id'], FILTER_VALIDATE_INT);
    $city = ($data['work_city_corporation_id'] ?? '') === '' ? null : filter_var($data['work_city_corporation_id'], FILTER_VALIDATE_INT);
    $cityArea = ($data['work_city_corporation_area_id'] ?? '') === '' ? null : filter_var($data['work_city_corporation_area_id'], FILTER_VALIDATE_INT);
    foreach (['division', 'district', 'upazila', 'union', 'city', 'cityArea'] as $key) {
        if ($$key === false) $$key = null;
    }
    $errors = [];
    if (!is_int($division) || !is_int($district) || (!is_int($upazila) && !is_int($city))) {
        $errors['work_location'] = 'Select a valid work location.';
    } elseif (!validate_location(db(), $division, $district, is_int($upazila) ? $upazila : null, is_int($union) ? $union : null, is_int($city) ? $city : null, is_int($cityArea) ? $cityArea : null)) {
        $errors['work_location'] = 'The selected work location hierarchy is invalid.';
    }
    $area = validate_optional_text($data['work_area_text'] ?? null, 150);
    if (($data['work_area_text'] ?? '') !== '' && $area === null) $errors['work_area_text'] = 'Work area must be 150 characters or fewer.';
    if ($errors !== []) throw new InvalidArgumentException(json_encode($errors, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    return ['division_id' => $division, 'district_id' => $district, 'upazila_id' => $upazila, 'union_id' => $union, 'city_corporation_id' => $city, 'city_corporation_area_id' => $cityArea, 'area_text' => $area];
}

function save_user_work_location(int $userId, array $data): void
{
    $location = validate_work_location($data);
    $visible = array_key_exists('work_is_visible', $data) ? ((string) $data['work_is_visible'] === '1' ? 1 : 0) : 1;
    db()->prepare(
        'INSERT INTO user_locations (user_id, division_id, district_id, upazila_id, union_id, city_corporation_id, city_corporation_area_id, area_text, is_visible, active_until)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)
         ON DUPLICATE KEY UPDATE division_id=VALUES(division_id), district_id=VALUES(district_id), upazila_id=VALUES(upazila_id),
           union_id=VALUES(union_id), city_corporation_id=VALUES(city_corporation_id), city_corporation_area_id=VALUES(city_corporation_area_id), area_text=VALUES(area_text),
           is_visible=VALUES(is_visible), active_until=NULL'
    )->execute([$userId, $location['division_id'], $location['district_id'], $location['upazila_id'], $location['union_id'], $location['city_corporation_id'], $location['city_corporation_area_id'], $location['area_text'], $visible]);
}

function delete_user_work_location(int $userId): void
{
    db()->prepare('DELETE FROM user_locations WHERE user_id = ?')->execute([$userId]);
    db()->prepare("UPDATE users SET present_at = 'home' WHERE id = ? AND present_at = 'work'")->execute([$userId]);
}

function set_user_present_at(int $userId, string $where): bool
{
    if (!in_array($where, ['home', 'work'], true)) return false;
    if ($where === 'work') {
        $location = get_user_work_location($userId);
        if ($location === null || !location_is_active($location)) return false;
    }
    $stmt = db()->prepare('UPDATE users SET present_at = ? WHERE id = ?');
    $stmt->execute([$where, $userId]);
    return $stmt->rowCount() === 1 || $where === 'home';
}
