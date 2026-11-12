<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function donor_badge_tier(int $count): ?string
{
    if ($count >= 10) return 'gold';
    if ($count >= 5) return 'silver';
    if ($count >= 3) return 'bronze';
    return null;
}

function donor_badge_threshold(string $tier): int
{
    return ['bronze' => 3, 'silver' => 5, 'gold' => 10][$tier] ?? 0;
}

function donor_badge_summary(int $userId): array
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM donations WHERE user_id = ?');
    $stmt->execute([$userId]);
    $count = (int) $stmt->fetchColumn();
    $tier = donor_badge_tier($count);
    $thresholds = ['bronze', 'silver', 'gold'];
    $nextTier = null;
    $remaining = 0;
    foreach ($thresholds as $name) {
        $threshold = donor_badge_threshold($name);
        if ($count < $threshold) {
            $nextTier = $name;
            $remaining = $threshold - $count;
            break;
        }
    }
    return ['count' => $count, 'tier' => $tier, 'next_tier' => $nextTier, 'remaining' => $remaining];
}

function list_user_donations(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT id, donation_date, place, note, added_by, created_at
         FROM donations WHERE user_id = ? ORDER BY donation_date DESC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function sync_last_donation(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare(
        'UPDATE users SET last_donation_date =
         (SELECT MAX(donation_date) FROM donations WHERE user_id = ?) WHERE id = ?'
    );
    $stmt->execute([$userId, $userId]);
}

function add_donation(int $userId, string $date, ?string $place, ?string $note): void
{
    if (!validate_donation_date($date)) {
        throw new InvalidArgumentException(json_encode([
            'donation_date' => 'Enter a valid donation date that is not in the future.',
        ], JSON_THROW_ON_ERROR));
    }
    $rawPlace = $place;
    $rawNote = $note;
    $place = validate_optional_text($rawPlace, 200);
    $note = validate_optional_text($rawNote, 500);
    if ($place === null && trim((string) $rawPlace) !== '') {
        throw new InvalidArgumentException(json_encode(['place' => 'Place must be 200 characters or fewer.'], JSON_THROW_ON_ERROR));
    }
    if ($note === null && trim((string) $rawNote) !== '') {
        throw new InvalidArgumentException(json_encode(['note' => 'Note must be 500 characters or fewer.'], JSON_THROW_ON_ERROR));
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO donations (user_id, donation_date, place, note, added_by)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $date, $place, $note, 'user']);
        sync_last_donation($pdo, $userId);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof PDOException && ($exception->errorInfo[1] ?? null) === 1062) {
            throw new InvalidArgumentException(json_encode([
                'donation_date' => 'A donation is already recorded for this date.',
            ], JSON_THROW_ON_ERROR));
        }
        throw $exception;
    }
}

function delete_donation(int $userId, int $donationId): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('DELETE FROM donations WHERE id = ? AND user_id = ?');
        $stmt->execute([$donationId, $userId]);
        if ($stmt->rowCount() !== 1) {
            throw new InvalidArgumentException('Donation record was not found.');
        }
        sync_last_donation($pdo, $userId);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}
