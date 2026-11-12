<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/lib/validate.php';

$passed = 0;
$failed = 0;
$check = static function (bool $ok, string $name) use (&$passed, &$failed): void {
    if ($ok) { $passed++; echo "PASS: {$name}\n"; } else { $failed++; echo "FAIL: {$name}\n"; }
};
$check((int) db()->query('SELECT COUNT(*) FROM city_corporation_areas')->fetchColumn() > 0, 'city areas imported');
$check((int) db()->query('SELECT COUNT(*) FROM city_corporation_areas WHERE is_active = 1')->fetchColumn() > 0, 'active areas available');
$city = db()->query('SELECT c.id, c.district_id FROM city_corporations c JOIN city_corporation_areas a ON a.city_corporation_id = c.id ORDER BY c.id LIMIT 1')->fetch();
$area = null;
if (is_array($city)) {
    $areaStmt = db()->prepare('SELECT id, city_corporation_id FROM city_corporation_areas WHERE city_corporation_id = ? ORDER BY id LIMIT 1');
    $areaStmt->execute([(int) $city['id']]);
    $area = $areaStmt->fetch();
}
$check(is_array($city) && is_array($area), 'city and area fixtures exist');
if (is_array($city) && is_array($area)) {
    $district = (int) $city['district_id'];
    $divisionStmt = db()->prepare('SELECT division_id FROM districts WHERE id = ?');
    $divisionStmt->execute([$district]);
    $division = (int) $divisionStmt->fetchColumn();
    $check(validate_location(db(), $division, $district, null, null, (int) $city['id'], (int) $area['id']), 'area belongs to city and district');
    $check(!validate_location(db(), $division, $district, 1, null, (int) $city['id'], null), 'inside mode rejects upazila');
    $check(!validate_location(db(), $division, $district, null, null, null, (int) $area['id']), 'area without city rejected');
    $upazilaStmt = db()->prepare('SELECT id FROM upazilas WHERE district_id = ? LIMIT 1');
    $upazilaStmt->execute([$district]);
    $upazila = (int) $upazilaStmt->fetchColumn();
    $check(!validate_location(db(), $division, $district, null, null, null, null), 'outside mode requires upazila');
    $check($upazila > 0 && !validate_location(db(), $division, $district, $upazila, null, (int) $city['id'], null), 'inside mode rejects mixed city/upazila');
    $check(!validate_location(db(), $division, $district, $upazila, null, null, (int) $area['id']), 'outside mode rejects city area');
}
$noAreaCity = db()->query('SELECT cc.id, cc.district_id FROM city_corporations cc LEFT JOIN city_corporation_areas ca ON ca.city_corporation_id = cc.id AND ca.is_active = 1 WHERE ca.id IS NULL LIMIT 1')->fetch();
if (is_array($noAreaCity)) {
    $districtStmt = db()->prepare('SELECT division_id FROM districts WHERE id = ?');
    $districtStmt->execute([(int) $noAreaCity['district_id']]);
    $noAreaDivision = (int) $districtStmt->fetchColumn();
    $check(!validate_location(db(), $noAreaDivision, (int) $noAreaCity['district_id'], null, null, (int) $noAreaCity['id'], null), 'city corporation without areas is rejected');
}
printf("City-area tests: %d passed, %d failed.\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);
