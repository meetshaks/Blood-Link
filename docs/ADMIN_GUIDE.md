# BloodLink administrator guide

## First login

Create the first super administrator with `tools/create_admin.php`, then sign in at `/admin/login.php`. Use a unique password and do not share it.

## Daily operations

- Review pending donors from the admin dashboard.
- Use donor management to approve, reject, suspend, unsuspend, reset passwords, or delete accounts.
- Give temporary passwords to donors through a private channel only.
- Review change requests before approving phone or blood-group changes.
- Use system settings to change approval mode, donation gap, age limits, phone visibility, and default language.
- Review audit logs after sensitive administrative activity.

## Account safety

Use the smallest administrator role required. Keep the super-admin account for settings and emergency operations. Never edit donor records directly in SQL unless recovering from a documented incident and taking a backup first.
