<?php
declare(strict_types=1);

function normalize_phone(string $phone): ?string
{
    $value = preg_replace('/[\s-]+/', '', trim($phone));
    if (!is_string($value)) {
        return null;
    }
    if (str_starts_with($value, '+880')) {
        $value = '0' . substr($value, 4);
    } elseif (str_starts_with($value, '880')) {
        $value = '0' . substr($value, 3);
    }
    return preg_match('/^01[3-9][0-9]{8}$/', $value) === 1 ? $value : null;
}

function validate_name(string $name): ?string
{
    $value = trim($name);
    $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    return $length >= 2 && $length <= 120 ? $value : null;
}

function validate_password(string $password): bool
{
    return strlen($password) >= 8;
}

function validate_blood_group(string $group): bool
{
    return in_array($group, ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], true);
}

function validate_gender(string $gender): bool
{
    return in_array($gender, ['male', 'female', 'other'], true);
}

function validate_date(string $date): ?DateTimeImmutable
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date ? $parsed : null;
}

function validate_age(string $dateOfBirth, int $minAge, int $maxAge): bool
{
    $date = validate_date($dateOfBirth);
    if ($date === null || $minAge < 0 || $maxAge < $minAge) {
        return false;
    }
    $today = new DateTimeImmutable('today');
    if ($date > $today) {
        return false;
    }
    $age = $date->diff($today)->y;
    return $age >= $minAge && $age <= $maxAge;
}

function validate_donation_date(string $date): bool
{
    $parsed = validate_date($date);
    return $parsed !== null && $parsed <= new DateTimeImmutable('today');
}

function validate_optional_text(?string $value, int $maxLength): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }
    $clean = trim($value);
    $length = function_exists('mb_strlen') ? mb_strlen($clean) : strlen($clean);
    return $length <= $maxLength ? $clean : null;
}

function validate_location(PDO $pdo, int $divisionId, int $districtId, ?int $upazilaId, ?int $unionId = null, ?int $cityCorporationId = null, ?int $cityCorporationAreaId = null): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM districts WHERE id = ? AND division_id = ?'
    );
    $stmt->execute([$districtId, $divisionId]);
    if ((int) $stmt->fetchColumn() !== 1) {
        return false;
    }

    if ($cityCorporationId === null) {
        if ($upazilaId === null) return false;
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM upazilas WHERE id = ? AND district_id = ?');
        $stmt->execute([$upazilaId, $districtId]);
        if ((int) $stmt->fetchColumn() !== 1) {
            return false;
        }
    }

    if ($unionId !== null) {
        if ($upazilaId === null) {
            return false;
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM unions WHERE id = ? AND upazila_id = ?');
        $stmt->execute([$unionId, $upazilaId]);
        if ((int) $stmt->fetchColumn() !== 1) {
            return false;
        }
    }

    if ($cityCorporationId !== null) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM city_corporations WHERE id = ? AND district_id = ?');
        $stmt->execute([$cityCorporationId, $districtId]);
        if ((int) $stmt->fetchColumn() !== 1) {
            return false;
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM city_corporations cc JOIN city_corporation_areas ca ON ca.city_corporation_id = cc.id AND ca.is_active = 1 WHERE cc.id = ? AND cc.district_id = ?');
        $stmt->execute([$cityCorporationId, $districtId]);
        if ((int) $stmt->fetchColumn() < 1) return false;
        if ($upazilaId !== null || $unionId !== null) {
            return false;
        }
        if ($cityCorporationAreaId !== null) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM city_corporation_areas WHERE id = ? AND city_corporation_id = ? AND is_active = 1');
            $stmt->execute([$cityCorporationAreaId, $cityCorporationId]);
            if ((int) $stmt->fetchColumn() !== 1) return false;
        }
    } elseif ($cityCorporationAreaId !== null) {
        return false;
    }

    return true;
}
