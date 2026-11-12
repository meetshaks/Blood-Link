<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';

$type = (string) ($_GET['type'] ?? '');
$queries = [
    'divisions' => ['SELECT id, name_bn, name_en FROM divisions ORDER BY name_bn', 'divisions'],
    'districts' => ['SELECT d.id, d.name_bn, d.name_en, EXISTS (SELECT 1 FROM city_corporations cc JOIN city_corporation_areas ca ON ca.city_corporation_id = cc.id AND ca.is_active = 1 WHERE cc.district_id = d.id) AS has_areas FROM districts d WHERE d.division_id = ? ORDER BY d.name_bn', 'districts'],
    'city_corporations' => ['SELECT id, name_bn, name_en FROM city_corporations WHERE district_id = ? ORDER BY name_bn', 'city_corporations'],
    'city_corporation_areas' => ['SELECT id, name_bn, name_en, ward_no FROM city_corporation_areas WHERE city_corporation_id = ? AND is_active = 1 ORDER BY ward_no, name_bn', 'city_corporation_areas'],
    'upazilas' => ['SELECT id, name_bn, name_en FROM upazilas WHERE district_id = ? ORDER BY name_bn', 'upazilas'],
    'unions' => ['SELECT id, name_bn, name_en, type, category FROM unions WHERE upazila_id = ? ORDER BY type, name_bn', 'unions'],
];
if (!isset($queries[$type])) {
    json_response(['ok' => false, 'error' => 'invalid_type'], 400);
}

$parentId = filter_var($_GET['parent_id'] ?? null, FILTER_VALIDATE_INT);
if ($type !== 'divisions' && ($parentId === false || $parentId < 1)) {
    json_response(['ok' => false, 'error' => 'invalid_parent'], 400);
}
$stmt = db()->prepare($queries[$type][0]);
$stmt->execute($type === 'divisions' ? [] : [$parentId]);
header('Cache-Control: public, max-age=86400');
json_response(['ok' => true, 'type' => $queries[$type][1], 'items' => $stmt->fetchAll()]);
