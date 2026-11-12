<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';

$ip = client_ip();
$rateKey = 'nearest:' . $ip;
db()->prepare("DELETE FROM api_rate_limits WHERE created_at < (NOW() - INTERVAL 1 HOUR)")->execute();
$rate = db()->prepare("SELECT COUNT(*) FROM api_rate_limits WHERE rate_key = ? AND created_at >= (NOW() - INTERVAL 1 MINUTE)");
$rate->execute([$rateKey]);
if ((int) $rate->fetchColumn() >= 20) {
    json_response(['ok' => false, 'error' => 'rate_limited'], 429);
}
db()->prepare('INSERT INTO api_rate_limits (rate_key, created_at) VALUES (?, NOW())')->execute([$rateKey]);

$lat = filter_var($_GET['lat'] ?? null, FILTER_VALIDATE_FLOAT);
$lng = filter_var($_GET['lng'] ?? null, FILTER_VALIDATE_FLOAT);
if ($lat === false || $lng === false || $lat < 20.5 || $lat > 26.7 || $lng < 88.0 || $lng > 92.8) {
    json_response(['ok' => false, 'error' => 'outside_bangladesh'], 400);
}

$stmt = db()->prepare(
    'SELECT d.id, d.division_id, d.name_bn, d.name_en, v.name_bn AS division_bn, v.name_en AS division_en,
            (6371 * ACOS(LEAST(1, GREATEST(-1,
                COS(RADIANS(:lat)) * COS(RADIANS(d.lat)) * COS(RADIANS(d.lng) - RADIANS(:lng))
                + SIN(RADIANS(:lat2)) * SIN(RADIANS(d.lat))
            )))) AS distance_km
     FROM districts d
     JOIN divisions v ON v.id = d.division_id
     WHERE d.lat IS NOT NULL AND d.lng IS NOT NULL
     ORDER BY distance_km ASC
     LIMIT 1'
);
$stmt->execute(['lat' => $lat, 'lng' => $lng, 'lat2' => $lat]);
$row = $stmt->fetch();
if (!is_array($row)) {
    json_response(['ok' => false, 'error' => 'geo_unavailable'], 503);
}
json_response([
    'ok' => true,
    'division' => ['id' => (int) $row['division_id'], 'name_bn' => $row['division_bn'], 'name_en' => $row['division_en']],
    'district' => ['id' => (int) $row['id'], 'name_bn' => $row['name_bn'], 'name_en' => $row['name_en']],
    'distance_km' => round((float) $row['distance_km'], 1),
]);
