<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This benchmark must run from the command line.\n");
    exit(1);
}

require __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/lib/donors.php';

$pdo = db();
$location = $pdo->query(
    'SELECT d.division_id, d.id AS district_id, u.id AS upazila_id
     FROM districts d
     JOIN upazilas u ON u.district_id = d.id
     ORDER BY d.id, u.id
     LIMIT 1'
)->fetch();
if (!is_array($location)) {
    fwrite(STDERR, "A valid donor location is required to run the benchmark.\n");
    exit(1);
}

$runPrefix = 'phase6bench_' . bin2hex(random_bytes(6)) . '_';
$basePhone = random_int(10000000, 49000000);
$phoneStart = '017' . str_pad((string) $basePhone, 8, '0', STR_PAD_LEFT);
$phoneEnd = '017' . str_pad((string) ($basePhone + 50000), 8, '0', STR_PAD_LEFT);
$collision = $pdo->prepare('SELECT COUNT(*) FROM users WHERE phone BETWEEN ? AND ?');
$collision->execute([$phoneStart, $phoneEnd]);
if ((int) $collision->fetchColumn() !== 0) {
    fwrite(STDERR, "A phone-number collision was detected; rerun the benchmark.\n");
    exit(1);
}

$sizes = [1000, 10000, 50000];
$hash = password_hash('Phase6BenchmarkOnly!', PASSWORD_DEFAULT);
$measureSearch = static function (): array {
    $latencies = [];
    $result = null;
    for ($sample = 0; $sample < 3; $sample++) {
        $queryStarted = microtime(true);
        $result = search_donors(['bg' => 'A+', 'availability_status' => 'all'], 1, 12);
        $latencies[] = (microtime(true) - $queryStarted) * 1000;
    }
    sort($latencies);
    return ['donors' => (int) $result['total'], 'median_ms' => round($latencies[1], 2)];
};
$insert = $pdo->prepare(
    "INSERT INTO users (name, phone, password_hash, blood_group, date_of_birth, gender,
     division_id, district_id, upazila_id, status)
     VALUES (?, ?, ?, 'A+', '1990-01-01', 'male', ?, ?, ?, 'approved')"
);
$samples = [];
$samples['existing'] = $measureSearch();
$started = microtime(true);
$inserted = 0;

try {
    $pdo->beginTransaction();
    foreach ($sizes as $size) {
        while ($inserted < $size) {
            $phone = '017' . str_pad((string) ($basePhone + $inserted), 8, '0', STR_PAD_LEFT);
            $insert->execute([
                $runPrefix . $inserted,
                $phone,
                $hash,
                (int) $location['division_id'],
                (int) $location['district_id'],
                (int) $location['upazila_id'],
            ]);
            $inserted++;
        }

        $pdo->commit();
        $insertElapsed = microtime(true) - $started;
        $measurement = $measureSearch();
        $samples[(string) $size] = [
            'donors' => $measurement['donors'],
            'median_ms' => $measurement['median_ms'],
            'seeded_donors' => $size,
            'cumulative_insert_seconds' => round($insertElapsed, 2),
        ];
        if ($size !== end($sizes)) {
            $pdo->beginTransaction();
        }
    }

    echo "Temporary donor search benchmark (3 runs, median latency)\n";
    foreach ($samples as $label => $sample) {
        printf(
            "%s: matching donors=%d, search median=%.2f ms%s\n",
            $label === 'existing' ? 'Existing data' : number_format((int) $label) . ' seeded',
            $sample['donors'],
            $sample['median_ms'],
            isset($sample['cumulative_insert_seconds'])
                ? ', cumulative seed=' . number_format((float) $sample['cumulative_insert_seconds'], 2) . ' s'
                : ''
        );
    }
    echo "Temporary benchmark users are removed before exit.\n";
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $cleanup = $pdo->prepare('DELETE FROM users WHERE name LIKE ?');
    $cleanup->execute([$runPrefix . '%']);
}
