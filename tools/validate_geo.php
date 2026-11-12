<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must run from the command line.\n");
    exit(1);
}

require __DIR__ . '/../app/lib/db.php';

$expected = [
    'divisions' => 8, 'districts' => 64, 'city_corporations' => 12,
    'upazilas' => 495, 'unions' => 4537, 'pourashavas' => 328,
];

try {
    $pdo = db();
    $queries = [
        'divisions' => 'SELECT COUNT(*) FROM divisions',
        'districts' => 'SELECT COUNT(*) FROM districts',
        'city_corporations' => 'SELECT COUNT(*) FROM city_corporations',
        'upazilas' => 'SELECT COUNT(*) FROM upazilas',
        'unions' => "SELECT COUNT(*) FROM unions WHERE type = 'union'",
        'pourashavas' => "SELECT COUNT(*) FROM unions WHERE type = 'pourashava'",
    ];
    $actual = [];
    foreach ($queries as $name => $query) {
        $actual[$name] = (int) $pdo->query($query)->fetchColumn();
    }
    if ($actual !== $expected) {
        throw new RuntimeException('Count mismatch: expected ' . json_encode($expected) . ', got ' . json_encode($actual));
    }

    $checks = [
        'orphan districts' => 'SELECT COUNT(*) FROM districts d LEFT JOIN divisions v ON v.id = d.division_id WHERE v.id IS NULL',
        'orphan city corporations' => 'SELECT COUNT(*) FROM city_corporations c LEFT JOIN districts d ON d.id = c.district_id WHERE d.id IS NULL',
        'orphan upazilas' => 'SELECT COUNT(*) FROM upazilas u LEFT JOIN districts d ON d.id = u.district_id WHERE d.id IS NULL',
        'orphan unions' => 'SELECT COUNT(*) FROM unions u LEFT JOIN upazilas z ON z.id = u.upazila_id WHERE z.id IS NULL',
        'blank location names' => "SELECT COUNT(*) FROM unions WHERE name_en = '' OR name_bn = ''",
        'invalid categories' => "SELECT COUNT(*) FROM unions WHERE type = 'pourashava' AND (category IS NULL OR category NOT IN ('A','B','C'))",
    ];
    foreach ($checks as $name => $query) {
        if ((int) $pdo->query($query)->fetchColumn() !== 0) {
            throw new RuntimeException("Validation failed: {$name}.");
        }
    }

    echo "Geography validation passed.\n";
    foreach ($actual as $name => $count) {
        printf("%-20s %d\n", ucfirst(str_replace('_', ' ', $name)) . ':', $count);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, "Geography validation failed: {$exception->getMessage()}\n");
    exit(1);
}
