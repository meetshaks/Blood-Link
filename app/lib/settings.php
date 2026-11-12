<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function all_settings(): array
{
    $rows = db()->query('SELECT setting_key, setting_value FROM settings ORDER BY setting_key')->fetchAll();
    $settings = [];
    foreach ($rows as $row) $settings[(string) $row['setting_key']] = (string) $row['setting_value'];
    return $settings;
}

function update_settings(int $adminId, array $values): void
{
    $allowed = [
        'approval_mode' => ['manual', 'auto'],
        'phone_visibility' => ['public', 'login'],
        'default_lang' => ['bn', 'en'],
    ];
    $numeric = ['donation_gap_days' => [1, 1000], 'min_age' => [1, 100], 'max_age' => [1, 120]];
    $validated = [];
    foreach ($allowed as $key => $options) {
        $value = (string) ($values[$key] ?? '');
        if (!in_array($value, $options, true)) throw new InvalidArgumentException('Invalid setting value.');
        $validated[$key] = $value;
    }
    foreach ($numeric as $key => [$min, $max]) {
        $value = filter_var($values[$key] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value < $min || $value > $max) throw new InvalidArgumentException('Invalid numeric setting.');
        $validated[$key] = (string) $value;
    }
    if ((int) $validated['min_age'] > (int) $validated['max_age']) throw new InvalidArgumentException('Minimum age cannot exceed maximum age.');
    $supportPhone = trim((string) ($values['support_phone'] ?? ''));
    if ($supportPhone !== '' && !preg_match('/^[0-9+\\s]+$/', $supportPhone)) throw new InvalidArgumentException('Invalid support phone.');
    $supportEmail = trim((string) ($values['support_email'] ?? ''));
    if ($supportEmail !== '' && filter_var($supportEmail, FILTER_VALIDATE_EMAIL) === false) throw new InvalidArgumentException('Invalid support email.');
    $supportHours = trim((string) ($values['support_hours'] ?? ''));
    if (mb_strlen($supportHours) > 100) throw new InvalidArgumentException('Support hours are too long.');
    $validated['support_phone'] = $supportPhone;
    $validated['support_email'] = $supportEmail;
    $validated['support_hours'] = $supportHours;
    $stmt = db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    foreach ($validated as $key => $value) $stmt->execute([$key, $value]);
    log_admin_action($adminId, 'update_settings', null, $validated);
}

function create_change_request(int $userId, string $field, string $newValue): void
{
    if ($field !== 'blood_group') throw new InvalidArgumentException('Invalid change request.');
    $stmt = db()->prepare("SELECT {$field} FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $oldValue = $stmt->fetchColumn();
    if (!is_string($oldValue) || $oldValue === $newValue) throw new InvalidArgumentException('The requested value is not valid.');
    if ($field === 'blood_group' && !validate_blood_group($newValue)) throw new InvalidArgumentException('Invalid blood group.');
    if ($field === 'phone') {
        $newValue = normalize_phone($newValue) ?? throw new InvalidArgumentException('Invalid phone number.');
    }
    $pending = db()->prepare('SELECT COUNT(*) FROM change_requests WHERE user_id = ? AND field_name = ? AND status = "pending"');
    $pending->execute([$userId, $field]);
    if ((int) $pending->fetchColumn() > 0) throw new InvalidArgumentException('A request for this field is already pending.');
    db()->prepare('INSERT INTO change_requests (user_id, field_name, old_value, new_value) VALUES (?, ?, ?, ?)')->execute([$userId, $field, $oldValue, $newValue]);
}
