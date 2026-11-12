<?php
declare(strict_types=1);

require_once __DIR__ . '/settings.php';

function support_contact(): array
{
    static $contact;
    if (is_array($contact)) return $contact;

    $settings = all_settings();
    $phone = trim((string) ($settings['support_phone'] ?? ''));
    $email = trim((string) ($settings['support_email'] ?? ''));
    $hours = trim((string) ($settings['support_hours'] ?? ''));
    $tel = preg_match('/^[0-9+\\s-]+$/', $phone) === 1
        ? preg_replace('/[^0-9+]/', '', $phone)
        : '';
    $contact = [
        'phone' => $phone,
        'phone_href' => $tel === '' ? '' : 'tel:' . $tel,
        'email' => filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : '',
        'email_href' => filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? 'mailto:' . $email : '',
        'hours' => $hours,
    ];
    return $contact;
}
