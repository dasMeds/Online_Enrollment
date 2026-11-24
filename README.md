# Online Enrollment System

A procedural PHP/MySQL application for student enrollment and administrator management. Existing page entry points and the `Admin/`, `student/`, `styles/`, and SQL file layout are retained.

## Features

- Role-checked administrator and student sessions
- Student account creation, profile editing, password changes, subject selection, enrollment submission, and enrollment history
- Administrator management of student accounts, subjects, sections, and schedules, plus enrollment reports
- Prepared database operations, CSRF checks on state-changing forms, escaped dynamic output, and transactional account/enrollment writes
- Responsive admin/student styles; the college photography/banner assets remain raster, while action icons use inline SVG

## Requirements

- PHP 8.0+ with `mysqli` and `mysqlnd`
- MySQL 8.0.16+ or a recent MariaDB version that enforces `CHECK` constraints
- Apache or another PHP-capable web server

## Install

1. Copy the project directory into the web root (for example `C:\xampp\htdocs\Online_Enrollment`).
2. Start Apache and MySQL.
3. Import `online_enroll (2).sql` into an empty MySQL/MariaDB instance. The script creates and selects `online_enroll` if needed:

   ```sh
   mysql -u root -p < "online_enroll (2).sql"
   ```

   For a local XAMPP root account with no password, omit `-p`.
4. Configure the database for the PHP process using environment variables:
   - `ONLINE_ENROLL_DB_HOST` (default `localhost`)
   - `ONLINE_ENROLL_DB_PORT` (default `3306`)
   - `ONLINE_ENROLL_DB_NAME` (default `online_enroll`)
   - `ONLINE_ENROLL_DB_USER` (default `root`)
   - `ONLINE_ENROLL_DB_PASS` (default empty; local development only)
5. Browse to `http://localhost/Online_Enrollment/`.

The SQL uses `CREATE TABLE IF NOT EXISTS` and does not drop existing tables. It is intended for a **fresh/empty database**: it does not migrate an older MyISAM schema. Back up existing data and plan a separate migration before using it with an existing installation.

For an existing legacy database, back it up and run [`migrate_enrollment_history.sql`](./migrate_enrollment_history.sql). It adds missing enrollment, subject-semester, and schedule fields without dropping submissions; legacy enrollment dates and unassigned subject terms remain marked as legacy. Legacy subjects are preserved and remain available through the Subjects page's Legacy filter, but are excluded from the default catalog listing. It also corrects the two known placeholder demo titles. This migration supports MariaDB 10.4+.

### Included catalog and mock data

The fresh-install SQL seeds 10 distinct subjects for each of 10 programs (100 subjects total), distributed across 1st Semester, 2nd Semester, and Summer. The programs are BSIT, BSCS, BSBA, BSA, BSED, BSCE, BSN, BSHM, BSPSY, and BSARCH; each has five first-semester subjects, four second-semester subjects, and one summer practicum or internship. Subject codes are globally unique. The seed includes 10 programs, 14 accounts (2 administrators and 12 students), 12 complete student profiles across all 10 programs, 12 sections, 100 conflict-free weekly schedule entries, 12 sample enrollments (pending and approved), and 32 subject-enrollment links. Seed inserts are safe to re-run and subject management supports course/semester filters, search, and pagination.

### Repair an older password column

Older installs may still have `usertbl.password VARCHAR(11)`, which truncates password hashes. Back up the database, then widen the column:

```sql
ALTER TABLE usertbl MODIFY password VARCHAR(255) NOT NULL;
```

If a previous login already truncated the demo hashes, they cannot be recovered. For the demo accounts only, reset the values below; the next successful login will hash them. Do not use this reset on real accounts:

```sql
UPDATE usertbl SET password = 'admins' WHERE username = 'admins' AND user_type = 'admin';
UPDATE usertbl SET password = '123456' WHERE username = 'dennis' AND user_type = 'student';
```

## Demo accounts

| Role | Username | Initial password |
|---|---|---|
| Administrator | `admins` | `admins` |
| Administrator | `registrar` | `Registrar!2026` |
| Student | `dennis` | `123456` |
| Other mock students | See usernames in the SQL seed | `Student!2026` |

These are educational/demo credentials and must be changed before deployment. Seeded passwords are stored as PHP `password_hash()` values. New passwords must be 12-72 characters (the upper bound avoids bcrypt truncation on PHP installations using it as `PASSWORD_DEFAULT`). Do not expose the demo database to the internet.

## Data and security notes

- Database access is centralized in `conn.php`; the Admin and Student include paths delegate to it.
- The default empty-password local database credentials are for XAMPP development only. Use a least-privilege database account and environment variables outside a local machine.
- Enrollment selections are looked up again on the server and written atomically with related subject IDs. Database foreign keys protect account, course, section, schedule, and enrollment relationships.
- Student and administrator routes enforce their respective session roles; delete operations require POST, a CSRF token, and confirmation.
- Configure HTTPS in production so session cookies are marked `Secure`.

## Validation

Check PHP syntax from the repository root:

```sh
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

End-to-end tests require a running MySQL/MariaDB instance with the schema imported.
