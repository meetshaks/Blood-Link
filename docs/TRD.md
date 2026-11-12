# BloodLink TRD — টেকনিক্যাল রিকোয়ারমেন্ট ডকুমেন্ট

**স্ট্যাক:** HTML5 · CSS3 · Vanilla JavaScript (ES6+) · Plain PHP 8.1+ · MySQL 8 / MariaDB 10.6+
**ফ্রেমওয়ার্ক:** কোনোটি নয় (plain PHP)। লাইব্রেরি ন্যূনতম।

---

## ১. আর্কিটেকচার

```
ব্রাউজার (HTML/CSS/JS)
      │  HTTP(S)
      ▼
Apache/Nginx + PHP 8.1 (PHP-FPM বা mod_php)
      │  PDO (prepared statements)
      ▼
MySQL / MariaDB (utf8mb4, +06:00)
```

- **মাল্টি-পেজ অ্যাপ:** প্রতিটি পেজ আলাদা PHP ফাইল; সার্ভার-সাইড রেন্ডারিং।
- **AJAX (fetch API):** ড্রপডাউন চেইন, সার্চ রেজাল্ট, availability টগল, JSON রেসপন্স।
- **সেশন:** PHP native session (কুকি-ভিত্তিক)।
- **ফ্রেমওয়ার্ক ছাড়া** হলেও কোড সংগঠিত রাখতে `app/lib/`, `app/views/` ও `app/config/`-এ ছোট ছোট হেল্পার ফাইল।

## ২. পরিবেশ ও নির্ভরতা

| বিষয় | সংস্করণ/নোট |
|--------|----------------|
| PHP | ≥ 8.1; এক্সটেনশন: `pdo_mysql`, `mbstring`, `fileinfo`, `gd` (ছবি রিসাইজ), `json` |
| MySQL | ≥ 8.0 অথবা MariaDB ≥ 10.6 (JSON কলামের জন্য) |
| ওয়েব সার্ভার | Apache (`.htaccess` সহ) অথবা Nginx |
| লোকাল ডেভ | XAMPP / Laragon |
| ফ্রন্টএন্ড লাইব্রেরি | ঐচ্ছিক: কিছুই বাধ্যতামূলক নয়; ফন্ট Google Fonts (ফলব্যাক সহ) |
| HTTPS | প্রোডাকশনে বাধ্যতামূলক |

## ৩. ফোল্ডার কাঠামো

শেয়ার্ড হোস্টিংয়ের জন্য ডকুমেন্ট রুটের **বাইরে** `app/` রাখা ভালো; সম্ভব না হলে `.htaccess` দিয়ে সুরক্ষিত।

```
bloodlink/
├── app/                              # ওয়েব রুটের বাইরে (বা .htaccess দিয়ে সুরক্ষিত)
│   ├── config/
│   │   ├── config.php                # ডাটাবেস, বেস URL, ফ্ল্যাগ (git-এ রাখা হবে না)
│   │   ├── config.sample.php
│   │   └── database.php              # সহজ shared PDO connection ($conn)
│   ├── lib/
│   │   ├── db.php                    # PDO কানেকশন
│   │   ├── auth.php                  # সেশন, লগইন, রোল গার্ড
│   │   ├── csrf.php                  # টোকেন
│   │   ├── i18n.php                  # t(), ভাষা, সংখ্যা/তারিখ ফরম্যাট
│   │   ├── validate.php              # ভ্যালিডেশন হেল্পার
│   │   ├── upload.php                # ছবি আপলোড ও রিসাইজ
│   │   ├── settings.php              # সেটিংস লোড (ক্যাশ সহ)
│   │   ├── donors.php                # সার্চ/ব্যাজ লজিক
│   │   ├── ratelimit.php             # লগইন লক
│   │   └── logger.php                # admin_logs
│   ├── lang/
│   │   ├── bn.php
│   │   └── en.php
│   └── views/
│       ├── layout/{header,footer,admin_sidebar}.php
│       └── partials/{donor_card,filters,pagination}.php
│
├── public/                           # ডকুমেন্ট রুট
│   ├── index.php                     # হোম + সার্চ
│   ├── register.php
│   ├── login.php
│   ├── logout.php
│   ├── about.php
│   ├── user/{dashboard,profile,history,password}.php
│   ├── admin/{login,dashboard,users,user_add,user_edit,pending,settings,logs}.php
│   ├── api/
│   │   ├── geo.php                   # ?type=districts&parent_id=
│   │   ├── search.php
│   │   ├── toggle_available.php
│   │   └── donation.php              # add/delete
│   ├── assets/
│   │   ├── css/{base,components,pages,admin}.css
│   │   ├── js/{app,search,register,admin}.js
│   │   └── img/
│   └── uploads/avatars/              # PHP এক্সিকিউশন বন্ধ (.htaccess)
│
├── tools/
│   └── import_geo.php                # JSON → লোকেশন টেবিল (CLI)
├── database/
│   ├── schema.sql
│   └── seed_admin.sql
├── docs/                             # AppFlow, UI_Spec, TRD, Schema, Plan
└── README.md
```

## ৪. কনফিগারেশন

`app/config/config.php`:

```php
<?php
return [
    'db' => [
        'host' => '127.0.0.1',
        'name' => 'bloodlink',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'base_url'   => 'https://example.com',
    'timezone'   => 'Asia/Dhaka',
    'debug'      => false,          // প্রোডাকশনে false
    'upload_max' => 2 * 1024 * 1024,
    'session_name' => 'bloodlink_session',
    'session_secure' => true,      // লোকাল HTTP-তে false; প্রোডাকশনে HTTPS-এ true
];
```

## ৫. মূল মডিউল

### ৫.০ সহজ PDO কানেকশন (`app/config/database.php`)

প্রজেক্টে সহজভাবে `$conn` ব্যবহার করতে চাইলে প্রতিটি পেজে নতুন কানেকশন না খুলে এই shared ফাইলটি include করতে হবে। কনফিগারেশন `config.php` থেকে আসবে, তাই credentials এক জায়গাতেই থাকবে।

```php
<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';
$db = $config['db'];

try {
    $conn = new PDO(
        "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}",
        $db['user'],
        $db['pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    $conn->exec("SET time_zone = '+06:00'");
} catch (PDOException $e) {
    error_log('BloodLink database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed.');
}
```

ব্যবহার:

```php
require __DIR__ . '/../app/config/database.php';
$stmt = $conn->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();
```

`db()` helper ব্যবহার করলে একই কাজের cached connection পাওয়া যাবে। একটি request-এ `$conn` ও `db()` মিশিয়ে ব্যবহার না করে প্রজেক্টে একটি style অনুসরণ করতে হবে। Production-এ database error সরাসরি browser-এ দেখানো যাবে না; বিস্তারিত error শুধু server log-এ থাকবে।

### ৫.১ ডাটাবেস (`lib/db.php`)

```php
<?php
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = require __DIR__ . '/../config/config.php';
    $dsn = "mysql:host={$c['db']['host']};dbname={$c['db']['name']};charset={$c['db']['charset']}";
    $pdo = new PDO($dsn, $c['db']['user'], $c['db']['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec("SET time_zone = '+06:00'");
    return $pdo;
}
```

- সব কোয়েরি **prepared statement**; ইউজার ইনপুট কখনও SQL স্ট্রিংয়ে সরাসরি নয়।
- `LIMIT/OFFSET` ইন্টিজার কাস্ট করে বাইন্ড।
- `date_default_timezone_set('Asia/Dhaka')` প্রতিটি এন্ট্রি পয়েন্টে (বুটস্ট্র্যাপে)।

### ৫.২ অথেনটিকেশন ও অথরাইজেশন (`lib/auth.php`)

- পাসওয়ার্ড: `password_hash($p, PASSWORD_DEFAULT)` / `password_verify`; সফল লগইনে `password_needs_rehash` চেক।
- সেশন কুকি সেটিংস (`session_secure`-এর মান কনফিগারেশন থেকে নিতে হবে):
  ```php
  session_set_cookie_params([
      'lifetime' => 0,
      'path'     => '/',
      'secure'   => $c['session_secure'],
      'httponly' => true,
      'samesite' => 'Lax',
  ]);
  ```
- লগইন সফল হলে `session_regenerate_id(true)`।
- গার্ড ফাংশন:
  - `require_user()` — লগইন করা `approved` ইউজার; `must_change_password` হলে `/user/password.php`-এ পাঠায়
  - `require_admin()` — অ্যাডমিন সেশন
  - `require_super()` — সুপার অ্যাডমিন (সেটিংস, অ্যাডমিন ম্যানেজমেন্ট)
- ইউজার সেশন ও অ্যাডমিন সেশন আলাদা কী (`$_SESSION['uid']` বনাম `$_SESSION['aid']`)।
- লগইন লক: `login_attempts` টেবিলে ১৫ মিনিটে ৫ বার ব্যর্থ হলে লক; একই বার্তা সবসময় ("নম্বর বা পাসওয়ার্ড ভুল") যাতে ইউজার আছে কিনা বোঝা না যায়।

### ৫.৩ CSRF (`lib/csrf.php`)

- সেশনে র‍্যান্ডম টোকেন (`bin2hex(random_bytes(32))`)।
- প্রতিটি POST ফর্মে হিডেন ফিল্ড; AJAX POST-এ `X-CSRF-Token` হেডার।
- `hash_equals` দিয়ে যাচাই; ব্যর্থ হলে HTTP 419।

### ৫.৪ ভ্যালিডেশন (`lib/validate.php`)

সার্ভার-সাইডে সবসময় (ক্লায়েন্ট ভ্যালিডেশন শুধু UX-এর জন্য):

| ফিল্ড | নিয়ম |
|--------|-------|
| ফোন | `preg_match('/^01[3-9]\d{8}$/')`; ইনপুটে `+880`/`880` আসলে স্বাভাবিকীকরণ |
| নাম | ২–১২০ অক্ষর, `mb_strlen` |
| পাসওয়ার্ড | ন্যূনতম ৮ অক্ষর |
| রক্তের গ্রুপ | অনুমোদিত ৮ মানের মধ্যে |
| জন্ম তারিখ | বৈধ তারিখ; বয়স `min_age`–`max_age` |
| লিঙ্গ | `male/female/other` |
| লোকেশন | আইডি বাস্তবে আছে এবং হায়ারার্কি মিলছে (জেলা ∈ বিভাগ, উপজেলা ∈ জেলা, ইউনিয়ন ∈ উপজেলা) |
| গ্রাম | ঐচ্ছিক, ≤ ১৫০ অক্ষর |
| রক্তদানের তারিখ | বৈধ, ভবিষ্যতের নয় |

### ৫.৫ ছবি আপলোড (`lib/upload.php`)

1. `$_FILES['photo']['error'] === UPLOAD_ERR_OK` ও সাইজ ≤ ২ MB
2. `finfo_file` দিয়ে আসল MIME যাচাই (`image/jpeg`, `image/png`, `image/webp`); এক্সটেনশনে ভরসা নয়
3. `getimagesize` দিয়ে বৈধ ছবি নিশ্চিত
4. GD দিয়ে সর্বোচ্চ ৪০০×৪০০-এ রিসাইজ ও JPEG/WebP-এ পুনরায় এনকোড (এতে এমবেডেড কোড মুছে যায়)
5. ফাইলনেম: `bin2hex(random_bytes(16)) . '.jpg'`
6. `uploads/avatars/` -এ `.htaccess`:
   ```
   php_flag engine off
   RemoveHandler .php .phtml .php3 .php5
   Options -Indexes
   ```
7. পুরনো ছবি বদলালে মুছে ফেলা

### ৫.৬ ভাষা (`lib/i18n.php`)

```php
<?php
function current_lang(): string {
    $lang = $_GET['lang'] ?? $_COOKIE['lang'] ?? setting('default_lang', 'bn');
    return in_array($lang, ['bn','en'], true) ? $lang : 'bn';
}
function t(string $key, array $vars = []): string {
    static $dict = null;
    if ($dict === null) $dict = require __DIR__ . '/../lang/' . current_lang() . '.php';
    $s = $dict[$key] ?? $key;
    foreach ($vars as $k => $v) $s = str_replace('{' . $k . '}', (string)$v, $s);
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
function num(int|string $n): string {            // বাংলা মোডে বাংলা অঙ্ক
    if (current_lang() !== 'bn') return (string)$n;
    return strtr((string)$n, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯']);
}
function loc_name(array $row, string $prefix): string {   // name_bn / name_en
    $k = $prefix . '_' . current_lang();
    return $row[$k] ?: ($row[$prefix . '_bn'] ?: $row[$prefix . '_en']);
}
```

- ভাষা বদলালে `?lang=bn|en`-এর মান whitelist করে কুকি `lang` (১ বছর) সেট করতে হবে এবং লগইন করা ইউজারের হলে `preferred_lang` আপডেট করতে হবে। এরপর একই পেজে `lang` query বাদ দিয়ে redirect করতে হবে, যাতে শেয়ার করা URL-এ ভাষা-পরিবর্তনের অস্থায়ী প্যারামিটার না থাকে।
- মাস/তারিখ ফরম্যাট: বাংলা মাসের নামের অ্যারে (জানুয়ারি…ডিসেম্বর), `date_fmt()` হেল্পার।
- `<html lang="<?= current_lang() ?>">`।

### ৫.৭ সার্চ লজিক (`lib/donors.php`)

- ফাংশন: `search_donors(array $filters, int $page, int $perPage = 12): array`
- ফিল্টার সংগ্রহ → **হোয়াইটলিস্ট** করা কলাম নামে শর্ত তৈরি → প্যারামিটার বাইন্ড (Schema.md § ৪.১ কোয়েরি)।
- মোট ফল গণনায় শুধু প্রয়োজনীয় `users` query চলে; location filter থাকলেই effective home/work matching-এর জন্য `user_locations` join করা হয়।
- `perPage` ১–৫০-এর মধ্যে সীমাবদ্ধ; count query-র পর চাওয়া `page` শেষ ফলাফল পৃষ্ঠার বেশি হলে শেষ পৃষ্ঠায় clamp হবে, ফলে অকার্যকর বড় `OFFSET` তৈরি হবে না।
- সার্চ UI ফিল্টার পরিবর্তনে ১৫০ms debounce এবং আগের চলমান অনুরোধ বাতিল করে; submit/pagination-এর সরাসরি অনুরোধ pending debounce-ও পরিষ্কার করে।
- `gap` সেটিং থেকে (ডিফল্ট ১২০)।
- ফোন নম্বর: 🟡 হলে SQL থেকেই `NULL` আসে (ভুল করে ফাঁস হওয়ার সুযোগ নেই)। `phone_visibility = 'login'` হলে এবং ভিজিটর লগইন না থাকলে PHP-তে `null`।
- রেসপন্স JSON:

```json
{
  "ok": true,
  "total": 37,
  "page": 1,
  "per_page": 12,
  "donors": [
    {
      "id": 15,
      "name": "রহিম উদ্দিন",
      "photo": "/uploads/avatars/ab12….jpg",
      "blood_group": "B+",
      "age": 32,
      "gender": "male",
      "location": "ধামরাই, ঢাকা",
      "village": "কুশুরা",
      "last_donation_date": "2026-06-12",
      "is_available": true,
      "days_left": 0,
      "phone": "01712345678"
    }
  ]
}
```

### ৫.৮ রক্তদান ও `last_donation_date` সিঙ্ক

```php
function add_donation(int $uid, string $date, ?string $place, ?string $note, string $by = 'user'): void {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO donations (user_id, donation_date, place, note, added_by)
                       VALUES (?,?,?,?,?)")->execute([$uid, $date, $place, $note, $by]);
        sync_last_donation($pdo, $uid);
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
}
function sync_last_donation(PDO $pdo, int $uid): void {
    $pdo->prepare("UPDATE users SET last_donation_date =
                   (SELECT MAX(donation_date) FROM donations WHERE user_id = ?) WHERE id = ?")
        ->execute([$uid, $uid]);
}
```

(ডুপ্লিকেট তারিখে `UNIQUE` কনস্ট্রেইন্ট ব্যতিক্রম ছুড়বে → ব্যবহারকারীকে বন্ধুত্বপূর্ণ বার্তা।)

### ৫.৯ Availability টগল API

`POST /api/toggle_available.php` — বডি: `{ "available": 0|1 }`, হেডার `X-CSRF-Token`

- শুধু লগইন করা ইউজারের নিজের রেকর্ড আপডেট (সেশন `uid` ব্যবহার; ক্লায়েন্ট থেকে `user_id` নেওয়া হয় না)।
- রেসপন্স: `{ "ok": true, "available_toggle": 0 }`
- Idempotent।

### ৫.১০ অ্যাডমিন অ্যাকশন

- প্রতিটি অ্যাকশন POST + CSRF + `require_admin()`।
- সফল অ্যাকশনের পর `logger.php` দিয়ে `admin_logs` এ এন্ট্রি (`approve`, `reject`, `suspend`, `unsuspend`, `create_user`, `edit_user`, `delete_user`, `reset_password`, `update_settings`)।
- অ্যাডমিন দিয়ে ডোনার যুক্ত: অস্থায়ী পাসওয়ার্ড `random_bytes` দিয়ে তৈরি (৮–১০ অক্ষর, বিভ্রান্তিকর অক্ষর `0/O/1/l` ছাড়া); `must_change_password = 1`; পাসওয়ার্ড একবারই স্ক্রিনে দেখানো, ডাটাবেসে শুধু হ্যাশ।
- Suspend: `is_suspended=1`, `suspended_until` (নির্দিষ্ট মাস যোগ করে) অথবা `NULL` (স্থায়ী)।
- ডিলিট: কনফার্মেশন মডাল; ছবি ফাইলও মোছা হয়।
- রক্তের গ্রুপ/নম্বর পরিবর্তনের অনুরোধ (`change_requests`) অ্যাডমিন অনুমোদন করলে `users` আপডেট হয়।

## ৬. API এন্ডপয়েন্ট সারাংশ

| মেথড | পাথ | অথ | বিবরণ |
|-------|------|-----|--------|
| GET | `/api/geo.php?type=districts&parent_id=1` | পাবলিক | `type`: `districts`/`city_corporations`/`upazilas`/`unions`; হোয়াইটলিস্টেড |
| GET | `/api/search.php?bg=B%2B&division=&district=&upazila=&union=&only_available=1&page=1` | পাবলিক | সার্চ; রেট লিমিট (প্রতি IP প্রতি মিনিটে ৩০) |
| POST | `/api/toggle_available.php` | ইউজার | টগল |
| POST | `/api/donation.php` (`action=add|delete`) | ইউজার | রক্তদান যোগ/মোছা |
| POST | `/admin/*` অ্যাকশন | অ্যাডমিন | approve, reject, suspend ইত্যাদি |

**রেসপন্স ফরম্যাট (ত্রুটি):**
```json
{ "ok": false, "error": "validation", "fields": { "phone": "নম্বর সঠিক নয়" } }
```
HTTP কোড: 200, 400 (ভ্যালিডেশন), 401/403 (অথ), 419 (CSRF), 429 (রেট লিমিট), 500 (সার্ভার)।

## ৭. ফ্রন্টএন্ড বাস্তবায়ন

- **Vanilla JS মডিউল:** `search.js`, `register.js`, `admin.js`; কোনো বিল্ড স্টেপ নেই।
- **ড্রপডাউন চেইন:** `geo.php` থেকে ফেচ; উপরের স্তর বদলালে নিচেরগুলো রিসেট ও ডিসেবল; ফেচ চলাকালীন স্পিনার।
- **সার্চ:** `fetch` + `AbortController` (দ্রুত পরপর সার্চে আগেরটি বাতিল); URL-এ ফিল্টার সিঙ্ক (`history.replaceState`) যাতে লিংক শেয়ার করা যায়।
- **টেমপ্লেটিং:** সার্ভার থেকে JSON এলে JS-এ `textContent` দিয়ে DOM তৈরি (`innerHTML` ব্যবহার না করে XSS এড়ানো)।
- **CSS:** CSS Variables (টোকেন), মোবাইল-ফার্স্ট মিডিয়া কোয়েরি; BEM-সদৃশ ক্লাস নাম।
- **ফন্ট লোড:** `font-display: swap`; ফলব্যাক ফন্ট স্ট্যাক।
- **অ্যাসেট ক্যাশ:** CSS/JS ফাইলে `?v=<ফাইলের mtime>`।

## ৮. নিরাপত্তা চেকলিস্ট

| ঝুঁকি | প্রতিরোধ |
|--------|-----------|
| SQL Injection | PDO prepared statements; `ATTR_EMULATE_PREPARES = false`; কলাম/সর্ট নাম হোয়াইটলিস্ট |
| XSS | আউটপুটে `htmlspecialchars`; JS-এ `textContent`; `Content-Security-Policy` হেডার |
| CSRF | টোকেন + `SameSite=Lax` কুকি |
| সেশন হাইজ্যাক/ফিক্সেশন | `HttpOnly`, `Secure`, লগইনে `session_regenerate_id(true)`, লগআউটে সেশন ধ্বংস |
| ব্রুট ফোর্স | ৫ ব্যর্থ চেষ্টায় ১৫ মিনিট লক; অ্যাডমিন লগইনেও |
| পাসওয়ার্ড | bcrypt/argon2 (`PASSWORD_DEFAULT`); কখনও প্লেইনটেক্সট লগ বা ইমেইলে নয় |
| ফাইল আপলোড | MIME যাচাই, রিসাইজ/রি-এনকোড, র‍্যান্ডম নাম, আপলোড ফোল্ডারে PHP বন্ধ |
| ডেটা ফাঁস | ফোন নম্বর শুধু 🟢 কার্ডে; বাল্ক এক্সপোর্ট নেই; সার্চ API রেট লিমিটেড |
| IDOR | ইউজার API-তে `user_id` সেশন থেকে; প্রোফাইল/রক্তদান এডিটে মালিকানা যাচাই |
| এরর ফাঁস | `display_errors=0`, লগ ফাইলে লেখা; ব্যবহারকারীকে সাধারণ বার্তা |
| HTTP হেডার | `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, HSTS |
| ডিরেক্টরি | `Options -Indexes`; `app/` ও `config/` ওয়েব থেকে অপ্রাপ্য |
| ক্লিকজ্যাকিং | `X-Frame-Options` / `frame-ancestors 'none'` |
| প্রাইভেসি | নম্বরে `rel="nofollow"`/`noindex` (সার্চ API পথ robots.txt-এ ব্লক), সার্চ ইঞ্জিন যেন ডোনারের তথ্য ইন্ডেক্স না করে |

**.htaccess (নমুনা):**
```apache
Options -Indexes
<IfModule mod_headers.c>
  Header always set X-Content-Type-Options "nosniff"
  Header always set X-Frame-Options "DENY"
  Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>
php_flag display_errors off
```

## ৯. পারফরম্যান্স

- সার্চের `WHERE` শর্ত `idx_search_*` ইনডেক্সের সাথে মেলানো (Schema.md); `EXPLAIN` দিয়ে যাচাই।
- লোকেশন ডাটা প্রায় স্থির: `geo.php` রেসপন্সে `Cache-Control: public, max-age=86400`।
- `settings` টেবিল প্রতি রিকোয়েস্টে একবার লোড (স্ট্যাটিক ক্যাশ)।
- ছবি ছোট করে সংরক্ষণ (≤ ৪০০px) ও `loading="lazy"`।
- পেজিনেশন (১২/পেজ), কখনও সব ডোনার একসাথে নয়।
- লক্ষ্য: ১০,০০০ ডোনারে সার্চ < ২ সেকেন্ড।

## ১০. লগিং ও মনিটরিং

- PHP ত্রুটি `error_log`-এ; ফাইল `app/logs/` (ওয়েব থেকে অপ্রাপ্য)।
- ব্যবসায়িক ইভেন্ট: `admin_logs` টেবিল।
- ব্যর্থ লগইন: `login_attempts`।
- (ঐচ্ছিক) আপটাইম মনিটর (UptimeRobot)।

## ১১. টেস্টিং কৌশল

| ধরন | কী যাচাই | কীভাবে |
|------|-----------|--------|
| ইউনিট (ম্যানুয়াল স্ক্রিপ্ট/PHPUnit ঐচ্ছিক) | ১২০ দিনের লজিক, ভ্যালিডেশন, `num()`, ফোন স্বাভাবিকীকরণ | PHP CLI টেস্ট স্ক্রিপ্ট |
| ইন্টিগ্রেশন | রেজিস্টার→অনুমোদন→সার্চে আসা; রক্তদান→🟡; টগল OFF→বাদ | ম্যানুয়াল + টেস্ট ডাটা |
| নিরাপত্তা | SQLi/XSS/CSRF/আপলোড পেলোড, রেট লিমিট | ম্যানুয়াল চেকলিস্ট |
| UI/রেসপন্সিভ | ৩২০, ৩৭৫, ৭৬৮, ১২৮০px | ব্রাউজার ডেভটুলস + আসল ফোন |
| ভাষা | বাংলা/English দুই মোডে সব পেজ, দীর্ঘ টেক্সট ভাঙছে কিনা | ম্যানুয়াল |
| ডেটা | ৫০০০+ নকল ডোনার জেনারেট করে সার্চ গতি | সিড স্ক্রিপ্ট |

### মূল টেস্ট কেস

1. ডোনার কখনও রক্ত দেননি → 🟢
2. শেষ রক্তদান ১১৯ দিন আগে → 🟡 "আর ১ দিন"; ১২০ দিন আগে → 🟢
3. টগল OFF → কোনো মোডেই সার্চে আসে না
4. Manual মোডে নতুন রেজিস্ট্রেশন → সার্চে আসে না, Approve করলে আসে
5. Auto মোডে নতুন রেজিস্ট্রেশন → সাথে সাথে সার্চে (যদি শর্ত মেলে)
6. সাসপেন্ড (৩ মাস) → বাদ; মেয়াদ পেরোলে ফেরত; স্থায়ী → Unsuspend পর্যন্ত বাদ
7. 🟡 কার্ডের JSON-এ `phone` নেই
8. ভুল জেলা-উপজেলা জোড়া POST করলে ভ্যালিডেশন ত্রুটি
9. অন্য ইউজারের `user_id` পাঠিয়ে টগল/রক্তদান বদলানো যায় না
10. অ্যাডমিন যুক্ত ডোনারের প্রথম লগইনে পাসওয়ার্ড পরিবর্তন বাধ্যতামূলক
11. ৫ বার ভুল পাসওয়ার্ডে লক
12. `.php` ফাইল ছবি সেজে আপলোড করলে প্রত্যাখ্যান
13. রক্তদান মুছলে `last_donation_date` ঠিকমতো পিছিয়ে যায়
14. ভবিষ্যতের তারিখে রক্তদান যোগ করা যায় না

## ১২. ডিপ্লয়মেন্ট

**ধাপ:**
1. হোস্টিং: PHP 8.1+, MySQL, HTTPS (Let's Encrypt)
2. ডাটাবেস তৈরি → `database/schema.sql` ইমপোর্ট
3. `php tools/import_geo.php Assets/bangladesh-geo.json` (অথবা phpMyAdmin/SSH-এ)
4. `database/seed_admin.sql` বা CLI স্ক্রিপ্ট দিয়ে প্রথম সুপার অ্যাডমিন তৈরি (পাসওয়ার্ড হ্যাশসহ)
5. `config.php` পূরণ; `debug=false`
6. ফোল্ডার পারমিশন: `uploads/` লেখার যোগ্য; বাকি সব রিড-ওনলি
7. ব্যাকআপ ক্রন (`mysqldump` দৈনিক)
8. ফাইনাল চেক: HTTPS রিডাইরেক্ট, এরর পেজ, সেশন কুকি `Secure`
9. `public/robots.txt`: `Disallow: /api/`, `/admin/`, `/user/`

**ফরম্যাট:** শেয়ার্ড হোস্টিংয়ে FTP/cPanel File Manager দিয়ে আপলোড; ভবিষ্যতে Git deploy।

## ১৩. ডেভেলপমেন্ট কনভেনশন

- ফাইল/ফাংশন নাম: `snake_case`; CSS ক্লাস: `kebab-case`
- প্রতিটি PHP ফাইলের শুরুতে `declare(strict_types=1);` (যেখানে সুবিধা)
- আউটপুট এস্কেপিং: ভিউতে সবসময় `e()` হেল্পার (`htmlspecialchars` র‍্যাপার)
- হার্ডকোডেড টেক্সট নয়; সব UI টেক্সট `lang/*.php`-এ
- Git: `main` + ফিচার ব্রাঞ্চ; `config.php` ও `uploads/` `.gitignore`-এ
- কমিট মেসেজ: সংক্ষিপ্ত, কাজের বিবরণসহ

## ১৪. নন-গোল ও সীমাবদ্ধতা

- ফ্রেমওয়ার্ক/ORM নয়; জটিল রিপোর্টিং/অ্যানালিটিক্স নয়
- রিয়েল-টাইম নোটিফিকেশন (WebSocket/Push) নয়
- OTP/SMS/ইমেইল ভেরিফিকেশন ভার্সন ১.২-এ
- মেডিকেল যোগ্যতা যাচাই সিস্টেম করে না; ডিসক্লেইমার দেখায়

## ১৫. বাস্তবায়নের আগে নির্ধারণযোগ্য সিদ্ধান্ত

1. লোকাল ডেভেলপমেন্টে HTTP ব্যবহার করলে `session_secure=false`; উৎপাদনে HTTPS চালু হলে `true` করতে হবে।
2. শেয়ার্ড হোস্টিংয়ে `app/` ডকুমেন্ট রুটের বাইরে রাখা হবে; সম্ভব না হলে web root-এ `app/`-এর জন্য deny rules দিতে হবে।
3. `city_corporation_id` ব্যবহারকারী ঠিকানার বিকল্প পথ হিসেবে দেখানো হবে: জেলা বাছার পর সিটি কর্পোরেশন থাকলে সেটি অথবা উপজেলা→ইউনিয়ন পথ বেছে নেওয়া যাবে। উভয় পথ একসঙ্গে গ্রহণ করা যাবে না।
