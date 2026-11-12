<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function login_is_locked(string $identifier, string $scope, int $maxAttempts = 5, int $windowMinutes = 15): bool
{
    if (!in_array($scope, ['user', 'admin'], true) || $maxAttempts < 1 || $windowMinutes < 1) {
        throw new InvalidArgumentException('Invalid rate-limit parameters.');
    }
    $windowMinutes = min($windowMinutes, 1440);
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE identifier = ? AND scope = ?
         AND attempted_at >= (NOW() - INTERVAL ' . $windowMinutes . ' MINUTE)'
    );
    $stmt->execute([$identifier, $scope]);
    return (int) $stmt->fetchColumn() >= $maxAttempts;
}

function record_login_failure(string $identifier, string $scope): void
{
    if (!in_array($scope, ['user', 'admin'], true)) {
        throw new InvalidArgumentException('Invalid login scope.');
    }
    $stmt = db()->prepare(
        'INSERT INTO login_attempts (identifier, ip_address, scope) VALUES (?, ?, ?)'
    );
    $stmt->execute([$identifier, client_ip(), $scope]);
}

function clear_login_failures(string $identifier, string $scope): void
{
    $stmt = db()->prepare('DELETE FROM login_attempts WHERE identifier = ? AND scope = ?');
    $stmt->execute([$identifier, $scope]);
}
