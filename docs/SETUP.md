# Online Enrollment — Clean v2.1 — SETUP

**What this is:** the complete project (v2 base + the important v3 updates,
simplified) plus this guide. Extract it over your existing
`C:\xampp\htdocs\Online_Enrollment` folder. It does not touch your
database — the SQL import below does that, separately.

**Do not push this zip itself to GitHub.** Push the project files after you
have copied them in and tested them (step 6 below). Joseph pushes — nothing
in this package pushes for you.

## 0. Before you start — back up

1. In phpMyAdmin, select the `online_enroll` database → Export → Go, and
   save the `.sql` file somewhere safe.
2. Copy your current `C:\xampp\htdocs\Online_Enrollment` folder to a
   backup folder, e.g. `Desktop\Online_Enrollment-backup-v2-clean`.
3. **Compare before overwriting.** If you edited any file after v2 was
   built (25 Nov 2026), do not blindly overwrite your newer version —
   compare them first and keep your edits. The files this package changes
   are listed in step 1.

## 1. Copy the files

Extract this zip over `C:\xampp\htdocs\Online_Enrollment` (overwrite
when asked).

**New files in this pack:**

- `database/schema.sql`, `database/migrate.sql` — the only SQL files now
- `docs/SETUP.md` (this file), `docs/CHANGELOG.md`, `README.md`
- `forgot.php`, `reset.php` — password reset
- `Admin/audit.php`, `Admin/notifications.php`,
  `student/notifications.php` — audit log + notification lists
- `includes/notifications_actions.php`, `includes/notifications_page.php`
- `assets/css/design.css` — the shared design system (tokens + components)
- `assets/logo.jpg`, `assets/dommc.jpg`, `assets/banner.png`,
  `assets/banner-repeat.png`, `assets/header.jpg`, `assets/brochure.jpg`,
  `assets/news1.jpg` … `assets/news4.jpg` — the shared images, one copy each
- `tests/run_tests.php` — pure-helper tests (no database needed)

**Changed files in this pack:**

- `conn.php` — term helpers, notifications + audit helpers, rate limiting,
  CSP header, 30-minute idle timeout support, MIME upload checks
- `index.php` — password_verify only (no plaintext fallback), rate-limit
  and timeout messages, forgot-password link, must-change redirect
- `Admin/logcheck.php`, `student/logcheck.php` — 30-minute idle timeout
  (student version also redirects to Change Password until changed)
- `Admin/enrollment_settings.php` — "set current term" control
- `student/enroll.php`, `student/showsubs.php` — school year now comes
  from the current term (fixes the January bug)
- `student/changepass.php` — password_verify only, clears the
  must-change flag
- `Admin/addnewuser.php` — new accounts get a temporary password +
  must-change flag + stored email; creation is audited
- `Admin/report.php`, `Admin/payments.php`, `Admin/documents.php` —
  approve/reject/verify actions now notify the student and write to the
  audit log
- `Admin/deletecurruser.php`, `Admin/userreg.php` — delete is audited
  first and the form warns that enrollments, payments, and documents
  cascade-delete
- `Admin/includes/menu1.php`, `student/includes/menu.php` —
  Notifications link with unread count, Audit Log link (admin)
- `Admin/style.css`, `student/style.css`, `styles/style.css` — now thin
  files that `@import` `assets/css/design.css` and keep only their
  page-specific rules
- `Admin/adminindex.php`, `student/studentindex.php`,
  `student/assessment.php`, `student/cor.php`,
  `student/enrollmenthistory.php` — image paths updated to `assets/`

**Files to DELETE from your live folder after extracting** (they are
gone from this package — keeping them would leave dead or duplicated
files behind):

- `online_enroll (2).sql`, `migrate_enrollment_history.sql`,
  `migrate_full_pack_v1.sql` — replaced by `database/schema.sql` +
  `database/migrate.sql`
- `APPLY.md`, `APPLY-UI-V2.md` — replaced by this file
- If you ever applied v3, also delete its v3-only files:
  `apply.php`, `includes/layout.php`, `assets/app.js`,
  `assets/layout.css`, `assets/fonts/`, `templates/`,
  `Admin/applicants.php`, `Admin/grades.php`, `Admin/discounts.php`,
  `Admin/import.php`, `Admin/instructor.php`, `Admin/reports.php`,
  `Admin/adddrop.php`, `student/adddrop.php`, `student/waitlist.php`,
  `student/receipt.php`, and the old `migrate_v3.sql` /
  `seed_v3_demo.sql` if you kept them. (The v3 demo students
  2026-0013…0020 stay in the database if you imported the v3 seed —
  they are harmless demo accounts; delete them in phpMyAdmin if you
  do not want them.)
- The duplicated images this package moved into `assets/`:
  `Admin/banner.png`, `Admin/banner-repeat.png`, `Admin/header.jpg`,
  `Admin/brochure.jpg`, `Admin/news1.jpg` … `Admin/news4.jpg`,
  `Admin/dommc.jpg`, and the same files under `student/`, plus the
  root `logo.jpg` and `dommc.jpg`. (The zip no longer contains them,
  so they only need deleting if an older copy is still sitting in
  those folders.)

## 2. Import the database — schema.sql OR migrate.sql, never both

**Fresh install** (empty database): import `database/schema.sql`. It
creates `online_enroll` and all 17 tables, and seeds the courses,
subjects, sections, schedules, demo accounts, enrollments, settings,
announcements, and terms.

```sh
mysql -u root -p < "database/schema.sql"
```

**Existing install** (you already have a database from v1/v2/v3):
import `database/migrate.sql` once. It is additive only — no DROP, no
password changes — and safe to run twice.

```sh
mysql -u root -p online_enroll < "database/migrate.sql"
```

For a local XAMPP root account with no password, omit `-p`. Or import
in phpMyAdmin. What the migration does:

- Legacy history fixes (usertbl widening, semester/school_year/
  created_at on enrollments, semester on subjects and schedules).
- v1 additions: enrollment `status`/review/assessment columns
  (backfilled from the old `approved` flag and the standard fee formula),
  `capacity` on subjects (default 40), and the `app_settings`,
  `announcements`, `enrollment_payments`, `student_documents` tables.
- Kept v3 additions: `terms` table (seeded from your most recent
  enrollment, falling back to 2026-2027 1st Semester) +
  `student_subject.term_id` (backfilled), `usertbl.must_change_password`
  + `usertbl.email`, `login_attempts`, `password_resets`,
  `notifications`, `audit_log`.
- Safe demo-data top-up: the extra terms (2025-2026 2nd Semester,
  2026-2027 2nd Semester + Summer) and two announcements. Students and
  enrollments are **never** re-seeded into an existing database.

**MariaDB note:** the migration uses `ADD COLUMN IF NOT EXISTS` /
`ADD INDEX IF NOT EXISTS`, which XAMPP's MariaDB accepts. Plain MySQL
8.0 does not support that syntax — if you run plain MySQL, import the
migration once only; a second run would error on the already-added
columns (the database itself is unaffected).

## 3. Check PHP syntax + run the tests (before opening the site)

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

Every file should print `No syntax errors detected`. If `php` is not on
your PATH, run it from the XAMPP shell, or use
`C:\xampp\php\php.exe -l <file>`.

Then run the pure-helper tests (no database needed):

```sh
php tests/run_tests.php
```

Expected: `19 passed, 0 failed.` (Fee calc, unit limit, schedule
conflicts, time parsing, and the term / school-year calculation that
covers the January bug.)

## 4. Hard-refresh the browser

Ctrl+F5 on the login page. The stylesheets moved (they now `@import`
`assets/css/design.css`), so a cached old stylesheet is the most common
"it didn't change" cause.

## 5. Click-through checklist (XAMPP, Apache + MySQL running)

Log in as admin `admins` / `admins` first, then as student `dennis` /
`123456` (demo accounts — change them before any real use).

1. Admin dashboard shows real numbers (students, pending/approved,
   payments/documents awaiting verification, verified collections) and
   the seeded "Enrollment is open" announcement.
2. Admin → Enrollment Settings: turn enrollment **off**, save. As a
   student, Enroll shows the closed message and the Review button is
   disabled. Turn it back **on**.
3. Admin → Enrollment Settings → Current term: the current term is
   shown (2026-2027 1st Semester on a fresh install). Change it to
   2nd Semester and save — the page confirms the change, and it appears
   in Admin → Audit Log.
4. Student → Enroll (1st Semester): each subject shows a schedule
   (e.g. Monday 08:00-09:30), room, instructor, and seats left. Tick
   subjects and watch the selected-units total update.
5. Student → Review selected subjects: the preview shows the schedule
   and seats left, and the fee breakdown (296/unit, 2,050 entrance, 150
   internet, 50 misc). Submit.
6. Admin → Enrollment Report: the new form appears. **Reject** it and
   type a reason (the form requires one). Check the student sees
   "Rejected" and your reason in Enrollment History — and that the
   student nav now shows a Notifications count. Open the student's
   Notifications page: the rejection notice is there; opening it marks
   it read.
7. Student → Enroll again for the same term and submit a corrected form.
   It must succeed — this is the re-enrollment path (the rejected row is
   reused, not blocked by the UNIQUE term key).
8. Admin → Enrollment Report: **Approve** the corrected form. The
   student gets an "Enrollment approved" notification.
9. Student → Assessment & Payments: fee breakdown, total, and balance
   match the preview. Submit a GCash payment with a small JPG/PNG/PDF
   receipt. Admin → Payments: verify it — the student balance drops and
   the student gets a "Payment verified" notification. Also try
   recording a cash payment from the admin page.
10. Student → Print COR (from Enrollment History or Assessment): the
    Certificate of Registration prints with subjects, schedule, fees,
    paid, and balance. Before approval it must show a message instead
    of a COR — check this on a still-pending enrollment.
11. Student → My Documents: upload a JPG/PNG/PDF. Admin → Documents:
    verify it, then reject another one with a reason and check the
    student sees the note and the notification.
12. Admin → Enrollment Report: filter by status/course/semester/search
    and click Export CSV — the download must contain the same filtered
    rows.
13. Admin → Enrollment Settings: add an announcement and publish it;
    check it appears on both dashboards.
14. **Login hardening:** enter a wrong password 5 times for one
    username → "Too many failed login attempts. Please wait 15 minutes"
    (the block is per username+IP). `admins` still logs in afterwards.
15. **Idle timeout:** leave a student logged in for 31 minutes →
    redirected to login with the "signed out after 30 minutes of
    inactivity" message.
16. **Password reset:** Login → "Forgot password?" → enter a username.
    **No reset link is shown on screen.** For local testing only, set
    `reset_debug_log = 1` in `app_settings` and the link is written to
    `reset_links.log` in the project root — **delete that file and set
    the flag back to 0 before any real use.** To actually send mail, see
    "Email" below.
17. **Must-change password:** Admin → Add Student, create an account.
    Log in as that student → forced to Change Password before using the
    site; after changing it, the site works normally.
18. **Audit log:** Admin → Audit Log shows the approve/reject,
    payment/document verify, user-create, and term-change actions above
    with actor + time. Admin → Students → Delete warns that the
    student's enrollments, payments, and documents cascade-delete.
19. **January bug:** set the current term to 2026-2027 2nd Semester,
    then submit a 2nd-semester enrollment and confirm it saves as
    school year **2026-2027** (check Admin → Enrollment Report). Set
    the term back when done.
20. **Look & responsive:** on desktop **and** on your phone (or a narrow
    browser window) — login card centred with logo and navy backdrop;
    sticky top nav with the current page highlighted; dashboards with
    stat cards and announcements; report status badges (Pending amber,
    Approved green, Rejected red) with sticky table header and row
    hover; a page with no rows shows the empty state. At 360px wide
    there must be no sideways scrolling of the page itself; tables
    scroll inside their own card. Motion: pages fade and rise slightly
    on load, stat cards stagger in, buttons lift a touch on hover.
    Nothing should bounce, spin, or flash.

## 6. Push to GitHub (you push — nothing in this package pushes for you)

Repo: `https://github.com/dasMeds/Online_Enrollment`, branch `main`.
In GitHub Desktop (or `git` on your PC):

1. Open the repository, or clone it first if it is not on your PC yet.
2. Copy the tested files from `C:\xampp\htdocs\Online_Enrollment`
   into the repo folder (same list as step 1).
3. Check `git status` before committing — it should list only the files
   above. Do **not** commit `uploads/` files students uploaded, any
   `.env`, `reset_links.log`, or database dumps.
4. Commit with a clear message, e.g.
   `Clean v2.1: back to v2 base, keep term fix + login hardening + reset + notifications + audit; reorganise assets, SQL and docs`.
5. Push to `main` and confirm the new files show on GitHub.

## Kept vs reverted (clean v2.1 vs v3)

| Feature | Clean v2.1 | Notes |
|---|---|---|
| Term management (current-term fix) | **Kept, simplified** | `terms` table + "set current term" in Enrollment Settings only |
| Login rate limiting (5 / 15 min) | **Kept** | `login_attempts` table |
| Plaintext-password fallback removed | **Kept** | `password_verify` only |
| 30-minute idle timeout | **Kept** | Both logchecks |
| CSP header | **Kept** | `'unsafe-inline'` allowed for existing inline scripts/styles |
| MIME-by-content upload checks | **Kept** | JPG/PNG/PDF, 5 MB, via `app_upload_file()` |
| Password reset + must-change | **Kept** | `forgot.php` / `reset.php`, 1-hour tokens |
| Notifications (simple) | **Kept** | Bell/count in both navs, list pages, 4 notice types |
| Audit log (simple) | **Kept** | `Admin/audit.php`, 6 action groups |
| Shared layout / sidebar / avatar top bar / breadcrumbs | Reverted | Back to the v2 top nav (active-nav class kept) |
| Student progress stepper, admin queue + chart | Reverted | Back to the v2 dashboards (stat cards + announcements) |
| Enroll subject cards + timetable preview | Reverted | Back to the v2 enroll table (v1 server checks kept) |
| Applicant registration (`apply.php`) | Reverted | Available in v3 if wanted later |
| Grades / prerequisites / GWA | Reverted | Available in v3 if wanted later |
| Add/drop, waitlist | Reverted | Available in v3 if wanted later |
| Installments, official receipts, discounts, reservation fee | Reverted | Available in v3 if wanted later |
| CSV bulk import, reports page, instructor view | Reverted | Available in v3 if wanted later |
| Dark mode | Reverted | Available in v3 if wanted later |
| Self-hosted Inter font files | Reverted | System font stack (works offline) |
| Modals / toasts / loading states (`assets/app.js`) | Reverted | Plain `confirm()` dialogs again |

## Legacy plaintext accounts — read this before going live

The plaintext-password login fallback was removed. All seeded demo
passwords are already `password_hash()` values (verified), so the demo
accounts work. If an **old account still has a plaintext password** in
the database, it can no longer log in. Fix it one of these ways:

- **Best:** the owner uses "Forgot password" on the login page (see
  "Email" below for mail setup; the debug-log flag works for local
  testing).
- **Admin reset:** an admin creates a fresh temporary password for the
  account with a hash generated in PHP:
  ```php
  php -r "echo password_hash('Temporary!2026', PASSWORD_DEFAULT), PHP_EOL;"
  ```
  then in phpMyAdmin:
  ```sql
  UPDATE usertbl SET password = '<the hash above>', must_change_password = 1 WHERE username = '<username>';
  ```
  The user logs in with the temporary password and is forced to change
  it. **Never** store a plaintext password in `usertbl.password`, and
  never use the old v1 README trick of writing the plaintext value
  there — it no longer works.

Older installs may also still have `usertbl.password VARCHAR(11)`,
which truncates hashes. The migration widens it to VARCHAR(255). If a
previous login already truncated the demo hashes, they cannot be
recovered — reset the demo accounts with the procedure above.

## Email (password reset + notifications)

`app_notify()` in `conn.php` always writes the in-app notice. Email is
sent only when the admin enables it:

1. In the database, `app_settings`: set `smtp_enabled` to `1` and
   `mail_from` to the address mail should come from.
2. `app_mail_notify()` in `conn.php` is the single hook point — it calls
   PHP `mail()` today. On XAMPP, `mail()` needs a working sendmail/SMTP
   setup (`php.ini` `[mail function]` / `sendmail_path`); if that is not
   configured, mail silently fails and only the in-app notice is created.
   To use PHPMailer instead, replace the body of `app_mail_notify()`
   with a PHPMailer send and keep the same signature.
3. Reset links are **never shown on screen**. For local testing only,
   set `reset_debug_log = 1` in `app_settings` and the link is written
   to `reset_links.log` in the project root — **delete that file and set
   the flag back to 0 before any real use.**

## Known limits / what was not verified here

- This pack was **not** run on MySQL in the build environment (no MySQL
  was available there), so the SQL import and the step-5 checklist are
  yours to run on XAMPP. `php -l` passed on every PHP file and
  `tests/run_tests.php` passed 19/19 in the build environment — that is
  static + pure-helper verification only, not a live run. The layout/CSS
  was not rendered in a real browser here either.
- Schedule conflicts use `sched_tbl` first (the seed fills it:
  08:00–09:30, one day per subject). If a subject has no schedule
  anywhere, it is shown as "Not scheduled" and is **not**
  conflict-checked — the preview says so rather than blocking the
  student.
- Capacity counts pending + approved enrollments in the same term. Two
  students submitting the exact same last seat at the exact same moment
  could both pass the check (no row locking on the count); for a school
  system this is acceptable, and the registrar can fix the rare overlap
  from the report.
- Receipts and documents are stored as files under `uploads/` and are
  reachable by their random filename if someone knows it. That is the
  same trade-off as the original project; a later pack could serve them
  through a role-checked PHP download script instead.
- If two terms ever end up marked current (e.g. the migration seeded a
  second one), pick the right one in Admin → Enrollment Settings →
  Current term — the app uses the term flagged current there.
