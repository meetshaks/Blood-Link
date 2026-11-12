<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';

try {
    $conn = db();
} catch (RuntimeException $exception) {
    http_response_code(500);
    exit('Database connection failed.');
}
