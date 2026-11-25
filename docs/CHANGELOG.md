# CHANGELOG — Online Enrollment

## v2.1-clean — 2026-11-25 (this package)

Simplification + reorganisation of v3, back to the v2 base. The UI/UX from
v2 is kept, and only the important v3 updates are kept (simplified).

**Kept from v3:** term management (fixes the January-2027 school-year
bug) with a simple "set current term" control in Enrollment Settings;
login rate limiting (5 / 15 min), plaintext-password fallback removed,
30-minute idle timeout, CSP header, MIME-by-content upload checks;
password reset (`forgot.php` / `reset.php`) + must-change-password for
admin-created accounts; simple in-app notifications (approval, rejection,
payment verified, document verified); simple audit log (`Admin/audit.php`).

**Reverted from v3:** shared `layout.php` / admin sidebar / student avatar
top bar / breadcrumbs (back to the v2 top nav); student progress stepper
and admin needs-attention queue + chart (back to the v2 dashboards);
enroll subject cards + timetable preview (back to the v2 enroll table);
applicant registration, grades/prerequisites/GWA, add/drop, waitlist,
installments, official receipts, discounts/scholarships, reservation fee,
CSV bulk import, reports page, instructor portal/view, dark mode,
self-hosted Inter font files. See `docs/SETUP.md` for the full list —
all of it is still available in the v3 package if wanted later.

**Reorganised:** all shared images now live once under `assets/`
(duplicates deleted from `Admin/`, `student/`, and the root); the shared
design tokens + components now live in `assets/css/design.css`, and the
three stylesheets are thin `@import` files; all SQL is merged into
`database/schema.sql` (fresh install) and `database/migrate.sql`
(existing installs); all markdown is merged into `README.md`,
`docs/SETUP.md`, and this file. The old root SQL files and the three
`APPLY*.md` files were deleted.

**Tests:** `tests/run_tests.php` kept from v3 minus the GWA, installment,
and prerequisite groups (those features were reverted) — 19 checks,
all passing.

## v3 — 2026-11-25 (reverted; kept only as a reference package)

Shared `layout.php` (admin sidebar, student avatar top bar, breadcrumbs),
student progress stepper, admin queue + chart, enroll subject cards +
timetable preview, login show-password/forgot link, modals/toasts/loading
states, dark mode, self-hosted Inter, sortable/paginated student list,
term management, prerequisites + grades/GWA, password reset + must-change
+ notifications bell, add/drop + waitlist, installments + official
receipts + discounts + reservation fee, public applicant registration,
CSV bulk import + bulk approve + class lists + reports, instructor admin
view, audit log, login hardening (rate limit, plaintext removal, idle
timeout, CSP, MIME checks), `tests/run_tests.php` (29 checks, all
passing). Delivered as `online-enrollment-v3.zip`. Joseph decided it
added too much — v2.1-clean is the simplification.

## v2 — UI refresh — 2026-11-25

UI-only refresh on top of v1, no logic or database change. Navy `#0F2A44`,
single teal-blue accent `#0E7490`, slate neutrals, `#F1F5F9` background,
Inter/system font stack (works offline), 8/12/16px radii, sticky nav with
active state, cards, sticky table headers, status badges, 44px touch
targets, responsive to 360px, print-clean COR on A4, CSS-only animations
(450ms fade+rise, staggered stat cards, 150–250ms transitions, disabled
under `prefers-reduced-motion`). Busy `dommc.jpg` body background removed
on Admin/Student pages; header banner kept. Delivered as
`online-enrollment-ui-v2.zip`.

## v1 — Full pack — 2026-11-25

Reject-with-reason + re-enroll by reusing the rejected row, schedule
conflict + capacity (default 40) + 24-unit limit, assessment snapshot +
payments with proof upload, printable COR (approved only), enrollment
period + announcements, document uploads, admin dashboard stats + CSV
export, shared SVG assets. Fees: 296/unit, 2050 entrance, 150 internet,
50 misc, 1500 lab/tutorial. Delivered as
`online-enrollment-full-pack-v1.zip`.
