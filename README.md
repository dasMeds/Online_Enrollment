# Online Enrollment System

A procedural PHP/MySQL application for student enrollment and administrator
management — John Paul College. Clean v2.1: the v2 look and the v1 features,
plus only the important updates from v3, with the files reorganised cleanly.

## Features

- Role-checked administrator and student sessions
- Student account creation, profile editing, password changes, subject
  selection, enrollment submission, and enrollment history
- Administrator management of student accounts, subjects, sections, and
  schedules, plus enrollment reports
- Prepared database operations, CSRF checks on state-changing forms,
  escaped dynamic output, and transactional account/enrollment writes
- Responsive admin/student styles; the college photography/banner assets
  live once under `assets/`, while action icons use inline SVG

### Full pack v1

- **Enrollment workflow:** approve, reject with a required reason, or
  return to pending. The student sees the status and the registrar note
  in Enrollment History. A rejected form can be corrected and re-submitted
  for the same term — the rejected row is reused in place, so the UNIQUE
  term key never blocks a re-enrollment and any payments already recorded
  against it carry over.
- **Schedule safety (server-side, checked on preview and again on
  submit):** time conflicts within the selection and against the
  student's other pending/approved enrollments in the same term,
  per-subject seat capacity (default 40, editable in Add Subject), and a
  maximum unit load (default 24, editable in Enrollment Settings).
  Schedules come from `sched_tbl` first; `subjecttbl` day/time is only
  parsed when its format is recognisable, and unparsable schedules are
  shown as a warning instead of blocking enrollment.
- **Assessment & payments:** every submission stores an assessment
  snapshot (tuition at the per-unit fee + entrance + internet + misc +
  lab/tutorial fees). Students submit payments with proof of payment
  (JPG/PNG/PDF, max 5 MB) on `student/assessment.php`; the admin verifies
  or rejects them and can record cash payments on `Admin/payments.php`.
  Only verified payments reduce the balance.
- **Printable Certificate of Registration:** `student/cor.php` prints an
  approved enrollment only — subjects with schedule/room/instructor,
  fees, verified paid, and balance, with registrar/cashier/student
  signature lines.
- **Enrollment period & announcements:** `Admin/enrollment_settings.php`
  controls open/closed, start/end dates, the unit limit, and all fees,
  and manages announcements. Both dashboards show published
  announcements and whether enrollment is open; new submissions are
  blocked with a friendly message while enrollment is closed.
- **Documents:** students upload ID / PSA birth certificate / TOR /
  Form 137 / photo / other on `student/documents.php`; the admin verifies
  or rejects them with a note on `Admin/documents.php`.
- **Admin dashboard & export:** real stat cards (students,
  pending/approved/rejected enrollments, payments and documents awaiting
  verification, verified collections) and a server-side filtered
  enrollment report with total/balance columns plus a CSV export
  (`Admin/report_export.php`).
- **Assets:** `assets/icons.svg` (inline-SVG sprite with explicit
  colours), `assets/favicon.svg`, and `assets/empty-state.svg`. Uploads
  live under `uploads/payments/` and `uploads/documents/` with random
  server filenames and `.htaccess` rules that deny script execution.

### UI v2 — modern professional refresh (no behaviour change)

- One shared design system in `assets/css/design.css`, imported by
  `Admin/style.css`, `student/style.css`, and `styles/style.css`: navy
  `#0F2A44`, a single teal-blue accent `#0E7490`, slate neutrals, white
  cards, 8/12/16px radii, soft shadows. System font stack (`Inter`,
  `Segoe UI`, system-ui…) so the pages look right offline on XAMPP.
- Sticky top navigation with the current page highlighted, clear page
  headers, stat cards, consistent buttons (primary / secondary / ghost /
  danger), inputs with clear focus rings, tables with sticky headers and
  row hover, status badges (Pending amber, Approved green, Rejected red),
  announcements, alerts, and empty states.
- Subtle CSS-only animation: page fade + slight rise on load, stat cards
  stagger in, cards lift on hover, 150–250ms button/link transitions.
  No bounce, spin, or flashing. Animations are disabled under
  `prefers-reduced-motion: reduce`.
- Responsive: stats stack, nav wraps, tables scroll inside their card,
  forms go single-column, touch targets at least 44px, no horizontal
  page overflow at 360px. COR print styles hide nav/buttons and print
  clean on A4.

### Clean v2.1 — kept from v3 (the important updates only)

- **Terms fix the January bug:** a `terms` table is now the single source
  of truth for school year / semester. A 2nd-semester enrollment in
  January 2027 is saved as 2026-2027, not 2027-2028. The admin sets the
  current term in Enrollment Settings.
- **Login hardening:** 5 failed attempts / 15 minutes per username+IP,
  30-minute idle timeout, a Content Security Policy header, and
  MIME-by-content upload checks (JPG/PNG/PDF, 5 MB).
- **Password reset + must-change:** `forgot.php` / `reset.php` (reset link
  is never shown on screen), and admin-created accounts must change
  their temporary password on first login.
- **Notifications:** a bell/count link in both navs; students are told
  when an enrollment is approved/rejected, a payment is verified, or a
  document is verified.
- **Audit log:** `Admin/audit.php` lists approve/reject, payment and
  document verify/reject, user create/delete, and term changes.

Everything else from v3 was reverted — see `docs/SETUP.md` for the
kept-vs-reverted list.

## Folder layout

```
index.php  conn.php  logout.php  forgot.php  reset.php
Admin/            admin pages (PHP + thin style.css, no images)
student/          student pages (PHP + thin style.css, no images)
includes/         shared notification helpers
assets/           images (one copy each), icons.svg, favicon.svg,
                  empty-state.svg, css/design.css (shared design system)
styles/           login stylesheet (thin, imports design.css)
database/         schema.sql (fresh install) + migrate.sql (existing installs)
docs/             SETUP.md (apply guide) + CHANGELOG.md
tests/            run_tests.php (pure-helper tests, no database)
uploads/          payments/ + documents/ (proof files, .htaccess protected)
```

## Requirements

- PHP 8.0+ with `mysqli` and `mysqlnd`
- MySQL 8.0.16+ or a recent MariaDB version that enforces `CHECK`
  constraints (XAMPP's MariaDB is fine — see `docs/SETUP.md`)
- Apache or another PHP-capable web server

## Install

1. Copy the project directory into the web root (for example
   `C:\xampp\htdocs\Online_Enrollment`).
2. Start Apache and MySQL.
3. **Fresh install:** import `database/schema.sql` into an empty
   MySQL/MariaDB instance. It creates and selects `online_enroll` if
   needed:
   ```sh
   mysql -u root -p < "database/schema.sql"
   ```
   **Existing install:** import `database/migrate.sql` instead — never
   import both on the same database (the schema already contains
   everything the migration adds). See `docs/SETUP.md`.
4. Configure the database for the PHP process using environment
   variables:
   - `ONLINE_ENROLL_DB_HOST` (default `localhost`)
   - `ONLINE_ENROLL_DB_PORT` (default `3306`)
   - `ONLINE_ENROLL_DB_NAME` (default `online_enroll`)
   - `ONLINE_ENROLL_DB_USER` (default `root`)
   - `ONLINE_ENROLL_DB_PASS` (default empty; local development only)
5. Browse to `http://localhost/Online_Enrollment/`.

The schema uses `CREATE TABLE IF NOT EXISTS` and does not drop existing
tables. For an existing legacy database, back it up first and run
`database/migrate.sql` — it adds the missing enrollment, subject-semester,
and schedule fields without dropping submissions; legacy enrollment dates
and unassigned subject terms remain marked as legacy. It also corrects the
two known placeholder demo titles.

### Included catalog and mock data

The fresh-install SQL seeds 10 distinct subjects for each of 10 programs
(100 subjects total), distributed across 1st Semester, 2nd Semester, and
Summer. The programs are BSIT, BSCS, BSBA, BSA, BSED, BSCE, BSN, BSHM,
BSPSY, and BSARCH; each has five first-semester subjects, four
second-semester subjects, and one summer practicum or internship. Subject
codes are globally unique. The seed includes 10 programs, 14 accounts
(2 administrators and 12 students), 12 complete student profiles across
all 10 programs, 12 sections, 100 conflict-free weekly schedule entries,
12 sample enrollments (pending and approved), and 32 subject-enrollment
links. Seed inserts are safe to re-run and subject management supports
course/semester filters, search, and pagination.

Default fees (same as the original preview): 296 per unit, 2,050 entrance,
150 internet, 50 misc, 1,500 per lab subject, 1,500 per tutorial subject,
maximum 24 units. Change them in Enrollment Settings — new enrollments use
the new values; already-submitted assessments keep their stored amounts.

## Demo accounts

| Role | Username | Initial password |
|---|---|---|
| Administrator | `admins` | `admins` |
| Administrator | `registrar` | `Registrar!2026` |
| Student | `dennis` | `123456` |
| Other mock students | See usernames in the SQL seed | `Student!2026` |

These are educational/demo credentials and must be changed before
deployment. Seeded passwords are stored as PHP `password_hash()` values.
New passwords must be 12–72 characters (the upper bound avoids bcrypt
truncation on PHP installations using it as `PASSWORD_DEFAULT`). Do not
expose the demo database to the internet.

**Old plaintext accounts:** the plaintext-password login fallback was
removed in clean v2.1. If an old account still has a plaintext password
in the database, it can no longer log in — the owner must use
"Forgot password" on the login page (see `docs/SETUP.md` for the reset
and the admin reset procedure). Do not set plaintext passwords in the
database.

## Data and security notes

- Database access is centralized in `conn.php`; the Admin and Student
  include paths delegate to it.
- The default empty-password local database credentials are for XAMPP
  development only. Use a least-privilege database account and
  environment variables outside a local machine.
- Enrollment selections are looked up again on the server and written
  atomically with related subject IDs. Database foreign keys protect
  account, course, section, schedule, and enrollment relationships.
- Student and administrator routes enforce their respective session
  roles; delete operations require POST, a CSRF token, and confirmation.
  Deleting a student cascades to their enrollments, payments, and
  documents — the delete form warns about this, and the deletion is
  written to the audit log first.
- Login is rate-limited (5 failed attempts / 15 minutes per
  username+IP) and sessions expire after 30 minutes of inactivity.
  A Content Security Policy header is sent from `conn.php`.
- Uploads are checked by content type (JPG/PNG/PDF, 5 MB max) and
  stored under random server filenames. They are reachable by their
  random filename if someone knows it; a later pack could serve them
  through a role-checked PHP download script instead.
- Configure HTTPS in production so session cookies are marked `Secure`.

## Validation

Check PHP syntax from the repository root:

```sh
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

Run the pure-helper tests (no database needed):

```sh
php tests/run_tests.php
```

Expected: `19 passed, 0 failed.` End-to-end tests require a running
MySQL/MariaDB instance with the schema imported — see the checklist in
`docs/SETUP.md`.
