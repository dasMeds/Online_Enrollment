-- Back up the database before applying this additive migration.
-- Adds metadata expected by the enrollment report/history to legacy installs.
USE `online_enroll`;

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
