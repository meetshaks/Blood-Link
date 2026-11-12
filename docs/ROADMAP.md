# BloodLink Step-by-Step Roadmap

We will complete and verify one phase at a time. No large code dump will be generated. Each phase must be completed before moving to the next phase.

## Phase 1 — Foundation and Environment

**Goal:** Establish a clean, secure, runnable project foundation.

**Status:** Complete

**Deliverables:**

- Project directory structure
- Secure configuration system
- Simple shared PDO connection
- Cached `db()` helper
- Local/production environment settings
- Bootstrap file
- Timezone configuration
- `.gitignore`
- Basic web-server protection rules
- Configuration sample
- Database connection health-check script
- Initial README setup instructions

**Validation:**

- PHP syntax checks
- PDO connection test
- Error handling test
- Local HTTP session configuration test
- Production HTTPS configuration check

## Phase 2 — Database Schema and Geography Importer

**Goal:** Create the complete database and import Bangladesh location data.

**Status:** Complete

**Deliverables:**

- `database/schema.sql`
- `tools/import_geo.php`
- Geography validation script
- Admin seed procedure
- Foreign keys and indexes
- Import count verification

**Validation:**

- Expected location counts
- Duplicate handling
- Hierarchy integrity
- Transaction rollback testing

## Phase 3 — Shared Application Core

**Goal:** Build reusable application infrastructure.

**Status:** Complete

**Deliverables:**

- Request/response helpers
- Output escaping
- Session handling
- Error pages
- Flash messages
- Common layout
- Security headers
- Logging foundation

## Phase 4 — Internationalization and UI Foundation

**Goal:** Establish Bengali/English support and the base visual system.

**Status:** Complete

**Deliverables:**

- Bengali translations
- English translations
- Language switcher
- Number/date formatting
- Base CSS
- Responsive layout
- Header/footer
- Accessibility foundations

## Phase 5 — Validation, CSRF, Uploads, and Rate Limiting

**Goal:** Implement reusable security and input-handling services.

**Status:** Complete

**Deliverables:**

- Phone normalization
- Form validation
- Age/date validation
- CSRF protection
- Login rate limiting
- Image upload validation and resizing
- Upload directory protection

## Phase 6 — Authentication and Donor Registration

**Goal:** Allow donors to register and log in securely.

**Status:** Complete

**Deliverables:**

- Registration page
- Login/logout
- Password hashing
- Password change flow
- Pending/approved/rejected states
- Auto/manual approval behavior
- Session regeneration

## Phase 7 — Location APIs and Public Donor Search

**Status:** Complete

**Goal:** Make donor discovery work publicly.

**Deliverables:**

- Location dropdown API
- City-corporation alternative flow
- Donor search API
- Search filters
- Pagination
- Availability calculation
- Phone privacy rules
- Search result UI

## Phase 8 — Donor Dashboard and Profile

**Status:** Complete

**Goal:** Allow donors to manage their own information.

**Deliverables:**

- Dashboard
- Profile view/edit
- Availability toggle
- Account status banners
- Password management
- Ownership protection

## Phase 9 — Donation History

**Status:** Complete

**Goal:** Track donations accurately.

**Deliverables:**

- Add donation
- Delete donation
- Donation history page
- Duplicate-date protection
- Future-date validation
- `last_donation_date` synchronization
- Transaction handling

## Phase 10 — Admin Authentication and Dashboard

**Status:** Complete

**Goal:** Establish the administrator experience.

**Deliverables:**

- Separate admin login
- Admin roles
- Admin dashboard
- Donor statistics
- Pending counts
- Admin session protection

## Phase 11 — Donor Administration

**Status:** Complete

**Goal:** Let administrators manage donor accounts.

**Deliverables:**

- Donor list
- Filters and pagination
- Approve/reject
- Suspend/unsuspend
- Admin-created donors
- Temporary passwords
- Password reset
- Delete donor and avatar

## Phase 12 — Settings, Change Requests, and Audit Logs

**Status:** Complete

**Goal:** Complete administrative controls and accountability.

**Deliverables:**

- System settings
- Approval mode
- Donation gap
- Age limits
- Phone visibility
- Language defaults
- Blood-group/phone change requests
- Admin action logs

## Phase 13 — Frontend Polish and Accessibility

**Status:** Complete

**Goal:** Match the complete UI specification.

**Deliverables:**

- Responsive layouts
- Loading states
- Empty/error states
- Toasts and modals
- Keyboard support
- Focus management
- Screen-reader labels
- Reduced-motion support

## Phase 14 — Testing and Security Verification

**Status:** Complete

**Goal:** Verify behavior before deployment.

**Deliverables:**

- Automated tests
- Integration tests
- Security test cases
- SQL injection checks
- XSS/CSRF checks
- IDOR checks
- Upload tests
- Rate-limit tests
- 5,000+ donor performance test
- Search query `EXPLAIN` validation

## Phase 15 — Deployment and Handover

**Status:** Complete

**Goal:** Prepare a safe production deployment.

**Deliverables:**

- Production configuration
- HTTPS enforcement
- Apache/Nginx rules
- Backup procedure
- Cron maintenance tasks
- Error logging
- `robots.txt`
- Deployment checklist
- Admin guide
- Final README

## Build Order

We will proceed strictly in this order:

**Phase 1 → Phase 2 → Phase 3 → Phase 4 → Phase 5 → Phase 6 → Phase 7 → Phase 8 → Phase 9 → Phase 10 → Phase 11 → Phase 12 → Phase 13 → Phase 14 → Phase 15**

Phase 1 will be implemented as a complete, focused foundation before moving forward.
