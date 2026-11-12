<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must run from the command line.\n");
    exit(1);
}

require __DIR__ . '/../app/lib/db.php';

$file = $argv[1] ?? __DIR__ . '/../Assets/bangladesh-geo.json';
if (!is_file($file) || !is_readable($file)) {
    fwrite(STDERR, "Geography file not found or unreadable: {$file}\n");
    exit(1);
}

try {
    $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data) || array_is_list($data) === false) {
        throw new RuntimeException('Geography JSON root must be an array.');
    }

    $pdo = db();
    $existing = (int) $pdo->query('SELECT COUNT(*) FROM divisions')->fetchColumn();
    if ($existing > 0) {
        throw new RuntimeException('divisions table is not empty; import aborted.');
    }

    $insertDivision = $pdo->prepare(
        'INSERT INTO divisions (name_en, name_bn, lat, lng) VALUES (?, ?, ?, ?)'
    );
    $insertDistrict = $pdo->prepare(
        'INSERT INTO districts (division_id, name_en, name_bn, lat, lng) VALUES (?, ?, ?, ?, ?)'
    );
    $insertCity = $pdo->prepare(
        'INSERT INTO city_corporations (district_id, name_en, name_bn, lat, lng) VALUES (?, ?, ?, ?, ?)'
    );
    $insertUpazila = $pdo->prepare(
        'INSERT INTO upazilas (district_id, name_en, name_bn) VALUES (?, ?, ?)'
    );
    $insertUnion = $pdo->prepare(
        'INSERT INTO unions (upazila_id, type, category, name_en, name_bn) VALUES (?, ?, ?, ?, ?)'
    );

    $counts = [
        'divisions' => 0, 'districts' => 0, 'city_corporations' => 0,
        'upazilas' => 0, 'unions' => 0, 'pourashavas' => 0, 'duplicates' => 0,
    ];

    $clean = static function (mixed $value): string {
        return trim((string) $value);
    };
    $coordinate = static function (mixed $value): ?string {
        return is_numeric($value) ? (string) $value : null;
    };

    $pdo->beginTransaction();
    try {
        foreach ($data as $division) {
            if (!is_array($division) || $clean($division['name'] ?? '') === '' || $clean($division['bn_name'] ?? '') === '') {
                throw new RuntimeException('Invalid division record.');
            }
            $insertDivision->execute([
                $clean($division['name']), $clean($division['bn_name']),
                $coordinate($division['lat'] ?? null), $coordinate($division['long'] ?? null),
            ]);
            $divisionId = (int) $pdo->lastInsertId();
            $counts['divisions']++;

            foreach (($division['districts'] ?? []) as $district) {
                if (!is_array($district)) {
                    throw new RuntimeException('Invalid district record.');
                }
                $insertDistrict->execute([
                    $divisionId, $clean($district['name'] ?? ''), $clean($district['bn_name'] ?? ''),
                    $coordinate($district['lat'] ?? null), $coordinate($district['long'] ?? null),
                ]);
                $districtId = (int) $pdo->lastInsertId();
                $counts['districts']++;

                foreach (($district['city_corporations'] ?? []) as $city) {
                    $insertCity->execute([
                        $districtId, $clean($city['name'] ?? ''), $clean($city['bn_name'] ?? ''),
                        $coordinate($city['lat'] ?? null), $coordinate($city['long'] ?? null),
                    ]);
                    $counts['city_corporations']++;
                }

                foreach (($district['upazilas'] ?? []) as $upazila) {
                    $insertUpazila->execute([
                        $districtId, $clean($upazila['name'] ?? ''), $clean($upazila['bn_name'] ?? ''),
                    ]);
                    $upazilaId = (int) $pdo->lastInsertId();
                    $counts['upazilas']++;
                    $seen = [];

                    foreach (($upazila['unions'] ?? []) as $union) {
                        $key = 'union|' . $clean($union['name'] ?? '') . '|' . $clean($union['bn_name'] ?? '');
                        if (isset($seen[$key])) {
                            $counts['duplicates']++;
                            continue;
                        }
                        $seen[$key] = true;
                        $insertUnion->execute([
                            $upazilaId, 'union', null,
                            $clean($union['name'] ?? ''), $clean($union['bn_name'] ?? ''),
                        ]);
                        $counts['unions']++;
                    }

                    foreach (($upazila['pourashavas'] ?? []) as $pourashava) {
                        $key = 'pourashava|' . $clean($pourashava['name'] ?? '') . '|' . $clean($pourashava['bn_name'] ?? '');
                        if (isset($seen[$key])) {
                            $counts['duplicates']++;
                            continue;
                        }
                        $seen[$key] = true;
                        $category = strtoupper(substr($clean($pourashava['category'] ?? ''), 0, 1)) ?: null;
                        $insertUnion->execute([
                            $upazilaId, 'pourashava', $category,
                            $clean($pourashava['name'] ?? ''), $clean($pourashava['bn_name'] ?? ''),
                        ]);
                        $counts['pourashavas']++;
                    }
                }
            }
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }

    foreach ($counts as $label => $count) {
        printf("%-20s %d\n", ucfirst(str_replace('_', ' ', $label)) . ':', $count);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, "Import failed: {$exception->getMessage()}\n");
    exit(1);
}
