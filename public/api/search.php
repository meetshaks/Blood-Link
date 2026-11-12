<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/donors.php';

$ip = client_ip();
$rateKey = 'search:' . $ip;
$cleanup = db()->prepare("DELETE FROM api_rate_limits WHERE created_at < (NOW() - INTERVAL 1 HOUR)");
$cleanup->execute();
$rate = db()->prepare("SELECT COUNT(*) FROM api_rate_limits WHERE rate_key = ? AND created_at >= (NOW() - INTERVAL 1 MINUTE)");
$rate->execute([$rateKey]);
if ((int) $rate->fetchColumn() >= 30) {
    json_response(['ok' => false, 'error' => 'rate_limited'], 429);
}
db()->prepare('INSERT INTO api_rate_limits (rate_key, created_at) VALUES (?, NOW())')->execute([$rateKey]);

$bloodGroup = (string) ($_GET['bg'] ?? '');
if (!validate_blood_group($bloodGroup)) {
    json_response(['ok' => false, 'error' => 'validation', 'fields' => ['bg' => 'Invalid blood group.']], 400);
}
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
if ($page === false || $page < 1) $page = 1;
$areaFilter = filter_var($_GET['city_corporation_area'] ?? null, FILTER_VALIDATE_INT);
if (($_GET['city_corporation_area'] ?? '') !== '' && ($areaFilter === false || $areaFilter < 1)) {
    json_response(['ok' => false, 'error' => 'validation', 'fields' => ['city_corporation_area' => 'Invalid city corporation area.']], 400);
}

try {
    $result = search_donors([
        'bg' => $bloodGroup,
        'division' => $_GET['division'] ?? '',
        'district' => $_GET['district'] ?? '',
        'upazila' => $_GET['upazila'] ?? '',
        'union' => $_GET['union'] ?? '',
        'city_corporation' => $_GET['city_corporation'] ?? '',
        'city_corporation_area' => $_GET['city_corporation_area'] ?? '',
        'availability_status' => $_GET['availability_status'] ?? 'all',
    ], $page);
    json_response(['ok' => true] + $result);
} catch (Throwable $exception) {
    app_log('Donor search failed', 'ERROR', ['exception' => $exception->getMessage()]);
    json_response(['ok' => false, 'error' => 'server'], 500);
}
