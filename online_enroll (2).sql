-- Fresh-install schema for the Online Enrollment application.
-- Existing password values are legacy plaintext samples; the login flow hashes
-- each password after its first successful sign-in.
CREATE DATABASE IF NOT EXISTS `online_enroll`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `online_enroll`;

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `courses_tbl` (
  `course_ID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_code` VARCHAR(20) NOT NULL,
  `course_title` VARCHAR(100) NOT NULL,
  `course_desc` TEXT NOT NULL,
  PRIMARY KEY (`course_ID`),
  UNIQUE KEY `uq_courses_code` (`course_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `usertbl` (
  `ID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `fullname` VARCHAR(150) NOT NULL,
  `user_type` ENUM('admin', 'student') NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `uq_usertbl_username` (`username`),
  KEY `idx_usertbl_type` (`user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sections_tbl` (
  `section_ID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `section_name` VARCHAR(100) NOT NULL,
  `section_course` VARCHAR(20) NOT NULL,
  `yr_level` TINYINT UNSIGNED NOT NULL,
  `max_num` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `min_num` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`section_ID`),
  UNIQUE KEY `uq_section_course_year` (`section_name`, `section_course`, `yr_level`),
  KEY `idx_sections_course` (`section_course`),
  CONSTRAINT `fk_sections_course` FOREIGN KEY (`section_course`)
    REFERENCES `courses_tbl` (`course_code`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_section_capacity` CHECK (`max_num` = 0 OR `min_num` <= `max_num`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `subjecttbl` (
  `ID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subjectcode` VARCHAR(30) NOT NULL,
  `subjecttitle` VARCHAR(150) NOT NULL,
  `numofunits` TINYINT UNSIGNED NOT NULL,
  `room` VARCHAR(100) NOT NULL DEFAULT '',
  `time` VARCHAR(100) NOT NULL DEFAULT '',
  `instructor` VARCHAR(150) NOT NULL DEFAULT '',
  `day` VARCHAR(50) NOT NULL DEFAULT '',
  `islab` TINYINT(1) NOT NULL DEFAULT 0,
  `istutorial` TINYINT(1) NOT NULL DEFAULT 0,
  `description` TEXT NOT NULL,
  `course_con` VARCHAR(20) NOT NULL,
  `semester` ENUM('1st Semester', '2nd Semester', 'Summer') NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `uq_subject_code` (`subjectcode`),
  KEY `idx_subject_course_semester` (`course_con`, `semester`, `subjectcode`),
  CONSTRAINT `fk_subject_course` FOREIGN KEY (`course_con`)
    REFERENCES `courses_tbl` (`course_code`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_subject_units` CHECK (`numofunits` > 0),
  CONSTRAINT `chk_subject_lab` CHECK (`islab` IN (0, 1)),
  CONSTRAINT `chk_subject_tutorial` CHECK (`istutorial` IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `studenttbl` (
  `ID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `accountID` INT UNSIGNED NOT NULL,
  `studnum` VARCHAR(20) NOT NULL,
  `fname` VARCHAR(100) NOT NULL,
  `minitial` VARCHAR(20) NOT NULL DEFAULT '',
  `lname` VARCHAR(100) NOT NULL,
  `address` VARCHAR(500) NOT NULL DEFAULT '',
  `contact` VARCHAR(30) NOT NULL DEFAULT '',
  `emailaddress` VARCHAR(254) NOT NULL DEFAULT '',
  `birthdate` DATE DEFAULT NULL,
  `course` VARCHAR(20) NOT NULL,
  `yearlvl` VARCHAR(50) NOT NULL DEFAULT '',
  `gender` VARCHAR(20) NOT NULL DEFAULT '',
  `fathername` VARCHAR(150) NOT NULL DEFAULT '',
  `mothername` VARCHAR(150) NOT NULL DEFAULT '',
  `guardianname` VARCHAR(150) NOT NULL DEFAULT '',
  `birthplace` VARCHAR(150) NOT NULL DEFAULT '',
  `civilstatus` VARCHAR(30) NOT NULL DEFAULT '',
  `elemschool` VARCHAR(150) NOT NULL DEFAULT '',
  `dategradelem` VARCHAR(20) NOT NULL DEFAULT '',
  `hsschool` VARCHAR(150) NOT NULL DEFAULT '',
  `datehsgrad` VARCHAR(20) NOT NULL DEFAULT '',
  `lastgrad` VARCHAR(150) NOT NULL DEFAULT '',
  `datelastgrad` VARCHAR(20) NOT NULL DEFAULT '',
  `addresslastgrad` VARCHAR(500) NOT NULL DEFAULT '',
  PRIMARY KEY (`ID`),
  UNIQUE KEY `uq_student_account` (`accountID`),
  UNIQUE KEY `uq_student_number` (`studnum`),
  KEY `idx_student_course` (`course`),
  CONSTRAINT `fk_student_account` FOREIGN KEY (`accountID`)
    REFERENCES `usertbl` (`ID`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_student_course` FOREIGN KEY (`course`)
    REFERENCES `courses_tbl` (`course_code`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sched_tbl` (
  `sched_ID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `section_ID` INT UNSIGNED NOT NULL,
  `subject_ID` INT UNSIGNED NOT NULL,
  `Instructor_ID` INT UNSIGNED NOT NULL DEFAULT 0,
  `time_from` TIME NOT NULL,
  `time_to` TIME NOT NULL,
  `room_ID` INT UNSIGNED NOT NULL DEFAULT 0,
  `semester` ENUM('1st Semester', '2nd Semester', 'Summer') NOT NULL,
  `day` ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') NOT NULL,
  PRIMARY KEY (`sched_ID`),
  UNIQUE KEY `uq_section_subject_schedule` (`section_ID`, `subject_ID`),
  KEY `idx_schedule_section_time` (`section_ID`, `time_from`),
  KEY `idx_schedule_subject` (`subject_ID`),
  CONSTRAINT `fk_schedule_section` FOREIGN KEY (`section_ID`)
    REFERENCES `sections_tbl` (`section_ID`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_schedule_subject` FOREIGN KEY (`subject_ID`)
    REFERENCES `subjecttbl` (`ID`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `chk_schedule_time` CHECK (`time_to` > `time_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_subject` (
  `ID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studentID` VARCHAR(20) NOT NULL,
  `subjects` TEXT NOT NULL,
  `totalunits` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `approved` TINYINT(1) NOT NULL DEFAULT 0,
  `semester` ENUM('1st Semester', '2nd Semester', 'Summer') NOT NULL DEFAULT '1st Semester',
  `school_year` VARCHAR(9) NOT NULL DEFAULT '',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `uq_enrollment_student_term` (`studentID`, `semester`, `school_year`),
  KEY `idx_enrollment_student_date` (`studentID`, `created_at`),
  KEY `idx_enrollment_created_at` (`created_at`, `ID`),
  CONSTRAINT `fk_enrollment_student` FOREIGN KEY (`studentID`)
    REFERENCES `studenttbl` (`studnum`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `chk_enrollment_approved` CHECK (`approved` IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_subject_items` (
  `ID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `enrollment_ID` INT UNSIGNED NOT NULL,
  `subject_ID` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `uq_enrollment_subject` (`enrollment_ID`, `subject_ID`),
  KEY `idx_enrollment_subject_item` (`subject_ID`),
  CONSTRAINT `fk_enrollment_item_enrollment` FOREIGN KEY (`enrollment_ID`)
    REFERENCES `student_subject` (`ID`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_enrollment_item_subject` FOREIGN KEY (`subject_ID`)
    REFERENCES `subjecttbl` (`ID`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `courses_tbl` (`course_ID`, `course_code`, `course_title`, `course_desc`) VALUES
(1, 'bsit', 'Bachelor of Science in Information Technology', 'Applied computing, software, infrastructure, and information systems.'),
(2, 'bscs', 'Bachelor of Science in Computer Science', 'Computing theory, algorithms, intelligent systems, and software design.'),
(3, 'bsba', 'Bachelor of Science in Business Administration', 'Management, marketing, operations, finance, and business analytics.'),
(4, 'bsa', 'Bachelor of Science in Accountancy', 'Financial reporting, auditing, taxation, and professional accounting practice.'),
(5, 'bsed', 'Bachelor of Secondary Education', 'Teaching, curriculum design, assessment, and secondary education practice.'),
(6, 'bsce', 'Bachelor of Science in Civil Engineering', 'Structural, geotechnical, transportation, and water resources engineering.'),
(7, 'bsn', 'Bachelor of Science in Nursing', 'Nursing science, patient care, health promotion, and clinical practice.'),
(8, 'bshm', 'Bachelor of Science in Hospitality Management', 'Hospitality operations, food service, lodging, and guest experience.'),
(9, 'bspsy', 'Bachelor of Science in Psychology', 'Psychological science, research, assessment, and community practice.'),
(10, 'bsarch', 'Bachelor of Science in Architecture', 'Architectural design, building technology, structures, and the built environment.');

-- Demo credentials: admins/admins, registrar/Registrar!2026, dennis/123456.
-- Other mock students use Student!2026. All stored values are password_hash() hashes.
INSERT IGNORE INTO `usertbl` (`ID`, `username`, `password`, `fullname`, `user_type`) VALUES
(2, 'admins', '$2y$12$U8EtaIy49nHtqe.i2xBk0u6MpcA1aladjrsKMHZfBjU6VOyto8Dsy', 'College Administrator', 'admin'),
(3, 'registrar', '$2y$12$8VeNIoNIEzAwHMoGyyLUceRgBO6vFH6A18f2SrtFew7Wnf76/WNLm', 'Registrar Office', 'admin'),
(6, 'dennis', '$2y$12$psgAIqjLm6EKiOsEzVFs1.IMA5NnTvu/tP7/nS9kneU8NRv3EfRw.', 'Dennis Flores', 'student'),
(7, 'maria.santos', '$2y$12$BVkI9UInpdOViL5InYJOt.nQCZgn50eSmeVVtcUxms/W4J.s3Bmr6', 'Maria Santos', 'student'),
(8, 'joshua.reyes', '$2y$12$BVkI9UInpdOViL5InYJOt.nQCZgn50eSmeVVtcUxms/W4J.s3Bmr6', 'Joshua Reyes', 'student'),
(9, 'isabel.cruz', '$2y$12$BVkI9UInpdOViL5InYJOt.nQCZgn50eSmeVVtcUxms/W4J.s3Bmr6', 'Isabel Cruz', 'student'),
(10, 'rafael.garcia', '$2y$12$BVkI9UInpdOViL5InYJOt.nQCZgn50eSmeVVtcUxms/W4J.s3Bmr6', 'Rafael Garcia', 'student'),
(11, 'camille.ramos', '$2y$12$BVkI9UInpdOViL5InYJOt.nQCZgn50eSmeVVtcUxms/W4J.s3Bmr6', 'Camille Ramos', 'student'),
(12, 'luis.mendoza', '$2y$12$BVkI9UInpdOViL5InYJOt.nQCZgn50eSmeVVtcUxms/W4J.s3Bmr6', 'Luis Mendoza', 'student'),
(13, 'ana.delacruz', '$2y$12$BVkI9UInpdOViL5InYJOt.nQCZgn50eSmeVVtcUxms/W4J.s3Bmr6', 'Ana Dela Cruz', 'student'),
(14, 'marco.bautista', '$2y$12$BVkI9UInpdOViL5InYJOt.nQCZgn50eSmeVVtcUxms/W4J.s3Bmr6', 'Marco Bautista', 'student'),
(15, 'sofia.flores', '$2y$12$BVkI9UInpdOViL5InYJOt.nQCZgn50eSmeVVtcUxms/W4J.s3Bmr6', 'Sofia Flores', 'student'),
(16, 'gabriel.torres', '$2y$12$BVkI9UInpdOViL5InYJOt.nQCZgn50eSmeVVtcUxms/W4J.s3Bmr6', 'Gabriel Torres', 'student'),
(17, 'elena.castillo', '$2y$12$BVkI9UInpdOViL5InYJOt.nQCZgn50eSmeVVtcUxms/W4J.s3Bmr6', 'Elena Castillo', 'student');

INSERT IGNORE INTO `studenttbl` (`ID`, `accountID`, `studnum`, `fname`, `minitial`, `lname`, `address`, `contact`, `emailaddress`, `birthdate`, `course`, `yearlvl`, `gender`, `fathername`, `mothername`, `guardianname`, `birthplace`, `civilstatus`, `elemschool`, `dategradelem`, `hsschool`, `datehsgrad`, `lastgrad`, `datelastgrad`, `addresslastgrad`) VALUES
(2, 6, '2026-0001', 'Dennis', 'A', 'Flores', '12 Sampaguita Street, Calapan City', '09171230001', 'dennis.flores@example.test', '2005-04-12', 'bsit', '1st Year', 'male', 'Ramon Flores', 'Elena Flores', 'Ramon Flores', 'Calapan City', 'single', 'Calapan Central School', '2017', 'Oriental Mindoro National High School', '2023', 'Oriental Mindoro National High School', '2023', 'Calapan City, Oriental Mindoro'),
(3, 7, '2026-0002', 'Maria', 'L', 'Santos', '18 Mabini Avenue, Naujan', '09171230002', 'maria.santos@example.test', '2004-09-22', 'bsit', '1st Year', 'female', 'Pedro Santos', 'Lourdes Santos', 'Lourdes Santos', 'Naujan', 'single', 'Naujan Central School', '2016', 'Naujan National High School', '2022', 'Naujan National High School', '2022', 'Naujan, Oriental Mindoro'),
(4, 8, '2026-0003', 'Joshua', 'M', 'Reyes', '42 Bonifacio Street, Pinamalayan', '09171230003', 'joshua.reyes@example.test', '2004-02-16', 'bscs', '2nd Year', 'male', 'Andres Reyes', 'Mila Reyes', 'Andres Reyes', 'Pinamalayan', 'single', 'Pinamalayan Central School', '2016', 'Pinamalayan National High School', '2022', 'Pinamalayan National High School', '2022', 'Pinamalayan, Oriental Mindoro'),
(5, 9, '2026-0004', 'Isabel', 'C', 'Cruz', '7 Rizal Street, Roxas', '09171230004', 'isabel.cruz@example.test', '2005-07-08', 'bscs', '2nd Year', 'female', 'Rogelio Cruz', 'Celia Cruz', 'Celia Cruz', 'Roxas', 'single', 'Roxas Central School', '2017', 'Roxas National High School', '2023', 'Roxas National High School', '2023', 'Roxas, Oriental Mindoro'),
(6, 10, '2026-0005', 'Rafael', 'G', 'Garcia', '25 Bonifacio Street, Bansud', '09171230005', 'rafael.garcia@example.test', '2003-11-30', 'bsba', '3rd Year', 'male', 'Ramon Garcia', 'Liza Garcia', 'Ramon Garcia', 'Bansud', 'single', 'Bansud Elementary School', '2015', 'Bansud National High School', '2021', 'Bansud National High School', '2021', 'Bansud, Oriental Mindoro'),
(7, 11, '2026-0006', 'Camille', 'R', 'Ramos', '31 Bonifacio Street, Victoria', '09171230006', 'camille.ramos@example.test', '2003-05-04', 'bsa', '3rd Year', 'female', 'Carlos Ramos', 'Rosa Ramos', 'Rosa Ramos', 'Victoria', 'single', 'Victoria Elementary School', '2015', 'Victoria National High School', '2021', 'Victoria National High School', '2021', 'Victoria, Oriental Mindoro'),
(8, 12, '2026-0007', 'Luis', 'M', 'Mendoza', '6 Luna Street, Calapan City', '09171230007', 'luis.mendoza@example.test', '2005-01-17', 'bsed', '1st Year', 'male', 'Mario Mendoza', 'Nina Mendoza', 'Mario Mendoza', 'Calapan City', 'single', 'Baco Central School', '2017', 'Baco National High School', '2023', 'Baco National High School', '2023', 'Calapan City, Oriental Mindoro'),
(9, 13, '2026-0008', 'Ana', 'P', 'Dela Cruz', '14 Luna Street, Naujan', '09171230008', 'ana.delacruz@example.test', '2004-12-03', 'bsce', '1st Year', 'female', 'Pablo Dela Cruz', 'Teresa Dela Cruz', 'Teresa Dela Cruz', 'Naujan', 'single', 'Naujan Central School', '2016', 'Naujan National High School', '2022', 'Naujan National High School', '2022', 'Naujan, Oriental Mindoro'),
(10, 14, '2026-0009', 'Marco', 'T', 'Bautista', '9 Luna Street, Pinamalayan', '09171230009', 'marco.bautista@example.test', '2004-06-11', 'bsn', '2nd Year', 'male', 'Tomas Bautista', 'Linda Bautista', 'Tomas Bautista', 'Pinamalayan', 'single', 'Pinamalayan Central School', '2016', 'Pinamalayan National High School', '2022', 'Pinamalayan National High School', '2022', 'Pinamalayan, Oriental Mindoro'),
(11, 15, '2026-0010', 'Sofia', 'R', 'Flores', '22 Luna Street, Roxas', '09171230010', 'sofia.flores@example.test', '2004-03-19', 'bshm', '2nd Year', 'female', 'Ramon Flores', 'Elena Flores', 'Elena Flores', 'Roxas', 'single', 'Roxas Central School', '2016', 'Roxas National High School', '2022', 'Roxas National High School', '2022', 'Roxas, Oriental Mindoro'),
(12, 16, '2026-0011', 'Gabriel', 'A', 'Torres', '33 Luna Street, Bansud', '09171230011', 'gabriel.torres@example.test', '2003-08-27', 'bspsy', '3rd Year', 'male', 'Arturo Torres', 'Grace Torres', 'Arturo Torres', 'Bansud', 'single', 'Bansud Elementary School', '2015', 'Bansud National High School', '2021', 'Bansud National High School', '2021', 'Bansud, Oriental Mindoro'),
(13, 17, '2026-0012', 'Elena', 'M', 'Castillo', '5 Luna Street, Victoria', '09171230012', 'elena.castillo@example.test', '2003-10-06', 'bsarch', '3rd Year', 'female', 'Manuel Castillo', 'Maria Castillo', 'Maria Castillo', 'Victoria', 'single', 'Victoria Elementary School', '2015', 'Victoria National High School', '2021', 'Victoria National High School', '2021', 'Victoria, Oriental Mindoro');

INSERT IGNORE INTO `sections_tbl` (`section_ID`, `section_name`, `section_course`, `yr_level`, `max_num`, `min_num`) VALUES
(1, 'BSIT-1A', 'bsit', 1, 40, 10),
(2, 'BSIT-2A', 'bsit', 2, 40, 10),
(3, 'BSCS-1A', 'bscs', 1, 40, 10),
(4, 'BSCS-2A', 'bscs', 2, 40, 10),
(5, 'BSBA-1A', 'bsba', 1, 40, 10),
(6, 'BSA-1A', 'bsa', 1, 40, 10),
(7, 'BSED-1A', 'bsed', 1, 40, 10),
(8, 'BSCE-1A', 'bsce', 1, 40, 10),
(9, 'BSN-1A', 'bsn', 1, 40, 10),
(10, 'BSHM-1A', 'bshm', 1, 40, 10),
(11, 'BSPSY-1A', 'bspsy', 1, 40, 10),
(12, 'BSARCH-1A', 'bsarch', 1, 40, 10);

-- Ten curated subjects per program (100 total), distributed across the academic year.
INSERT IGNORE INTO `subjecttbl`
  (`subjectcode`, `subjecttitle`, `numofunits`, `room`, `time`, `instructor`, `day`, `islab`, `istutorial`, `description`, `course_con`, `semester`)
VALUES
('IT101', 'Programming Fundamentals', 3, '', '', '', '', 1, 0, 'Programming concepts, control structures, functions, and problem solving.', 'bsit', '1st Semester'),
('IT102', 'Discrete Mathematics for Computing', 3, '', '', '', '', 0, 0, 'Logic, sets, relations, counting, graphs, and discrete structures for IT.', 'bsit', '1st Semester'),
('IT103', 'Database Management Systems', 3, '', '', '', '', 1, 0, 'Relational data modeling, SQL, normalization, and database applications.', 'bsit', '1st Semester'),
('IT104', 'Computer Networks and Communications', 3, '', '', '', '', 1, 0, 'Network architectures, protocols, addressing, and communication services.', 'bsit', '1st Semester'),
('IT105', 'Human-Computer Interaction', 3, '', '', '', '', 0, 0, 'User-centered design, usability evaluation, accessibility, and prototyping.', 'bsit', '1st Semester'),
('IT106', 'Web Application Development', 3, '', '', '', '', 1, 0, 'Client-server development, web standards, APIs, and secure application practices.', 'bsit', '2nd Semester'),
('IT107', 'Systems Analysis and Design', 3, '', '', '', '', 0, 0, 'Requirements analysis, process modeling, system design, and project documentation.', 'bsit', '2nd Semester'),
('IT108', 'Cybersecurity Fundamentals', 3, '', '', '', '', 1, 0, 'Security principles, threat analysis, access control, and defensive operations.', 'bsit', '2nd Semester'),
('IT109', 'Cloud Computing and Virtualization', 3, '', '', '', '', 1, 0, 'Virtual infrastructure, cloud service models, deployment, and operations.', 'bsit', '2nd Semester'),
('IT110', 'Information Technology Industry Practicum', 5, '', '', '', '', 0, 0, 'Supervised practical experience applying IT skills in a professional setting.', 'bsit', 'Summer'),
('CS101', 'Introduction to Computer Science', 3, '', '', '', '', 1, 0, 'Computing foundations, programming concepts, and computational problem solving.', 'bscs', '1st Semester'),
('CS102', 'Discrete Structures', 3, '', '', '', '', 0, 0, 'Mathematical logic, proof techniques, combinatorics, graphs, and relations.', 'bscs', '1st Semester'),
('CS103', 'Data Structures and Algorithms', 4, '', '', '', '', 1, 0, 'Abstract data types, algorithm analysis, searching, sorting, and graph algorithms.', 'bscs', '1st Semester'),
('CS104', 'Computer Organization and Architecture', 3, '', '', '', '', 1, 0, 'Digital representation, processor organization, memory systems, and machine instructions.', 'bscs', '1st Semester'),
('CS105', 'Calculus for Computer Science', 3, '', '', '', '', 0, 0, 'Differential and integral calculus with applications to computing and modeling.', 'bscs', '1st Semester'),
('CS106', 'Operating Systems', 3, '', '', '', '', 1, 0, 'Processes, concurrency, memory management, file systems, and operating system design.', 'bscs', '2nd Semester'),
('CS107', 'Theory of Computation', 3, '', '', '', '', 0, 0, 'Formal languages, automata, computability, and complexity fundamentals.', 'bscs', '2nd Semester'),
('CS108', 'Database Systems', 3, '', '', '', '', 1, 0, 'Relational and nonrelational models, query processing, transactions, and database design.', 'bscs', '2nd Semester'),
('CS109', 'Software Engineering', 3, '', '', '', '', 1, 0, 'Software lifecycle methods, requirements, architecture, testing, and maintenance.', 'bscs', '2nd Semester'),
('CS110', 'Computer Science Research Practicum', 5, '', '', '', '', 0, 0, 'Supervised research or project work applying computer science methods.', 'bscs', 'Summer'),
('BA101', 'Principles of Management', 3, '', '', '', '', 0, 0, 'Management functions, organizational structures, leadership, and decision making.', 'bsba', '1st Semester'),
('BA102', 'Principles of Marketing', 3, '', '', '', '', 0, 0, 'Marketing environments, consumer behavior, segmentation, and the marketing mix.', 'bsba', '1st Semester'),
('BA103', 'Business Mathematics', 3, '', '', '', '', 0, 0, 'Quantitative methods for business, including finance, algebra, and decision analysis.', 'bsba', '1st Semester'),
('BA104', 'Financial Accounting for Business', 3, '', '', '', '', 1, 0, 'Accounting cycle, financial statements, and interpretation for business decisions.', 'bsba', '1st Semester'),
('BA105', 'Business Communication', 3, '', '', '', '', 0, 0, 'Professional writing, presentations, workplace communication, and business correspondence.', 'bsba', '1st Semester'),
('BA106', 'Human Resource Management', 3, '', '', '', '', 0, 0, 'Staffing, employee development, performance management, and labor relations.', 'bsba', '2nd Semester'),
('BA107', 'Operations and Supply Chain Management', 3, '', '', '', '', 0, 0, 'Process design, quality, inventory, logistics, and supply chain coordination.', 'bsba', '2nd Semester'),
('BA108', 'Managerial Economics', 3, '', '', '', '', 0, 0, 'Economic principles applied to pricing, production, markets, and firm decisions.', 'bsba', '2nd Semester'),
('BA109', 'Business Analytics', 3, '', '', '', '', 1, 0, 'Data preparation, descriptive analysis, visualization, and evidence-based business decisions.', 'bsba', '2nd Semester'),
('BA110', 'Business Administration Internship', 5, '', '', '', '', 0, 0, 'Supervised workplace experience in a business administration setting.', 'bsba', 'Summer'),
('AC101', 'Fundamentals of Accounting', 3, '', '', '', '', 1, 0, 'Accounting concepts, transaction analysis, and preparation of basic financial statements.', 'bsa', '1st Semester'),
('AC102', 'Financial Accounting and Reporting 1', 3, '', '', '', '', 1, 0, 'Recognition, measurement, and reporting of assets and current liabilities.', 'bsa', '1st Semester'),
('AC103', 'Cost Accounting', 3, '', '', '', '', 1, 0, 'Cost behavior, job and process costing, budgeting, and cost control.', 'bsa', '1st Semester'),
('AC104', 'Business Law and Obligations', 3, '', '', '', '', 0, 0, 'Legal principles governing contracts, obligations, commercial transactions, and business entities.', 'bsa', '1st Semester'),
('AC105', 'Financial Management', 3, '', '', '', '', 0, 0, 'Financial analysis, capital budgeting, working capital, and financing decisions.', 'bsa', '1st Semester'),
('AC106', 'Financial Accounting and Reporting 2', 3, '', '', '', '', 1, 0, 'Accounting for liabilities, equity, revenue, leases, and financial instruments.', 'bsa', '2nd Semester'),
('AC107', 'Auditing Principles and Assurance', 3, '', '', '', '', 0, 0, 'Audit standards, risk assessment, evidence gathering, and assurance reporting.', 'bsa', '2nd Semester'),
('AC108', 'Taxation of Individuals and Business', 3, '', '', '', '', 0, 0, 'Tax laws, tax computation, compliance, and planning for individuals and enterprises.', 'bsa', '2nd Semester'),
('AC109', 'Accounting Information Systems', 3, '', '', '', '', 1, 0, 'Accounting processes, internal controls, information systems, and data integrity.', 'bsa', '2nd Semester'),
('AC110', 'Professional Accounting Internship', 5, '', '', '', '', 0, 0, 'Supervised experience in accounting, audit, or finance practice.', 'bsa', 'Summer'),
('ED101', 'Foundations of Education', 3, '', '', '', '', 0, 0, 'Historical, philosophical, social, and legal foundations of Philippine education.', 'bsed', '1st Semester'),
('ED102', 'Child and Adolescent Development', 3, '', '', '', '', 0, 0, 'Physical, cognitive, social, and emotional development across adolescence.', 'bsed', '1st Semester'),
('ED103', 'Principles of Teaching', 3, '', '', '', '', 0, 0, 'Learning theories, instructional strategies, lesson design, and reflective teaching.', 'bsed', '1st Semester'),
('ED104', 'Assessment of Student Learning', 3, '', '', '', '', 1, 0, 'Assessment design, measurement, feedback, and interpretation of learner progress.', 'bsed', '1st Semester'),
('ED105', 'Educational Technology', 3, '', '', '', '', 1, 0, 'Selecting and using digital tools and media for inclusive, effective instruction.', 'bsed', '1st Semester'),
('ED106', 'Curriculum Development', 3, '', '', '', '', 0, 0, 'Curriculum models, standards alignment, instructional planning, and evaluation.', 'bsed', '2nd Semester'),
('ED107', 'Inclusive and Special Needs Education', 3, '', '', '', '', 0, 0, 'Inclusive pedagogy, learner diversity, accommodations, and support strategies.', 'bsed', '2nd Semester'),
('ED108', 'Classroom Management', 3, '', '', '', '', 0, 0, 'Positive learning environments, behavior support, classroom routines, and safety.', 'bsed', '2nd Semester'),
('ED109', 'Research in Education', 3, '', '', '', '', 1, 0, 'Educational research questions, methods, ethics, analysis, and reporting.', 'bsed', '2nd Semester'),
('ED110', 'Secondary Teaching Internship', 5, '', '', '', '', 0, 0, 'Supervised field teaching, lesson delivery, assessment, and professional reflection.', 'bsed', 'Summer'),
('CE101', 'Engineering Mathematics 1', 4, '', '', '', '', 0, 0, 'Differential calculus and mathematical methods for engineering applications.', 'bsce', '1st Semester'),
('CE102', 'Engineering Mechanics: Statics', 3, '', '', '', '', 1, 0, 'Force systems, equilibrium, trusses, frames, and friction in engineering structures.', 'bsce', '1st Semester'),
('CE103', 'Engineering Drawing and Computer-Aided Design', 3, '', '', '', '', 1, 0, 'Technical drawing standards, spatial visualization, and computer-aided design.', 'bsce', '1st Semester'),
('CE104', 'Construction Materials and Testing', 3, '', '', '', '', 1, 0, 'Properties, selection, testing, and quality control of civil engineering materials.', 'bsce', '1st Semester'),
('CE105', 'Surveying and Geomatics', 3, '', '', '', '', 1, 0, 'Field surveying, measurement, leveling, mapping, and geospatial data basics.', 'bsce', '1st Semester'),
('CE106', 'Structural Analysis', 4, '', '', '', '', 1, 0, 'Analysis of determinate and indeterminate beams, frames, and trusses.', 'bsce', '2nd Semester'),
('CE107', 'Geotechnical Engineering', 3, '', '', '', '', 1, 0, 'Soil properties, site investigation, foundations, and earth-retaining structures.', 'bsce', '2nd Semester'),
('CE108', 'Hydraulics and Fluid Mechanics', 3, '', '', '', '', 1, 0, 'Fluid statics, pipe flow, open channels, and hydraulic engineering applications.', 'bsce', '2nd Semester'),
('CE109', 'Construction Project Management', 3, '', '', '', '', 0, 0, 'Construction planning, estimating, scheduling, contracts, and site safety.', 'bsce', '2nd Semester'),
('CE110', 'Civil Engineering Field Practice', 5, '', '', '', '', 0, 0, 'Supervised field exposure in civil engineering investigation and construction practice.', 'bsce', 'Summer'),
('NU101', 'Anatomy and Physiology for Nursing', 3, '', '', '', '', 1, 0, 'Human body structure and function with applications to nursing care.', 'bsn', '1st Semester'),
('NU102', 'Fundamentals of Nursing Practice', 4, '', '', '', '', 1, 0, 'Core nursing skills, patient safety, basic care, and clinical documentation.', 'bsn', '1st Semester'),
('NU103', 'Health Assessment', 3, '', '', '', '', 1, 0, 'Interviewing, physical assessment, clinical reasoning, and health documentation.', 'bsn', '1st Semester'),
('NU104', 'Pharmacology for Nursing Practice', 3, '', '', '', '', 0, 0, 'Medication principles, drug classifications, safe administration, and monitoring.', 'bsn', '1st Semester'),
('NU105', 'Nursing Ethics and Jurisprudence', 3, '', '', '', '', 0, 0, 'Ethical decision making, professional standards, patient rights, and nursing law.', 'bsn', '1st Semester'),
('NU106', 'Maternal and Child Health Nursing', 4, '', '', '', '', 1, 0, 'Family-centered nursing across pregnancy, childbirth, infancy, and childhood.', 'bsn', '2nd Semester'),
('NU107', 'Medical-Surgical Nursing 1', 4, '', '', '', '', 1, 0, 'Nursing management of common adult health conditions across care settings.', 'bsn', '2nd Semester'),
('NU108', 'Community Health Nursing', 3, '', '', '', '', 1, 0, 'Population health assessment, prevention, community partnerships, and health promotion.', 'bsn', '2nd Semester'),
('NU109', 'Nursing Research and Evidence-Based Practice', 3, '', '', '', '', 0, 0, 'Research appraisal, evidence-based decisions, and improvement of nursing care.', 'bsn', '2nd Semester'),
('NU110', 'Supervised Clinical Nursing Practicum', 5, '', '', '', '', 1, 0, 'Supervised clinical experience integrating safe, patient-centered nursing practice.', 'bsn', 'Summer'),
('HM101', 'Principles of Hospitality Management', 3, '', '', '', '', 0, 0, 'Hospitality sectors, service organizations, management functions, and guest experience.', 'bshm', '1st Semester'),
('HM102', 'Food and Beverage Service', 3, '', '', '', '', 1, 0, 'Restaurant service standards, mise en place, guest relations, and service operations.', 'bshm', '1st Semester'),
('HM103', 'Culinary Fundamentals', 4, '', '', '', '', 1, 0, 'Kitchen safety, ingredients, basic cooking methods, and food preparation.', 'bshm', '1st Semester'),
('HM104', 'Front Office Operations', 3, '', '', '', '', 1, 0, 'Reservations, reception, guest registration, information, and front desk procedures.', 'bshm', '1st Semester'),
('HM105', 'Sanitation and Food Safety', 3, '', '', '', '', 1, 0, 'Food safety systems, sanitation standards, hazard control, and workplace hygiene.', 'bshm', '1st Semester'),
('HM106', 'Housekeeping Operations', 3, '', '', '', '', 1, 0, 'Guestroom care, cleaning systems, housekeeping supervision, and inventory control.', 'bshm', '2nd Semester'),
('HM107', 'Hospitality Marketing', 3, '', '', '', '', 0, 0, 'Hospitality markets, customer behavior, promotion, branding, and digital channels.', 'bshm', '2nd Semester'),
('HM108', 'Event Planning and Management', 3, '', '', '', '', 1, 0, 'Event concepts, budgeting, logistics, risk planning, and event evaluation.', 'bshm', '2nd Semester'),
('HM109', 'Revenue and Guest Service Management', 3, '', '', '', '', 0, 0, 'Service quality, revenue strategies, guest feedback, and hospitality performance.', 'bshm', '2nd Semester'),
('HM110', 'Hospitality Industry Practicum', 5, '', '', '', '', 0, 0, 'Supervised workplace experience in lodging, food service, or tourism operations.', 'bshm', 'Summer'),
('PS101', 'General Psychology', 3, '', '', '', '', 0, 0, 'Core psychological concepts, major perspectives, research, and applications.', 'bspsy', '1st Semester'),
('PS102', 'Developmental Psychology', 3, '', '', '', '', 0, 0, 'Human development across the lifespan and the biological and social factors involved.', 'bspsy', '1st Semester'),
('PS103', 'Psychological Statistics', 3, '', '', '', '', 1, 0, 'Descriptive and inferential statistics for behavioral science data.', 'bspsy', '1st Semester'),
('PS104', 'Research Methods in Psychology', 3, '', '', '', '', 1, 0, 'Experimental and nonexperimental design, ethical research, measurement, and analysis.', 'bspsy', '1st Semester'),
('PS105', 'Biological Bases of Behavior', 3, '', '', '', '', 0, 0, 'Neural, genetic, and physiological processes underlying behavior and cognition.', 'bspsy', '1st Semester'),
('PS106', 'Cognitive Psychology', 3, '', '', '', '', 0, 0, 'Attention, memory, language, perception, reasoning, and problem solving.', 'bspsy', '2nd Semester'),
('PS107', 'Abnormal Psychology', 3, '', '', '', '', 0, 0, 'Psychological disorders, diagnostic frameworks, risk factors, and evidence-based care.', 'bspsy', '2nd Semester'),
('PS108', 'Social Psychology', 3, '', '', '', '', 0, 0, 'Social influence, group processes, attitudes, relationships, and intergroup behavior.', 'bspsy', '2nd Semester'),
('PS109', 'Psychological Assessment', 3, '', '', '', '', 1, 0, 'Principles of psychological testing, assessment ethics, interpretation, and reporting.', 'bspsy', '2nd Semester'),
('PS110', 'Community Psychology Practicum', 5, '', '', '', '', 0, 0, 'Supervised community-based work applying psychological principles and ethical practice.', 'bspsy', 'Summer'),
('AR101', 'Architectural Design 1', 5, '', '', '', '', 1, 0, 'Fundamentals of spatial composition, design process, scale, and architectural presentation.', 'bsarch', '1st Semester'),
('AR102', 'Architectural Graphics and Visual Communication', 3, '', '', '', '', 1, 0, 'Architectural drawing, visual conventions, digital representation, and communication.', 'bsarch', '1st Semester'),
('AR103', 'History of Architecture', 3, '', '', '', '', 0, 0, 'Architectural movements, precedents, and cultural contexts from antiquity to today.', 'bsarch', '1st Semester'),
('AR104', 'Building Utilities and Systems', 3, '', '', '', '', 1, 0, 'Building electrical, plumbing, lighting, ventilation, and life-safety systems.', 'bsarch', '1st Semester'),
('AR105', 'Theory of Architecture', 3, '', '', '', '', 0, 0, 'Architectural concepts, design principles, critical interpretation, and professional context.', 'bsarch', '1st Semester'),
('AR106', 'Architectural Design 2', 5, '', '', '', '', 1, 0, 'Design development for small buildings with attention to site, users, and context.', 'bsarch', '2nd Semester'),
('AR107', 'Building Technology and Materials', 3, '', '', '', '', 1, 0, 'Construction methods, material properties, detailing, and building performance.', 'bsarch', '2nd Semester'),
('AR108', 'Structures for Architects', 3, '', '', '', '', 0, 0, 'Structural behavior, systems, loads, and coordination of architectural design.', 'bsarch', '2nd Semester'),
('AR109', 'Site Planning and Landscape Design', 3, '', '', '', '', 1, 0, 'Site analysis, circulation, landscape principles, environmental response, and planning.', 'bsarch', '2nd Semester'),
('AR110', 'Architectural Design Internship', 5, '', '', '', '', 0, 0, 'Supervised professional exposure to architectural practice and design documentation.', 'bsarch', 'Summer');

INSERT IGNORE INTO `sched_tbl`
  (`section_ID`, `subject_ID`, `Instructor_ID`, `time_from`, `time_to`, `room_ID`, `semester`, `day`)
SELECT
  sec.`section_ID`,
  sub.`ID`,
  100 + CAST(RIGHT(sub.`subjectcode`, 2) AS UNSIGNED),
  '08:00:00',
  '09:30:00',
  200 + CAST(RIGHT(sub.`subjectcode`, 2) AS UNSIGNED),
  sub.`semester`,
  CASE CAST(RIGHT(sub.`subjectcode`, 2) AS UNSIGNED)
    WHEN 1 THEN 'Monday'
    WHEN 2 THEN 'Tuesday'
    WHEN 3 THEN 'Wednesday'
    WHEN 4 THEN 'Thursday'
    WHEN 5 THEN 'Friday'
    WHEN 6 THEN 'Monday'
    WHEN 7 THEN 'Tuesday'
    WHEN 8 THEN 'Wednesday'
    WHEN 9 THEN 'Thursday'
    ELSE 'Monday'
  END
FROM `subjecttbl` sub
JOIN `sections_tbl` sec ON sec.`section_course` = sub.`course_con` AND sec.`yr_level` = 1;

INSERT IGNORE INTO `student_subject`
  (`ID`, `studentID`, `subjects`, `totalunits`, `approved`, `semester`, `school_year`, `created_at`)
VALUES
(101, '2026-0001', 'IT101,IT102,IT103', 9, 0, '1st Semester', '2026-2027', '2026-08-03 09:00:00'),
(102, '2026-0002', 'IT106,IT107,IT108', 9, 1, '2nd Semester', '2026-2027', '2026-08-04 10:15:00'),
(103, '2026-0003', 'CS101,CS102,CS103', 10, 1, '1st Semester', '2026-2027', '2026-08-05 09:25:00'),
(104, '2026-0004', 'CS106,CS107,CS108', 9, 0, '2nd Semester', '2026-2027', '2026-08-06 10:40:00'),
(105, '2026-0005', 'BA101,BA102,BA103', 9, 0, '1st Semester', '2026-2027', '2026-08-07 11:10:00'),
(106, '2026-0006', 'AC106,AC107,AC108', 9, 1, '2nd Semester', '2026-2027', '2026-08-08 13:05:00'),
(107, '2026-0007', 'ED101,ED102,ED103', 9, 0, '1st Semester', '2026-2027', '2026-08-09 09:25:00'),
(108, '2026-0008', 'CE106,CE107,CE108', 10, 1, '2nd Semester', '2026-2027', '2026-08-10 10:40:00'),
(109, '2026-0009', 'NU110', 5, 0, 'Summer', '2026-2027', '2026-08-11 11:10:00'),
(110, '2026-0010', 'HM101,HM102,HM103', 10, 1, '1st Semester', '2026-2027', '2026-08-12 13:05:00'),
(111, '2026-0011', 'PS106,PS107,PS108', 9, 0, '2nd Semester', '2026-2027', '2026-08-13 09:25:00'),
(112, '2026-0012', 'AR110', 5, 1, 'Summer', '2026-2027', '2026-08-14 10:40:00');

INSERT IGNORE INTO `student_subject_items` (`enrollment_ID`, `subject_ID`)
SELECT e.`ID`, s.`ID`
FROM `student_subject` e
JOIN `subjecttbl` s ON FIND_IN_SET(s.`subjectcode`, e.`subjects`) > 0
WHERE e.`ID` BETWEEN 101 AND 112;
