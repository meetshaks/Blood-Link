<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must run from the command line.\n");
    exit(1);
}
require __DIR__ . '/../app/lib/db.php';

$file = __DIR__ . '/../Assets/city_corp.json';
$dryRun = false;
$replace = false;
$forceNull = false;
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--file=')) $file = substr($argument, 7);
    elseif ($argument === '--dry-run') $dryRun = true;
    elseif ($argument === '--replace') $replace = true;
    elseif ($argument === '--force-null') $forceNull = true;
}
if (!is_file($file) || !is_readable($file)) {
    fwrite(STDERR, "Dataset not found: {$file}\n");
    exit(1);
}
try {
    $rows = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($rows)) throw new RuntimeException('JSON root must be an array.');
    $pdo = db();
    $cities = $pdo->query('SELECT id, name_en FROM city_corporations')->fetchAll();
    $cityIds = [];
    foreach ($cities as $city) $cityIds[strtolower(trim((string) $city['name_en']))] = (int) $city['id'];
    $counts = ['DNCC' => ['valid' => 0, 'inserted' => 0, 'updated' => 0], 'DSCC' => ['valid' => 0, 'inserted' => 0, 'updated' => 0]];
    $empty = 0;
    $prepared = $pdo->prepare(
        'INSERT INTO city_corporation_areas (city_corporation_id, name_bn, name_en, ward_no, source_id, sort_order, is_active)
         VALUES (?, ?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE name_bn=VALUES(name_bn), source_id=VALUES(source_id), sort_order=VALUES(sort_order), is_active=1'
    );
    if ($replace && !$dryRun) {
        $used = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE city_corporation_area_id IS NOT NULL')->fetchColumn()
            + (int) $pdo->query('SELECT COUNT(*) FROM user_locations WHERE city_corporation_area_id IS NOT NULL')->fetchColumn();
        if ($used > 0 && !$forceNull) throw new RuntimeException('Replace refused: donors reference city areas. Use --force-null.');
    }
    if (!$dryRun) $pdo->beginTransaction();
    try {
        if ($replace && !$dryRun) {
            if ($forceNull) {
                $pdo->exec('UPDATE users SET city_corporation_area_id = NULL');
                $pdo->exec('UPDATE user_locations SET city_corporation_area_id = NULL');
            }
            $pdo->exec('DELETE FROM city_corporation_areas');
        }
        foreach ($rows as $row) {
            if (!is_array($row) || trim((string) ($row['area_name']['en'] ?? '')) === '' || trim((string) ($row['area_name']['bn'] ?? '')) === '' || trim((string) ($row['city_corporation'] ?? '')) === '' || trim((string) ($row['ward'] ?? '')) === '') {
                $empty++;
                continue;
            }
            $cityName = strtolower(trim((string) $row['city_corporation']));
            if (!isset($cityIds[$cityName])) throw new RuntimeException("City corporation not found: {$row['city_corporation']}");
            $tag = (string) ($row['city_corp_tag'] ?? '');
            if (!isset($counts[$tag])) throw new RuntimeException("Unsupported city corporation tag: {$tag}");
            $counts[$tag]['valid']++;
            if (!$dryRun) {
                $before = (int) $pdo->query('SELECT COUNT(*) FROM city_corporation_areas')->fetchColumn();
                $prepared->execute([$cityIds[$cityName], trim((string) $row['area_name']['bn']), trim((string) $row['area_name']['en']), (int) $row['ward'], substr(trim((string) ($row['id'] ?? '')), 0, 64), (int) $row['ward']]);
                $after = (int) $pdo->query('SELECT COUNT(*) FROM city_corporation_areas')->fetchColumn();
                $counts[$tag][$after > $before ? 'inserted' : 'updated']++;
            }
        }
        if (!$dryRun) $pdo->commit();
    } catch (Throwable $exception) {
        if (!$dryRun && $pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
    foreach ($counts as $tag => $count) printf("%s: valid=%d inserted=%d updated=%d\n", $tag, $count['valid'], $count['inserted'], $count['updated']);
    printf("empty/skipped: %d\n", $empty);
} catch (Throwable $exception) {
    fwrite(STDERR, "Import failed: {$exception->getMessage()}\n");
    exit(1);
}
