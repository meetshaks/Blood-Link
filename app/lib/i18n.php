<?php
declare(strict_types=1);

function supported_languages(): array
{
    return ['bn', 'en'];
}

function current_lang(): string
{
    $config = bloodlink_config();
    $requested = $_GET['lang'] ?? null;
    $cookie = $_COOKIE['lang'] ?? null;
    $default = $config['default_lang'] ?? 'bn';
    if (function_exists('db')) {
        $storedDefault = db()->query("SELECT setting_value FROM settings WHERE setting_key = 'default_lang'")->fetchColumn();
        if (is_string($storedDefault)) $default = $storedDefault;
    }

    foreach ([$requested, $cookie, $default] as $candidate) {
        if (is_string($candidate) && in_array($candidate, supported_languages(), true)) {
            return $candidate;
        }
    }

    return 'bn';
}

function language_dictionary(?string $language = null): array
{
    static $dictionaries = [];
    $language ??= current_lang();

    if (!isset($dictionaries[$language])) {
        $file = __DIR__ . '/../lang/' . $language . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('Language dictionary not found.');
        }
        $dictionary = require $file;
        if (!is_array($dictionary)) {
            throw new RuntimeException('Invalid language dictionary.');
        }
        $dictionaries[$language] = $dictionary;
    }

    return $dictionaries[$language];
}

function t(string $key, array $vars = [], ?string $language = null): string
{
    $value = language_dictionary($language)[$key] ?? $key;
    foreach ($vars as $name => $replacement) {
        $value = str_replace('{' . $name . '}', (string) $replacement, $value);
    }
    return (string) $value;
}

function num(int|string|float $number): string
{
    $value = (string) $number;
    if (current_lang() !== 'bn') {
        return $value;
    }
    return strtr($value, [
        '0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪',
        '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯',
    ]);
}

function date_fmt(DateTimeInterface|string $date): string
{
    $dateTime = $date instanceof DateTimeInterface
        ? $date
        : new DateTimeImmutable($date);

    if (current_lang() === 'en') {
        return $dateTime->format('j M Y');
    }

    $months = [
        1 => 'জানুয়ারি', 2 => 'ফেব্রুয়ারি', 3 => 'মার্চ', 4 => 'এপ্রিল',
        5 => 'মে', 6 => 'জুন', 7 => 'জুলাই', 8 => 'আগস্ট',
        9 => 'সেপ্টেম্বর', 10 => 'অক্টোবর', 11 => 'নভেম্বর', 12 => 'ডিসেম্বর',
    ];
    return num((int) $dateTime->format('j')) . ' ' .
        $months[(int) $dateTime->format('n')] . ' ' .
        num((int) $dateTime->format('Y'));
}

function language_url(string $language): string
{
    if (!in_array($language, supported_languages(), true)) {
        throw new InvalidArgumentException('Unsupported language.');
    }

    $query = $_GET;
    $query['lang'] = $language;
    $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    return $path . '?' . http_build_query($query);
}
