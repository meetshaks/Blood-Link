# UI redesign (ui_2.html → production)

Visual system taken from `ui_2.html` / `docs/UI_Spec.md`, rebuilt as plain CSS/JS so the strict CSP in `.htaccess` keeps working
(no Tailwind/Google Fonts/Font Awesome CDN).

## New / changed
- `public/assets/css/base.css`, `fonts.css`, `public/assets/fonts/*` (self-hosted Hind Siliguri + Inter, SIL OFL)
- `app/lib/icons.php` — `icon('name')` inline SVG helper (loaded from bootstrap)
- `app/views/layout/header.php`, `footer.php` — new header, mobile drawer, language pill, admin sidebar layout (set `$adminNav`)
- `app/views/partials/admin_ui.php` — admin badges, row-action forms, reject/suspend modals, pagination
- All pages under `public/` restyled; new `public/about.php` (nav linked to it but it did not exist)
- JS: `ui.js` (toast, confirm, menu, password eye, photo preview, modals, validation), `search.js`, `dashboard.js`, `donations.js`, `profile.js` (null-safe fix)
- `app/lang/bn.php`, `en.php` — 118 new keys each

## Small backend additions (UI-driven, additive)
- `app/lib/admin.php`: `admin_blood_group_counts()`, `admin_recent_users()` (read-only); `admin_user_list()` also selects photo, last donation, district
- `public/admin/action.php`: optional `return` field (`users`|`pending`) so Approve/Reject on the Pending page returns there
- `login.php` / `logout.php`: flash messages now use `t()`

## Not changed
Form field names, CSRF, auth, sessions, SQL schema, API contracts.
