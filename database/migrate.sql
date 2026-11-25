-- Online Enrollment — clean v2.1 migration for EXISTING installs.
-- Back up the database first (phpMyAdmin Export, or mysqldump).
-- Additive only: no DROP, no password changes. Safe to run twice
-- (ADD COLUMN IF NOT EXISTS / CREATE TABLE IF NOT EXISTS / INSERT IGNORE).
-- This ONE file replaces migrate_enrollment_history.sql,
-- migrate_full_pack_v1.sql and migrate_v3.sql. Run it once on the database
-- your current install already uses. Fresh installs use database/schema.sql
-- instead — never import both on the same database (schema.sql already
-- contains everything here).
USE `online_enroll`;

-- ---------------------------------------------------------------------------
-- 1. Legacy history migration (was migrate_enrollment_history.sql).
-- ---------------------------------------------------------------------------
ALTER TABLE `usertbl`
  MODIFY COLUMN `username` VARCHAR(50) NOT NULL,
  MODIFY COLUMN `password` VARCHAR(255) NOT NULL,
  MODIFY COLUMN `fullname` VARCHAR(150) NOT NULL,
  MODIFY COLUMN `user_type` ENUM('admin', 'student') NOT NULL,
  ADD UNIQUE KEY IF NOT EXISTS `uq_usertbl_username` (`username`);

ALTER TABLE `usertbl` ENGINE=InnoDB,
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `student_subject`
  ADD COLUMN IF NOT EXISTS `semester` VARCHAR(20) NOT NULL DEFAULT 'Legacy' AFTER `approved`,
  ADD COLUMN IF NOT EXISTS `school_year` VARCHAR(20) NOT NULL DEFAULT 'Legacy' AFTER `semester`,
  ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL DEFAULT NULL AFTER `school_year`,
  ADD INDEX IF NOT EXISTS `idx_student_subject_student_created` (`studentID`, `created_at`);

ALTER TABLE `student_subject` ENGINE=InnoDB,
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `subjecttbl`
  ADD COLUMN IF NOT EXISTS `semester` VARCHAR(20) NOT NULL DEFAULT 'Legacy',
  MODIFY COLUMN `subjectcode` VARCHAR(30) NOT NULL,
  MODIFY COLUMN `subjecttitle` VARCHAR(150) NOT NULL,
  MODIFY COLUMN `numofunits` TINYINT UNSIGNED NOT NULL,
  MODIFY COLUMN `description` TEXT NOT NULL,
  MODIFY COLUMN `course_con` VARCHAR(20) NOT NULL,
  ADD UNIQUE KEY IF NOT EXISTS `uq_subject_code` (`subjectcode`);

UPDATE `subjecttbl`
SET `subjecttitle` = 'File Systems and Storage Management',
    `description` = 'Introduces file system organization, storage devices, and reliable data management.'
WHERE `subjectcode` = 'IT202' AND `subjecttitle` = 'File Manage';

UPDATE `subjecttbl`
SET `subjecttitle` = 'Computer Systems and Architecture',
    `description` = 'Introduces processor organization, memory hierarchy, and computer system components.'
WHERE `subjectcode` = 'CS 103' AND `subjecttitle` = 'Farenheit';

UPDATE `subjecttbl`
SET `semester` = 'Legacy'
WHERE `subjectcode` IN ('IT201', 'IT202', 'CS 103')
  AND `semester` = '1st Semester';

ALTER TABLE `subjecttbl` ENGINE=InnoDB,
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `sched_tbl`
  ADD COLUMN IF NOT EXISTS `semester` VARCHAR(20) NOT NULL DEFAULT '1st Semester',
  ADD COLUMN IF NOT EXISTS `day` VARCHAR(20) NOT NULL DEFAULT 'Unscheduled';

ALTER TABLE `sched_tbl` ENGINE=InnoDB,
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_subject_items` (
  `ID` INT NOT NULL AUTO_INCREMENT,
  `enrollment_ID` INT NOT NULL,
  `subject_ID` INT NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `uq_enrollment_subject` (`enrollment_ID`, `subject_ID`),
  KEY `idx_enrollment_subject_item` (`subject_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2. Full-pack v1 migration (was migrate_full_pack_v1.sql).
-- ---------------------------------------------------------------------------
-- 1. Enrollment workflow + assessment snapshot on student_subject.
ALTER TABLE `student_subject`
  ADD COLUMN IF NOT EXISTS `status` VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER `approved`,
  ADD COLUMN IF NOT EXISTS `review_note` TEXT NULL DEFAULT NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `reviewed_at` DATETIME NULL DEFAULT NULL AFTER `review_note`,
  ADD COLUMN IF NOT EXISTS `reviewed_by` INT UNSIGNED NULL DEFAULT NULL AFTER `reviewed_at`,
  ADD COLUMN IF NOT EXISTS `tuition_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `reviewed_by`,
  ADD COLUMN IF NOT EXISTS `entrance_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `tuition_amount`,
  ADD COLUMN IF NOT EXISTS `internet_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `entrance_fee`,
  ADD COLUMN IF NOT EXISTS `misc_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `internet_fee`,
  ADD COLUMN IF NOT EXISTS `lab_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `misc_fee`,
  ADD COLUMN IF NOT EXISTS `tutorial_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `lab_fee`,
  ADD COLUMN IF NOT EXISTS `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `tutorial_fee`,
  ADD INDEX IF NOT EXISTS `idx_student_subject_status` (`status`);

-- Backfill the new status from the old approved flag, then keep them in
-- sync from the application code (approved = 1 exactly when status = 'approved').
UPDATE `student_subject` SET `status` = 'approved' WHERE `approved` = 1 AND `status` = 'pending';
UPDATE `student_subject` SET `status` = 'pending' WHERE `approved` = 0 AND `status` NOT IN ('pending', 'rejected');

-- Backfill assessment snapshots for old enrollments using the standard
-- formula (296/unit + 2050 entrance + 150 internet + 50 misc + 1500 per
-- lab/tutorial subject). New submissions store the snapshot at submit time.
UPDATE `student_subject` e
SET `tuition_amount` = e.`totalunits` * 296,
    `entrance_fee` = 2050,
    `internet_fee` = 150,
    `misc_fee` = 50,
    `lab_fee` = (SELECT COALESCE(SUM(s.`islab`), 0) * 1500 FROM `student_subject_items` i JOIN `subjecttbl` s ON s.`ID` = i.`subject_ID` WHERE i.`enrollment_ID` = e.`ID`),
    `tutorial_fee` = (SELECT COALESCE(SUM(s.`istutorial`), 0) * 1500 FROM `student_subject_items` i JOIN `subjecttbl` s ON s.`ID` = i.`subject_ID` WHERE i.`enrollment_ID` = e.`ID`)
WHERE e.`total_amount` = 0;
UPDATE `student_subject`
SET `total_amount` = `tuition_amount` + `entrance_fee` + `internet_fee` + `misc_fee` + `lab_fee` + `tutorial_fee`
WHERE `total_amount` = 0;

-- 2. Per-subject seat capacity (default 40, matching the section maximum).
ALTER TABLE `subjecttbl`
  ADD COLUMN IF NOT EXISTS `capacity` SMALLINT UNSIGNED NOT NULL DEFAULT 40 AFTER `numofunits`;

-- 3. Settings (fees, enrollment period, unit limit).
CREATE TABLE IF NOT EXISTS `app_settings` (
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `app_settings` (`setting_key`, `setting_value`) VALUES
  ('enrollment_open', '1'),
  ('enrollment_start', ''),
  ('enrollment_end', ''),
  ('max_units', '24'),
  ('fee_per_unit', '296'),
  ('fee_entrance', '2050'),
  ('fee_internet', '150'),
  ('fee_misc', '50'),
  ('fee_lab', '1500'),
  ('fee_tutorial', '1500');

-- 4. Announcements.
CREATE TABLE IF NOT EXISTS `announcements` (
  `ID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `body` TEXT NOT NULL,
  `is_published` TINYINT(1) NOT NULL DEFAULT 0,
  `starts_at` DATETIME NULL DEFAULT NULL,
  `ends_at` DATETIME NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `idx_announcements_published` (`is_published`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `announcements` (`ID`, `title`, `body`, `is_published`) VALUES
  (1, 'Enrollment is open', 'Enrollment for the current term is now open. Choose your subjects, submit your form, and check your status here.', 1);

-- 5. Payments against an enrollment assessment.
CREATE TABLE IF NOT EXISTS `enrollment_payments` (
  `ID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `enrollment_ID` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `method` ENUM('cash', 'gcash', 'bank', 'maya', 'other') NOT NULL DEFAULT 'cash',
  `reference` VARCHAR(100) NOT NULL DEFAULT '',
  `paid_at` DATE NULL DEFAULT NULL,
  `receipt_path` VARCHAR(255) NOT NULL DEFAULT '',
  `status` ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
  `note` TEXT NULL DEFAULT NULL,
  `recorded_by` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `idx_payments_enrollment` (`enrollment_ID`),
  KEY `idx_payments_status` (`status`),
  CONSTRAINT `fk_payments_enrollment` FOREIGN KEY (`enrollment_ID`)
    REFERENCES `student_subject` (`ID`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `chk_payment_amount` CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Student documents.
CREATE TABLE IF NOT EXISTS `student_documents` (
  `ID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `accountID` INT UNSIGNED NOT NULL,
  `studnum` VARCHAR(20) NOT NULL DEFAULT '',
  `doc_type` ENUM('id', 'psa_birth', 'tor', 'form137', 'photo', 'other') NOT NULL DEFAULT 'other',
  `file_path` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL DEFAULT '',
  `mime` VARCHAR(100) NOT NULL DEFAULT '',
  `size_bytes` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
  `note` TEXT NULL DEFAULT NULL,
  `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `idx_documents_account` (`accountID`),
  KEY `idx_documents_status` (`status`),
  CONSTRAINT `fk_documents_account` FOREIGN KEY (`accountID`)
    REFERENCES `usertbl` (`ID`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 3. Kept v3 additions (was migrate_v3.sql sections 1-4).
-- ---------------------------------------------------------------------------
-- Sections 1-4 of the v3 migration (terms, login hardening, notifications,
-- audit log). The v3 sections 5+ (prerequisites, grades, installments,
-- add/drop, waitlist, applicants, discounts) were reverted in clean v2.1
-- and are NOT in this file.

-- ---------------------------------------------------------------------------
-- 1. Terms. The current term is now the single source of truth for the
-- school year / semester (fixes the Jan-2027 bug: a 2nd-semester enrollment
-- in January 2027 used to be saved as "2027-2028" because the year was
-- calculated from date('Y')).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `terms` (
  `ID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_year` VARCHAR(9) NOT NULL,
  `semester` ENUM('1st Semester', '2nd Semester', 'Summer') NOT NULL,
  `start_date` DATE NULL DEFAULT NULL,
  `end_date` DATE NULL DEFAULT NULL,
  `is_current` TINYINT(1) NOT NULL DEFAULT 0,
  `enrollment_open` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `uq_terms_year_semester` (`school_year`, `semester`),
  KEY `idx_terms_current` (`is_current`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the current term from the most recent enrollment already in the
-- database (the seed data is 1st/2nd Semester + Summer 2026-2027). If there
-- are no enrollments at all, seed a sensible current term for today.
INSERT INTO `terms` (`school_year`, `semester`, `is_current`, `enrollment_open`)
SELECT `school_year`, `semester`, 1, 1 FROM `student_subject`
WHERE `school_year` <> '' AND `school_year` <> 'Legacy'
ORDER BY `created_at` DESC, `ID` DESC LIMIT 1
ON DUPLICATE KEY UPDATE `is_current` = 1;

INSERT IGNORE INTO `terms` (`school_year`, `semester`, `is_current`, `enrollment_open`)
SELECT '2026-2027', '1st Semester', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM `terms` WHERE `is_current` = 1);

-- Link enrollments to their term (kept in sync with semester/school_year).
ALTER TABLE `student_subject`
  ADD COLUMN IF NOT EXISTS `term_id` INT UNSIGNED NULL DEFAULT NULL AFTER `school_year`,
  ADD INDEX IF NOT EXISTS `idx_student_subject_term` (`term_id`);

UPDATE `student_subject` e
JOIN `terms` t ON t.`school_year` = e.`school_year` AND t.`semester` = e.`semester`
SET e.`term_id` = t.`ID`
WHERE e.`term_id` IS NULL;

-- ---------------------------------------------------------------------------
-- 2. Accounts: forced password change + login rate limiting.
-- ---------------------------------------------------------------------------
ALTER TABLE `usertbl`
  ADD COLUMN IF NOT EXISTS `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `user_type`,
  ADD COLUMN IF NOT EXISTS `email` VARCHAR(254) NOT NULL DEFAULT '' AFTER `must_change_password`;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `ID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `idx_login_attempts_user_ip` (`username`, `ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `ID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `accountID` INT UNSIGNED NOT NULL,
  `selector` CHAR(16) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `uq_password_resets_selector` (`selector`),
  KEY `idx_password_resets_account` (`accountID`),
  CONSTRAINT `fk_password_resets_account` FOREIGN KEY (`accountID`)
    REFERENCES `usertbl` (`ID`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 3. Notifications (in-app; email is an optional mail() hook, see APPLY-V3).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `ID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `accountID` INT UNSIGNED NOT NULL,
  `type` VARCHAR(50) NOT NULL DEFAULT 'general',
  `title` VARCHAR(200) NOT NULL,
  `body` TEXT NOT NULL,
  `link` VARCHAR(255) NOT NULL DEFAULT '',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `idx_notifications_account` (`accountID`, `is_read`, `created_at`),
  CONSTRAINT `fk_notifications_account` FOREIGN KEY (`accountID`)
    REFERENCES `usertbl` (`ID`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 4. Audit log.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_log` (
  `ID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_userID` INT UNSIGNED NULL DEFAULT NULL,
  `actor_role` VARCHAR(20) NOT NULL DEFAULT '',
  `action` VARCHAR(100) NOT NULL,
  `target_type` VARCHAR(50) NOT NULL DEFAULT '',
  `target_id` VARCHAR(50) NOT NULL DEFAULT '',
  `details` TEXT NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `idx_audit_created` (`created_at`),
  KEY `idx_audit_actor` (`actor_userID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------

-- ---------------------------------------------------------------------------
-- 4. Safe demo-data top-up (from the v3 seed file, terms + announcements
-- only). These are INSERT IGNORE / WHERE NOT EXISTS, so re-running is safe.
-- Students and enrollments are NEVER re-seeded into an existing database.
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `terms` (`school_year`, `semester`, `start_date`, `end_date`, `is_current`, `enrollment_open`) VALUES
('2025-2026', '2nd Semester', '2026-01-05', '2026-05-29', 0, 0),
('2026-2027', '2nd Semester', '2027-01-04', '2027-05-28', 0, 1),
('2026-2027', 'Summer', '2027-06-07', '2027-07-30', 0, 1);

INSERT INTO `announcements` (`title`, `body`, `is_published`, `created_at`)
SELECT 'Enrollment for 2nd Semester 2026-2027 is open', 'Submit your enrollment form before January 15, 2027. Bring your Form 137 and PSA birth certificate.', 1, '2026-08-15 08:00:00'
WHERE NOT EXISTS (SELECT 1 FROM `announcements` WHERE `title` = 'Enrollment for 2nd Semester 2026-2027 is open');
INSERT INTO `announcements` (`title`, `body`, `is_published`, `created_at`)
SELECT 'Cashier hours during enrollment', 'The cashier is open Monday to Friday, 8:00 AM to 4:00 PM. GCash payments are verified within one working day.', 1, '2026-08-15 08:05:00'
WHERE NOT EXISTS (SELECT 1 FROM `announcements` WHERE `title` = 'Cashier hours during enrollment');
