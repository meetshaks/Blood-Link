<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/admin.php';
require_once __DIR__ . '/../../app/views/partials/admin_ui.php';

require_admin();
$rows = admin_user_export($_GET);
$filename = 'bloodlink-donors-' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

$output = fopen('php://output', 'wb');
if ($output === false) {
    http_response_code(500);
    exit;
}
fwrite($output, "\xEF\xBB\xBF");
$csvValue = static function (mixed $value): string {
    $value = (string) ($value ?? '');
    return preg_match('/^[=+\-@]/', $value) === 1 ? "'" . $value : $value;
};
fputcsv($output, [t('col_donor'), t('phone'), t('blood_group'), t('district'), t('work_short'), t('col_status'), t('last_donation_date'), t('availability')]);
foreach ($rows as $row) {
    $status = t((string) $row['status']);
    $available = (int) $row['available_toggle'] === 1 && ((int) $row['is_suspended'] !== 1 || ($row['suspended_until'] !== null && $row['suspended_until'] <= date('Y-m-d')));
    fputcsv($output, [
        $csvValue($row['name']),
        $csvValue($row['phone']),
        $csvValue($row['blood_group']),
        $csvValue(admin_district($row) ?: ''),
        $csvValue($row['work_district_bn'] ?? ''),
        $csvValue($status),
        $row['last_donation_date'] ? date_fmt((string) $row['last_donation_date']) : '',
        $csvValue($available ? t('available') : t('not_available')),
    ]);
}
fclose($output);
