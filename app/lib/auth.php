<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ratelimit.php';

function setting_value(string $key, string $default = ''): string
{
    $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return is_string($value) ? $value : $default;
}

function register_user(array $data, ?array $photo = null): array
{
    $name = validate_name((string) ($data['name'] ?? ''));
    $phone = normalize_phone((string) ($data['phone'] ?? ''));
    $password = (string) ($data['password'] ?? '');
    $bloodGroup = (string) ($data['blood_group'] ?? '');
    $dateOfBirth = (string) ($data['date_of_birth'] ?? '');
    $gender = (string) ($data['gender'] ?? '');
    $divisionId = filter_var($data['division_id'] ?? null, FILTER_VALIDATE_INT);
    $districtId = filter_var($data['district_id'] ?? null, FILTER_VALIDATE_INT);
    $upazilaId = filter_var($data['upazila_id'] ?? null, FILTER_VALIDATE_INT);
    $unionId = ($data['union_id'] ?? '') === '' ? null : filter_var($data['union_id'], FILTER_VALIDATE_INT);
    $cityId = ($data['city_corporation_id'] ?? '') === '' ? null : filter_var($data['city_corporation_id'], FILTER_VALIDATE_INT);
    $cityAreaId = ($data['city_corporation_area_id'] ?? '') === '' ? null : filter_var($data['city_corporation_area_id'], FILTER_VALIDATE_INT);
    if ($upazilaId === false) $upazilaId = null;
    if ($unionId === false) $unionId = null;
    if ($cityId === false) $cityId = null;
    if ($cityAreaId === false) $cityAreaId = null;
    $village = validate_optional_text($data['village'] ?? null, 150);
    $lastDonation = ($data['last_donation_date'] ?? '') === '' ? null : (string) $data['last_donation_date'];
    $errors = [];

    if ($name === null) $errors['name'] = 'Enter a valid name.';
    if ($phone === null) $errors['phone'] = 'Enter a valid Bangladesh mobile number.';
    if (!validate_password($password)) $errors['password'] = 'Password must be at least 8 characters.';
    if (!validate_blood_group($bloodGroup)) $errors['blood_group'] = 'Select a valid blood group.';
    $minAge = (int) setting_value('min_age', '18');
    $maxAge = (int) setting_value('max_age', '60');
    if (!validate_age($dateOfBirth, $minAge, $maxAge)) $errors['date_of_birth'] = 'Enter a valid date within the allowed age range.';
    if (!validate_gender($gender)) $errors['gender'] = 'Select a valid gender.';
    if (!is_int($divisionId) || !is_int($districtId) || (!is_int($upazilaId) && !is_int($cityId))) $errors['location'] = 'Select a valid location.';
    if (is_int($divisionId) && is_int($districtId) &&
        !validate_location(db(), $divisionId, $districtId, $upazilaId, is_int($unionId) ? $unionId : null, is_int($cityId) ? $cityId : null, is_int($cityAreaId) ? $cityAreaId : null)) {
        $errors['location'] = 'The selected location hierarchy is invalid.';
    }
    if (($data['village'] ?? '') !== '' && $village === null) $errors['village'] = 'Village must be 150 characters or fewer.';
    if ($lastDonation !== null && !validate_donation_date($lastDonation)) $errors['last_donation_date'] = 'Enter a valid donation date.';
    if (($data['consent'] ?? '') !== '1') $errors['consent'] = 'Consent is required.';
    $hasWorkLocation = trim((string) ($data['work_district_id'] ?? '')) !== '';
    if ($hasWorkLocation) {
        try {
            validate_work_location($data);
        } catch (InvalidArgumentException $exception) {
            $workErrors = json_decode($exception->getMessage(), true);
            if (is_array($workErrors)) $errors = array_merge($errors, $workErrors);
        }
    }
    if ($errors !== []) throw new InvalidArgumentException(json_encode($errors, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

    $status = setting_value('approval_mode', 'manual') === 'auto' ? 'approved' : 'pending';
    $photoPath = null;
    if ($photo !== null && ($photo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $photoPath = store_avatar($photo);
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = db()->prepare(
            'INSERT INTO users
             (name, phone, password_hash, photo, blood_group, date_of_birth, gender,
              division_id, district_id, upazila_id, union_id, city_corporation_id, city_corporation_area_id, village,
              last_donation_date, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $name, $phone, password_hash($password, PASSWORD_DEFAULT), $photoPath,
            $bloodGroup, $dateOfBirth, $gender, $divisionId, $districtId, $upazilaId,
            $unionId, $cityId, $cityAreaId, $village, $lastDonation, $status,
        ]);
        if ($hasWorkLocation) save_user_work_location((int) $pdo->lastInsertId(), $data);
        $pdo->commit();
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($photoPath !== null) {
            $path = dirname(__DIR__, 2) . '/public' . $photoPath;
            if (is_file($path)) unlink($path);
        }
        if (($exception->errorInfo[1] ?? null) === 1062) {
            throw new InvalidArgumentException(json_encode(['phone' => 'This phone number is already registered.'], JSON_THROW_ON_ERROR));
        }
        throw $exception;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($photoPath !== null) {
            $path = dirname(__DIR__, 2) . '/public' . $photoPath;
            if (is_file($path)) unlink($path);
        }
        throw $exception;
    }

    return ['status' => $status];
}

function attempt_user_login(string $phoneInput, string $password): array
{
    $phone = normalize_phone($phoneInput);
    if ($phone === null || login_is_locked($phone, 'user')) {
        return ['ok' => false, 'reason' => 'invalid'];
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE phone = ? LIMIT 1');
    $stmt->execute([$phone]);
    $user = $stmt->fetch();
    if (!is_array($user) || !password_verify($password, (string) $user['password_hash'])) {
        record_login_failure($phone, 'user');
        return ['ok' => false, 'reason' => 'invalid'];
    }

    clear_login_failures($phone, 'user');
    if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
        $rehash = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $rehash->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    $update = db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
    $update->execute([$user['id']]);
    if ($user['status'] === 'approved') {
        start_session();
        session_regenerate_id(true);
        session_set('uid', (int) $user['id']);
        session_set('uid_version', (int) ($user['session_version'] ?? 1));
    }
    return ['ok' => true, 'user' => $user];
}

function current_user(): ?array
{
    $id = session_get('uid');
    if (!is_int($id) && !ctype_digit((string) $id)) return null;
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([(int) $id]);
    $user = $stmt->fetch();
    if (!is_array($user)) return null;
    $version = session_get('uid_version');
    if ($version !== null && (int) $version !== (int) ($user['session_version'] ?? 1)) return null;
    return $user;
}

function require_user(bool $allowForcedChange = false): array
{
    no_store_headers();
    $user = current_user();
    if ($user === null) {
        flash('error', 'Please log in to continue.');
        redirect('/login.php');
    }
    if ((int) $user['must_change_password'] === 1 && !$allowForcedChange) {
        redirect('/user/password.php');
    }
    return $user;
}

function change_user_password(int $userId, string $current, string $new): bool
{
    if (!validate_password($new)) return false;
    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $hash = $stmt->fetchColumn();
    if (!is_string($hash) || !password_verify($current, $hash)) return false;
    $update = db()->prepare('UPDATE users SET password_hash = ?, must_change_password = 0, password_changed_at = NOW(), session_version = session_version + 1 WHERE id = ?');
    $update->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
    session_regenerate_id(true);
    session_set('uid_version', (int) session_get('uid_version') + 1);
    return true;
}

function complete_forced_password_change(int $userId, string $new): bool
{
    if (!validate_password($new)) return false;
    $stmt = db()->prepare('SELECT password_hash, must_change_password FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!is_array($row) || (int) $row['must_change_password'] !== 1 || password_verify($new, (string) $row['password_hash'])) return false;
    $update = db()->prepare('UPDATE users SET password_hash = ?, must_change_password = 0, password_changed_at = NOW(), session_version = session_version + 1 WHERE id = ?');
    $update->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
    session_regenerate_id(true);
    session_set('uid_version', (int) session_get('uid_version') + 1);
    return true;
}

function update_user_profile(int $userId, array $data, ?array $photo = null): array
{
    $name = validate_name((string) ($data['name'] ?? ''));
    $dateOfBirth = (string) ($data['date_of_birth'] ?? '');
    $gender = (string) ($data['gender'] ?? '');
    $divisionId = filter_var($data['division_id'] ?? null, FILTER_VALIDATE_INT);
    $districtId = filter_var($data['district_id'] ?? null, FILTER_VALIDATE_INT);
    $upazilaId = filter_var($data['upazila_id'] ?? null, FILTER_VALIDATE_INT);
    $unionId = ($data['union_id'] ?? '') === '' ? null : filter_var($data['union_id'], FILTER_VALIDATE_INT);
    $cityId = ($data['city_corporation_id'] ?? '') === '' ? null : filter_var($data['city_corporation_id'], FILTER_VALIDATE_INT);
    $cityAreaId = ($data['city_corporation_area_id'] ?? '') === '' ? null : filter_var($data['city_corporation_area_id'], FILTER_VALIDATE_INT);
    $submittedPhone = normalize_phone((string) ($data['phone'] ?? ''));
    $currentPassword = (string) ($data['current_password'] ?? '');
    $village = validate_optional_text($data['village'] ?? null, 150);
    foreach (['divisionId', 'districtId', 'upazilaId', 'unionId', 'cityId', 'cityAreaId'] as $id) {
        if ($$id === false) $$id = null;
    }

    $errors = [];
    $existingUser = db()->prepare('SELECT phone, photo FROM users WHERE id = ?');
    $existingUser->execute([$userId]);
    $storedUser = $existingUser->fetch();
    if (!is_array($storedUser)) throw new InvalidArgumentException('User account was not found.');
    $storedPhone = (string) $storedUser['phone'];
    if ($submittedPhone === null) {
        $errors['phone'] = 'Enter a valid Bangladesh mobile number.';
    } elseif ($submittedPhone !== $storedPhone) {
        $identifier = 'phone-change:' . $userId;
        if (login_is_locked($identifier, 'user')) {
            $errors['current_password'] = 'Too many failed attempts. Please try again later.';
        } else {
            $passwordStmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
            $passwordStmt->execute([$userId]);
            $passwordHash = $passwordStmt->fetchColumn();
        }
        if ($submittedPhone !== null && $submittedPhone !== $storedPhone && !isset($errors['phone']) && !isset($errors['current_password']) && (!is_string($passwordHash ?? null) || !password_verify($currentPassword, $passwordHash))) {
            record_login_failure($identifier, 'user');
            $errors['current_password'] = 'Current password is incorrect.';
        } elseif ($submittedPhone !== null && $submittedPhone !== $storedPhone && !isset($errors['phone']) && !isset($errors['current_password'])) {
            $duplicate = db()->prepare('SELECT COUNT(*) FROM users WHERE phone = ? AND id <> ?');
            $duplicate->execute([$submittedPhone, $userId]);
            if ((int) $duplicate->fetchColumn() > 0) $errors['phone'] = 'This phone number is already registered.';
        }
    }
    if ($name === null) $errors['name'] = 'Enter a valid name.';
    if (!validate_age($dateOfBirth, (int) setting_value('min_age', '18'), (int) setting_value('max_age', '60'))) {
        $errors['date_of_birth'] = 'Enter a valid date within the allowed age range.';
    }
    if (!validate_gender($gender)) $errors['gender'] = 'Select a valid gender.';
    if (!is_int($divisionId) || !is_int($districtId) || (!is_int($upazilaId) && !is_int($cityId))) {
        $errors['location'] = 'Select a valid location.';
    } elseif (!validate_location(db(), $divisionId, $districtId, $upazilaId, is_int($unionId) ? $unionId : null, is_int($cityId) ? $cityId : null, is_int($cityAreaId) ? $cityAreaId : null)) {
        $errors['location'] = 'The selected location hierarchy is invalid.';
    }
    if (($data['village'] ?? '') !== '' && $village === null) $errors['village'] = 'Village must be 150 characters or fewer.';
    if ($errors !== []) throw new InvalidArgumentException(json_encode($errors, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

    $oldPhoto = $storedUser['photo'];

    $newPhoto = is_array($photo) && ($photo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
        ? store_avatar($photo, is_string($oldPhoto) ? $oldPhoto : null, false)
        : (is_string($oldPhoto) ? $oldPhoto : null);
    try {
        db()->beginTransaction();
        $stmt = db()->prepare(
            'UPDATE users
             SET name = ?, phone = ?, date_of_birth = ?, gender = ?, division_id = ?, district_id = ?,
                 upazila_id = ?, union_id = ?, city_corporation_id = ?, city_corporation_area_id = ?, village = ?, photo = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $name, $submittedPhone, $dateOfBirth, $gender, $divisionId, $districtId, $upazilaId,
            $unionId, $cityId, $cityAreaId, $village, $newPhoto, $userId,
        ]);
        if ($submittedPhone !== $storedPhone) {
            $cancel = db()->prepare("UPDATE change_requests SET status = 'cancelled' WHERE user_id = ? AND field_name = 'phone' AND status = 'pending'");
            $cancel->execute([$userId]);
            log_user_activity($userId, 'phone_changed', [
                'old_phone' => mask_phone($storedPhone),
                'new_phone' => mask_phone($submittedPhone),
            ]);
        }
        db()->commit();
    } catch (PDOException $exception) {
        if (db()->inTransaction()) db()->rollBack();
        if ($newPhoto !== $oldPhoto && is_string($newPhoto)) {
            $newPath = dirname(__DIR__, 2) . '/public' . $newPhoto;
            if (is_file($newPath)) unlink($newPath);
        }
        if (($exception->errorInfo[1] ?? null) === 1062) {
            throw new InvalidArgumentException(json_encode(['phone' => 'This phone number is already registered.'], JSON_THROW_ON_ERROR));
        }
        throw $exception;
    } catch (Throwable $exception) {
        if (db()->inTransaction()) db()->rollBack();
        if ($newPhoto !== $oldPhoto && is_string($newPhoto)) {
            $newPath = dirname(__DIR__, 2) . '/public' . $newPhoto;
            if (is_file($newPath)) unlink($newPath);
        }
        throw $exception;
    }
    if ($newPhoto !== $oldPhoto && is_string($oldPhoto)) {
        $oldPath = dirname(__DIR__, 2) . '/public/' . ltrim($oldPhoto, '/');
        if (is_file($oldPath)) unlink($oldPath);
    }
    if ($submittedPhone !== $storedPhone) {
        clear_login_failures('phone-change:' . $userId, 'user');
        start_session();
        session_regenerate_id(true);
        session_set('uid', $userId);
    }
    return ['photo' => $newPhoto, 'phone_changed' => $submittedPhone !== $storedPhone, 'phone' => $submittedPhone];
}

function set_user_availability(int $userId, bool $available): bool
{
    $stmt = db()->prepare('UPDATE users SET available_toggle = ? WHERE id = ?');
    $stmt->execute([$available ? 1 : 0, $userId]);
    return $stmt->rowCount() === 1;
}
