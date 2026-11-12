<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ratelimit.php';

function current_admin(): ?array
{
    $id = session_get('admin_id');
    if (!is_int($id) && !ctype_digit((string) $id)) return null;
    $stmt = db()->prepare('SELECT id, name, username, role, is_active FROM admins WHERE id = ?');
    $stmt->execute([(int) $id]);
    $admin = $stmt->fetch();
    return is_array($admin) && (int) $admin['is_active'] === 1 ? $admin : null;
}

function require_admin(?string $minimumRole = null): array
{
    no_store_headers();
    $admin = current_admin();
    if ($admin === null) {
        redirect('/admin/login.php');
    }
    if ($minimumRole === 'super' && $admin['role'] !== 'super') {
        http_response_code(403);
        require dirname(__DIR__, 2) . '/public/403.php';
        exit;
    }
    return $admin;
}

function attempt_admin_login(string $username, string $password): array
{
    $username = trim($username);
    if ($username === '' || strlen($username) > 60) return ['ok' => false];
    $stmt = db()->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    if (!is_array($admin) || (int) $admin['is_active'] !== 1 || !password_verify($password, (string) $admin['password_hash'])) {
        return ['ok' => false];
    }
    start_session();
    session_regenerate_id(true);
    session_set('admin_id', (int) $admin['id']);
    $update = db()->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = ?');
    $update->execute([(int) $admin['id']]);
    log_admin_action((int) $admin['id'], 'admin_login');
    return ['ok' => true, 'admin' => $admin];
}

function admin_statistics(): array
{
    $stats = [];
    foreach ([
        'total' => 'SELECT COUNT(*) FROM users',
        'approved' => "SELECT COUNT(*) FROM users WHERE status = 'approved'",
        'pending' => "SELECT COUNT(*) FROM users WHERE status = 'pending'",
        'rejected' => "SELECT COUNT(*) FROM users WHERE status = 'rejected'",
        'available' => "SELECT COUNT(*) FROM users WHERE status = 'approved' AND available_toggle = 1",
        'suspended' => 'SELECT COUNT(*) FROM users WHERE is_suspended = 1 AND (suspended_until IS NULL OR suspended_until > CURDATE())',
        'donations' => 'SELECT COUNT(*) FROM donations',
    ] as $key => $sql) {
        $stats[$key] = (int) db()->query($sql)->fetchColumn();
    }
    return $stats;
}

function admin_user_list(array $filters, int $page = 1, int $perPage = 20): array
{
    $page = max(1, $page);
    $perPage = min(50, max(1, $perPage));
    [$whereSql, $params, $fromSql] = admin_user_filter_query($filters);
    $count = db()->prepare("SELECT COUNT(*) FROM {$fromSql} WHERE {$whereSql}");
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $stmt = db()->prepare(
        "SELECT u.id, u.name, u.phone, u.blood_group, u.status, u.is_suspended,
                u.suspended_until, u.available_toggle, u.created_at,
                u.photo, u.last_donation_date, d.name_bn AS district_bn, d.name_en AS district_en,
                wd.name_bn AS work_district_bn, wd.name_en AS work_district_en
         FROM {$fromSql}
         WHERE {$whereSql} ORDER BY u.created_at DESC
         LIMIT ? OFFSET ?"
    );
    foreach ($params as $index => $value) $stmt->bindValue($index + 1, $value);
    $stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, ($page - 1) * $perPage, PDO::PARAM_INT);
    $stmt->execute();
    return ['users' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'per_page' => $perPage];
}

function admin_user_filter_query(array $filters): array
{
    $where = ['1=1'];
    $params = [];
    if (in_array(($filters['status'] ?? ''), ['pending', 'approved', 'rejected'], true)) {
        $where[] = 'u.status = ?';
        $params[] = $filters['status'];
    }
    if (($filters['q'] ?? '') !== '') {
        $where[] = '(u.name LIKE ? OR u.phone LIKE ?)';
        $query = '%' . trim((string) $filters['q']) . '%';
        $params[] = $query;
        $params[] = $query;
    }
    if (validate_blood_group((string) ($filters['blood_group'] ?? ''))) {
        $where[] = 'u.blood_group = ?';
        $params[] = (string) $filters['blood_group'];
    }
    foreach (['division' => 'u.division_id', 'district' => 'u.district_id'] as $key => $column) {
        $value = filter_var($filters[$key] ?? null, FILTER_VALIDATE_INT);
        if ($value !== false && $value > 0) {
            $where[] = "{$column} = ?";
            $params[] = (int) $value;
        }
    }
    $whereSql = implode(' AND ', $where);
    $fromSql = 'users u LEFT JOIN districts d ON d.id = u.district_id
        LEFT JOIN user_locations wl ON wl.user_id = u.id AND wl.is_visible = 1
        LEFT JOIN districts wd ON wd.id = wl.district_id';
    return [$whereSql, $params, $fromSql];
}

function admin_user_export(array $filters): array
{
    [$whereSql, $params, $fromSql] = admin_user_filter_query($filters);
    $stmt = db()->prepare(
        "SELECT u.id, u.name, u.phone, u.blood_group, u.status, u.is_suspended,
                u.suspended_until, u.available_toggle, u.created_at, u.last_donation_date,
                d.name_bn AS district_bn, d.name_en AS district_en,
                wd.name_bn AS work_district_bn, wd.name_en AS work_district_en
         FROM {$fromSql}
         WHERE {$whereSql} ORDER BY u.created_at DESC"
    );
    foreach ($params as $index => $value) $stmt->bindValue($index + 1, $value);
    $stmt->execute();
    return $stmt->fetchAll();
}

function admin_update_user(int $adminId, int $userId, string $action, ?string $reason = null, ?int $months = null): void
{
    $pdo = db();
    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'approved', approved_by = ?, approved_at = NOW(), reject_reason = NULL WHERE id = ?");
        $stmt->execute([$adminId, $userId]);
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'rejected', reject_reason = ? WHERE id = ?");
        $stmt->execute([validate_optional_text($reason, 255), $userId]);
    } elseif ($action === 'suspend') {
        $until = $months !== null && $months > 0 ? (new DateTimeImmutable('today'))->modify("+{$months} months")->format('Y-m-d') : null;
        $stmt = $pdo->prepare('UPDATE users SET is_suspended = 1, suspended_until = ?, suspend_reason = ? WHERE id = ?');
        $stmt->execute([$until, validate_optional_text($reason, 255), $userId]);
    } elseif ($action === 'unsuspend') {
        $stmt = $pdo->prepare('UPDATE users SET is_suspended = 0, suspended_until = NULL, suspend_reason = NULL WHERE id = ?');
        $stmt->execute([$userId]);
    } else {
        throw new InvalidArgumentException('Invalid user action.');
    }
    if ($stmt->rowCount() !== 1) throw new InvalidArgumentException('Donor account was not found or unchanged.');
    log_admin_action($adminId, $action, $userId, ['reason' => $reason]);
}

function admin_delete_user(int $adminId, int $userId): void
{
    $stmt = db()->prepare('SELECT photo FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $photo = $stmt->fetchColumn();
    $delete = db()->prepare('DELETE FROM users WHERE id = ?');
    $delete->execute([$userId]);
    if ($delete->rowCount() !== 1) throw new InvalidArgumentException('Donor account was not found.');
    if (is_string($photo) && str_starts_with($photo, '/uploads/avatars/')) {
        $path = dirname(__DIR__, 2) . '/public' . $photo;
        if (is_file($path)) unlink($path);
    }
    log_admin_action($adminId, 'delete_user', $userId);
}

function generate_temporary_password(): string
{
    $letters = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz';
    $digits = '23456789';
    $chars = $letters[random_int(0, strlen($letters) - 1)] . $digits[random_int(0, strlen($digits) - 1)];
    $alphabet = $letters . $digits;
    while (strlen($chars) < 10) $chars .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    $bytes = random_bytes(10);
    $output = '';
    $remaining = str_split($chars);
    foreach (unpack('C*', $bytes) as $byte) {
        $index = $byte % count($remaining);
        $output .= $remaining[$index];
        array_splice($remaining, $index, 1);
        if ($remaining === []) break;
    }
    return $output;
}

function admin_reset_password(int $adminId, int $userId, string $note = ''): string
{
    $note = validate_optional_text($note, 200) ?? '';
    $rate = session_get('admin_password_resets', []);
    $now = time();
    if (!is_array($rate) || (int) ($rate['started'] ?? 0) < $now - 3600) $rate = ['started' => $now, 'count' => 0];
    if ((int) $rate['count'] >= 5) throw new InvalidArgumentException('Too many password resets. Try again later.');
    $stmt = db()->prepare('SELECT id, phone, status FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!is_array($user) || !in_array($user['status'], ['approved', 'pending', 'rejected'], true)) throw new InvalidArgumentException('Donor account was not found.');
    $password = generate_temporary_password();
    $stmt = db()->prepare('UPDATE users SET password_hash = ?, must_change_password = 1, password_changed_at = NULL, session_version = session_version + 1 WHERE id = ?');
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
    if ($stmt->rowCount() !== 1) throw new InvalidArgumentException('Donor account was not found.');
    clear_login_failures((string) $user['phone'], 'user');
    $rate['count']++;
    session_set('admin_password_resets', $rate);
    log_admin_action($adminId, 'user_password_reset', $userId, ['note' => $note]);
    return $password;
}

function admin_create_user(int $adminId, array $data): string
{
    $name = validate_name((string) ($data['name'] ?? ''));
    $phone = normalize_phone((string) ($data['phone'] ?? ''));
    $blood = (string) ($data['blood_group'] ?? '');
    $dob = (string) ($data['date_of_birth'] ?? '');
    $gender = (string) ($data['gender'] ?? '');
    $division = filter_var($data['division_id'] ?? null, FILTER_VALIDATE_INT);
    $district = filter_var($data['district_id'] ?? null, FILTER_VALIDATE_INT);
    $upazila = filter_var($data['upazila_id'] ?? null, FILTER_VALIDATE_INT);
    $union = ($data['union_id'] ?? '') === '' ? null : filter_var($data['union_id'], FILTER_VALIDATE_INT);
    $city = ($data['city_corporation_id'] ?? '') === '' ? null : filter_var($data['city_corporation_id'], FILTER_VALIDATE_INT);
    $cityArea = ($data['city_corporation_area_id'] ?? '') === '' ? null : filter_var($data['city_corporation_area_id'], FILTER_VALIDATE_INT);
    foreach (['upazila', 'union', 'city', 'cityArea'] as $key) if ($$key === false) $$key = null;
    $errors = [];
    if ($name === null) $errors['name'] = 'Enter a valid name.';
    if ($phone === null) $errors['phone'] = 'Enter a valid Bangladesh mobile number.';
    if (!validate_blood_group($blood)) $errors['blood_group'] = 'Select a valid blood group.';
    if (!validate_age($dob, (int) setting_value('min_age', '18'), (int) setting_value('max_age', '60'))) $errors['date_of_birth'] = 'Enter a valid date.';
    if (!validate_gender($gender)) $errors['gender'] = 'Select a valid gender.';
    if (!is_int($division) || !is_int($district) || (!is_int($upazila) && !is_int($city)) || !validate_location(db(), $division, $district, $upazila, $union, $city, $cityArea)) $errors['location'] = 'Select a valid location.';
    $hasWorkLocation = trim((string) ($data['work_district_id'] ?? '')) !== '';
    if ($hasWorkLocation) {
        try {
            validate_work_location($data);
        } catch (InvalidArgumentException $exception) {
            $workErrors = json_decode($exception->getMessage(), true);
            if (is_array($workErrors)) $errors = array_merge($errors, $workErrors);
        }
    }
    if ($errors !== []) throw new InvalidArgumentException(json_encode($errors, JSON_THROW_ON_ERROR));
    $password = generate_temporary_password();
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $pdo->prepare(
            "INSERT INTO users (name, phone, password_hash, blood_group, date_of_birth, gender,
             division_id, district_id, upazila_id, union_id, city_corporation_id, city_corporation_area_id, village, status, approved_by, approved_at,
             registered_by, created_by_admin_id, must_change_password)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, NOW(), 'admin', ?, 1)"
        )->execute([$name, $phone, password_hash($password, PASSWORD_DEFAULT), $blood, $dob, $gender, $division, $district, $upazila, $union, $city, $cityArea, validate_optional_text($data['village'] ?? null, 150), $adminId, $adminId]);
        $id = (int) $pdo->lastInsertId();
        if ($hasWorkLocation) save_user_work_location($id, $data);
        $pdo->commit();
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (($exception->errorInfo[1] ?? null) === 1062) throw new InvalidArgumentException(json_encode(['phone' => 'This phone number is already registered.'], JSON_THROW_ON_ERROR));
        throw $exception;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
    log_admin_action($adminId, 'create_user', $id);
    return $password;
}

/** Approved donors per blood group (all 8 groups, zero-filled) — read-only, for the admin chart. */
function admin_blood_group_counts(): array
{
    $counts = array_fill_keys(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], 0);
    $rows = db()->query("SELECT blood_group, COUNT(*) AS total FROM users WHERE status = 'approved' GROUP BY blood_group")->fetchAll();
    foreach ($rows as $row) {
        if (array_key_exists((string) $row['blood_group'], $counts)) $counts[(string) $row['blood_group']] = (int) $row['total'];
    }
    return $counts;
}

/** Most recent registrations — read-only, for the admin dashboard. */
function admin_recent_users(int $limit = 10): array
{
    $stmt = db()->prepare(
        'SELECT u.id, u.name, u.phone, u.blood_group, u.status, u.created_at, u.photo,
                d.name_bn AS district_bn, d.name_en AS district_en
         FROM users u LEFT JOIN districts d ON d.id = u.district_id
         ORDER BY u.created_at DESC, u.id DESC LIMIT ?'
    );
    $stmt->bindValue(1, max(1, min(50, $limit)), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function admin_district_blood_matrix(?int $divisionId = null): array
{
    $groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    $where = "u.status = 'approved'";
    $params = [];
    if ($divisionId !== null && $divisionId > 0) {
        $where .= ' AND d.division_id = ?';
        $params[] = $divisionId;
    }
    $stmt = db()->prepare(
        "SELECT d.id, d.name_bn, d.name_en, u.blood_group, COUNT(*) AS total
         FROM users u JOIN districts d ON d.id = u.district_id
         WHERE {$where}
         GROUP BY d.id, d.name_bn, d.name_en, u.blood_group
         ORDER BY d.name_bn"
    );
    $stmt->execute($params);
    $matrix = [];
    foreach ($stmt->fetchAll() as $row) {
        $id = (int) $row['id'];
        if (!isset($matrix[$id])) {
            $matrix[$id] = ['id' => $id, 'name_bn' => $row['name_bn'], 'name_en' => $row['name_en'], 'counts' => array_fill_keys($groups, 0)];
        }
        if (array_key_exists((string) $row['blood_group'], $matrix[$id]['counts'])) $matrix[$id]['counts'][(string) $row['blood_group']] = (int) $row['total'];
    }
    if ($divisionId !== null && $divisionId > 0) {
        $districts = db()->prepare('SELECT id, name_bn, name_en FROM districts WHERE division_id = ? ORDER BY name_bn');
        $districts->execute([$divisionId]);
    } else {
        $districts = db()->query('SELECT id, name_bn, name_en FROM districts ORDER BY name_bn');
    }
    foreach ($districts->fetchAll() as $district) {
        $id = (int) $district['id'];
        $matrix[$id] ??= ['id' => $id, 'name_bn' => $district['name_bn'], 'name_en' => $district['name_en'], 'counts' => array_fill_keys($groups, 0)];
    }
    return array_values($matrix);
}

function public_statistics(): array
{
    static $memory = null;
    static $cachedAt = 0;
    if (is_array($memory) && time() - $cachedAt < 300) return $memory;

    $cacheFile = __DIR__ . '/../logs/public-statistics.cache.php';
    if (is_file($cacheFile) && (time() - (int) filemtime($cacheFile)) < 300) {
        $cached = @include $cacheFile;
        if (is_array($cached) && isset($cached['approved'], $cached['available'], $cached['donations'], $cached['districts'])) {
            $memory = $cached;
            $cachedAt = time();
            return $memory;
        }
    }

    $gap = max(1, (int) setting_value('donation_gap_days', '120'));
    $stats = [
        'approved' => (int) db()->query("SELECT COUNT(*) FROM users WHERE status = 'approved'")->fetchColumn(),
        'available' => 0,
        'donations' => (int) db()->query('SELECT COUNT(*) FROM donations')->fetchColumn(),
        'districts' => 0,
    ];
    $available = db()->prepare(
        "SELECT COUNT(*) FROM users
         WHERE status = 'approved' AND available_toggle = 1
           AND (is_suspended = 0 OR suspended_until IS NOT NULL AND suspended_until <= CURDATE())
           AND (last_donation_date IS NULL OR DATEDIFF(CURDATE(), last_donation_date) >= ?)"
    );
    $available->execute([$gap]);
    $stats['available'] = (int) $available->fetchColumn();
    $stats['districts'] = (int) db()->query("SELECT COUNT(DISTINCT district_id) FROM users WHERE status = 'approved'")->fetchColumn();

    $memory = $stats;
    $cachedAt = time();
    $payload = "<?php\nreturn " . var_export($stats, true) . ";\n";
    @file_put_contents($cacheFile, $payload, LOCK_EX);
    return $stats;
}
