<?php
/* Guarded so the pure helpers (and tests/run_tests.php) still load on a
   PHP install without mysqli; XAMPP always has mysqli. */
if (function_exists('mysqli_report')) {
	mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
	ini_set('session.use_strict_mode', '1');
	session_set_cookie_params(array(
		'httponly' => true,
		'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
		'samesite' => 'Lax'
	));
	session_start();
}

/* Content-Security-Policy. The pages use small inline scripts/styles, so
   'unsafe-inline' is allowed for those two sources only; everything else
   must come from this site. Session cookies are already HttpOnly + Secure
   (when HTTPS) + SameSite=Lax (see above). */
if (!headers_sent() && PHP_SAPI !== 'cli') {
	header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; frame-ancestors 'self'; form-action 'self'; base-uri 'self'");
}

if (!function_exists('app_h')) {
	function app_h($value)
	{
		if (!is_scalar($value)) {
			return '';
		}
		return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	function app_input($name, $default = '')
	{
		return isset($_POST[$name]) && is_string($_POST[$name]) ? $_POST[$name] : $default;
	}

	function app_int_input($name, $default = -1)
	{
		$value = app_input($name);
		return ctype_digit($value) ? (int)$value : $default;
	}

	function app_bind_params($statement, $types, &$values)
	{
		$parameters = array($types);
		foreach ($values as &$value) {
			$parameters[] = &$value;
		}
		unset($value);
		return call_user_func_array(array($statement, 'bind_param'), $parameters);
	}

	function app_csrf_token()
	{
		if (empty($_SESSION['csrf_token'])) {
			$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
		}
		return $_SESSION['csrf_token'];
	}

	function app_verify_csrf()
	{
		$token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
		if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
			http_response_code(400);
			exit('Invalid request token. Please reload the page and try again.');
		}
	}

	function app_valid_date($value)
	{
		$date = DateTime::createFromFormat('!Y-m-d', (string)$value);
		return $date !== false && $date->format('Y-m-d') === (string)$value;
	}

	function app_course_options()
	{
		static $cached = null;
		if ($cached !== null) {
			return $cached;
		}
		$fallback = array(
			'bsit' => 'Bachelor of Science in Information Technology',
			'bscs' => 'Bachelor of Science in Computer Science',
			'bsba' => 'Bachelor of Science in Business Administration',
			'bsa' => 'Bachelor of Science in Accountancy',
			'bsed' => 'Bachelor of Secondary Education',
			'bsce' => 'Bachelor of Science in Civil Engineering',
			'bsn' => 'Bachelor of Science in Nursing',
			'bshm' => 'Bachelor of Science in Hospitality Management',
			'bspsy' => 'Bachelor of Science in Psychology',
			'bsarch' => 'Bachelor of Science in Architecture'
		);
		$conn = isset($GLOBALS['app_conn']) ? $GLOBALS['app_conn'] : null;
		if ($conn) {
			try {
				$result = $conn->query('SELECT course_code, course_title FROM courses_tbl ORDER BY course_code');
				$rows = array();
				while ($row = $result->fetch_assoc()) {
					$rows[strtolower((string)$row['course_code'])] = (string)$row['course_title'];
				}
				if ($rows) {
					$cached = $rows;
					return $cached;
				}
			} catch (mysqli_sql_exception $exception) {
				/* Table missing (pre-install) — use the hardcoded list. */
			}
		}
		$cached = $fallback;
		return $cached;
	}

	/* ---- Full pack v1: shared enrollment helpers ---- */
	/* Every helper that touches a v1 table catches a missing-table error and
	   falls back to the pre-v1 behaviour, so old pages still load before
	   database/migrate.sql (or database/schema.sql on a fresh install) has been imported. */

	function app_setting_get($conn, $key, $default = '')
	{
		try {
			$statement = $conn->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
			$statement->bind_param('s', $key);
			$statement->execute();
			$row = $statement->get_result()->fetch_assoc();
			return $row ? (string)$row['setting_value'] : (string)$default;
		} catch (mysqli_sql_exception $exception) {
			return (string)$default;
		}
	}

	function app_setting_set($conn, $key, $value)
	{
		$statement = $conn->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
		$statement->bind_param('ss', $key, $value);
		$statement->execute();
	}

	function app_fee_settings($conn)
	{
		return array(
			'per_unit' => (float)app_setting_get($conn, 'fee_per_unit', '296'),
			'entrance' => (float)app_setting_get($conn, 'fee_entrance', '2050'),
			'internet' => (float)app_setting_get($conn, 'fee_internet', '150'),
			'misc' => (float)app_setting_get($conn, 'fee_misc', '50'),
			'lab' => (float)app_setting_get($conn, 'fee_lab', '1500'),
			'tutorial' => (float)app_setting_get($conn, 'fee_tutorial', '1500'),
			'max_units' => (int)app_setting_get($conn, 'max_units', '24')
		);
	}

	function app_assessment_calc($fees, $totalUnits, $labCount, $tutorialCount)
	{
		$tuition = $totalUnits * $fees['per_unit'];
		$labFee = $labCount * $fees['lab'];
		$tutorialFee = $tutorialCount * $fees['tutorial'];
		$total = $tuition + $fees['entrance'] + $fees['internet'] + $fees['misc'] + $labFee + $tutorialFee;
		return array(
			'tuition' => $tuition, 'entrance' => $fees['entrance'], 'internet' => $fees['internet'],
			'misc' => $fees['misc'], 'lab' => $labFee, 'tutorial' => $tutorialFee, 'total' => $total
		);
	}

	function app_enrollment_open($conn)
	{
		/* v3: the current term is authoritative when the terms table exists. */
		$term = app_current_term($conn);
		if ($term) {
			if ((int)$term['enrollment_open'] !== 1) {
				return array('open' => false, 'reason' => 'Enrollment is currently closed for ' . $term['semester'] . ' ' . $term['school_year'] . '. Please check the announcements or contact the registrar.');
			}
			$today = date('Y-m-d');
			if (!empty($term['start_date']) && $today < $term['start_date']) {
				return array('open' => false, 'reason' => 'Enrollment opens on ' . $term['start_date'] . '.');
			}
			if (!empty($term['end_date']) && $today > $term['end_date']) {
				return array('open' => false, 'reason' => 'Enrollment closed on ' . $term['end_date'] . '. Please contact the registrar.');
			}
			return array('open' => true, 'reason' => '');
		}
		if (app_setting_get($conn, 'enrollment_open', '1') !== '1') {
			return array('open' => false, 'reason' => 'Enrollment is currently closed. Please check the announcements or contact the registrar.');
		}
		$today = date('Y-m-d');
		$start = app_setting_get($conn, 'enrollment_start', '');
		$end = app_setting_get($conn, 'enrollment_end', '');
		if ($start !== '' && $today < $start) {
			return array('open' => false, 'reason' => 'Enrollment opens on ' . $start . '.');
		}
		if ($end !== '' && $today > $end) {
			return array('open' => false, 'reason' => 'Enrollment closed on ' . $end . '. Please contact the registrar.');
		}
		return array('open' => true, 'reason' => '');
	}

	function app_published_announcements($conn)
	{
		try {
			$result = $conn->query("SELECT title, body, created_at FROM announcements WHERE is_published = 1 AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW()) ORDER BY created_at DESC LIMIT 10");
			$rows = array();
			while ($row = $result->fetch_assoc()) {
				$rows[] = $row;
			}
			return $rows;
		} catch (mysqli_sql_exception $exception) {
			return array();
		}
	}

	function app_status_label($status)
	{
		$status = strtolower(trim((string)$status));
		if ($status === 'approved') {
			return 'Approved';
		}
		if ($status === 'rejected') {
			return 'Rejected';
		}
		return 'Pending';
	}

	function app_status_class($status)
	{
		$status = strtolower(trim((string)$status));
		if ($status === 'approved') {
			return 'status-approved';
		}
		if ($status === 'rejected') {
			return 'status-rejected';
		}
		return 'status-pending';
	}

	function app_time_to_minutes($value)
	{
		$value = trim((string)$value);
		if ($value === '') {
			return null;
		}
		if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?\s*([AaPp][Mm])?$/', $value, $m)) {
			$hour = (int)$m[1];
			$minute = (int)$m[2];
			if (!empty($m[3])) {
				$isPm = strtolower($m[3]) === 'pm';
				if ($isPm && $hour < 12) {
					$hour += 12;
				}
				if (!$isPm && $hour === 12) {
					$hour = 0;
				}
			}
			if ($hour > 23 || $minute > 59) {
				return null;
			}
			return $hour * 60 + $minute;
		}
		return null;
	}

	function app_normalize_days($dayText)
	{
		$dayText = strtolower(trim((string)$dayText));
		if ($dayText === '') {
			return array();
		}
		$map = array(
			'mon' => 'Monday', 'monday' => 'Monday', 'm' => 'Monday',
			'tue' => 'Tuesday', 'tues' => 'Tuesday', 'tuesday' => 'Tuesday',
			'wed' => 'Wednesday', 'wednesday' => 'Wednesday', 'w' => 'Wednesday',
			'thu' => 'Thursday', 'thur' => 'Thursday', 'thurs' => 'Thursday', 'thursday' => 'Thursday',
			'fri' => 'Friday', 'friday' => 'Friday', 'f' => 'Friday',
			'sat' => 'Saturday', 'saturday' => 'Saturday', 's' => 'Saturday'
		);
		$days = array();
		$parts = preg_split('/[^a-z]+/', $dayText);
		foreach ($parts as $part) {
			if ($part === '') {
				continue;
			}
			if (isset($map[$part])) {
				$days[] = $map[$part];
				continue;
			}
			if (preg_match('/^(m|t|w|th|f|s)+$/', $part) && strlen($part) <= 6) {
				$i = 0;
				while ($i < strlen($part)) {
					$two = substr($part, $i, 2);
					if ($two === 'th') {
						$days[] = 'Thursday';
						$i += 2;
					} else {
						$one = substr($part, $i, 1);
						if (isset($map[$one])) {
							$days[] = $map[$one];
						}
						$i += 1;
					}
				}
			}
		}
		return array_values(array_unique($days));
	}

	function app_parse_time_range($timeText)
	{
		$timeText = trim((string)$timeText);
		if ($timeText === '') {
			return null;
		}
		if (preg_match('/^(.+?)\s*(?:-|to|–)\s*(.+)$/i', $timeText, $m)) {
			$from = app_time_to_minutes($m[1]);
			$to = app_time_to_minutes($m[2]);
			if ($from !== null && $to !== null && $to > $from) {
				return array($from, $to);
			}
		}
		return null;
	}

	function app_subject_slots($conn, $subject)
	{
		$slots = array();
		$subjectId = (int)$subject['ID'];
		/* Preferred source: sched_tbl TIME ranges (the seed fills these;
		   subjecttbl day/time are empty in the seed data). */
		try {
			$statement = $conn->prepare('SELECT day, time_from, time_to, room_ID FROM sched_tbl WHERE subject_ID = ? ORDER BY day, time_from');
			$statement->bind_param('i', $subjectId);
			$statement->execute();
			$result = $statement->get_result();
			while ($row = $result->fetch_assoc()) {
				$from = app_time_to_minutes($row['time_from']);
				$to = app_time_to_minutes($row['time_to']);
				if ($from !== null && $to !== null && $to > $from) {
					$slots[] = array(
						'day' => $row['day'],
						'from' => $from, 'to' => $to,
						'label' => $row['day'] . ' ' . substr((string)$row['time_from'], 0, 5) . '-' . substr((string)$row['time_to'], 0, 5),
						'room' => ((int)$row['room_ID'] > 0) ? ('Room ' . (int)$row['room_ID']) : (isset($subject['room']) ? (string)$subject['room'] : '')
					);
				}
			}
		} catch (mysqli_sql_exception $exception) {
			$slots = array();
		}
		if ($slots) {
			return $slots;
		}
		/* Fallback: parse subjecttbl day/time only when recognisable. */
		$range = app_parse_time_range(isset($subject['time']) ? $subject['time'] : '');
		if ($range === null) {
			return array();
		}
		foreach (app_normalize_days(isset($subject['day']) ? $subject['day'] : '') as $day) {
			$slots[] = array(
				'day' => $day, 'from' => $range[0], 'to' => $range[1],
				'label' => $day . ' ' . trim((string)$subject['time']),
				'room' => isset($subject['room']) ? (string)$subject['room'] : ''
			);
		}
		return $slots;
	}

	function app_slots_overlap($a, $b)
	{
		return $a['day'] === $b['day'] && $a['from'] < $b['to'] && $b['from'] < $a['to'];
	}

	function app_subject_capacity($conn, $subjectId)
	{
		try {
			$statement = $conn->prepare('SELECT capacity FROM subjecttbl WHERE ID = ? LIMIT 1');
			$statement->bind_param('i', $subjectId);
			$statement->execute();
			$row = $statement->get_result()->fetch_assoc();
			return $row ? max(0, (int)$row['capacity']) : 40;
		} catch (mysqli_sql_exception $exception) {
			return 40;
		}
	}

	function app_subject_enrolled_count($conn, $subjectId, $semester, $schoolYear, $excludeEnrollmentId = 0)
	{
		try {
			$statement = $conn->prepare("SELECT COUNT(*) AS n FROM student_subject_items i JOIN student_subject e ON e.ID = i.enrollment_ID WHERE i.subject_ID = ? AND e.semester = ? AND e.school_year = ? AND e.status IN ('pending', 'approved') AND e.ID <> ?");
			$statement->bind_param('issi', $subjectId, $semester, $schoolYear, $excludeEnrollmentId);
			$statement->execute();
			$row = $statement->get_result()->fetch_assoc();
			return $row ? (int)$row['n'] : 0;
		} catch (mysqli_sql_exception $exception) {
			/* Clean v2.1 requires database/migrate.sql (or database/schema.sql
			   on a fresh install). A missing status column is a setup error
			   now, not a case to guess at. */
			throw $exception;
		}
	}

	function app_capacity_remaining($conn, $subjectId, $semester, $schoolYear, $excludeEnrollmentId = 0)
	{
		return app_subject_capacity($conn, $subjectId) - app_subject_enrolled_count($conn, $subjectId, $semester, $schoolYear, $excludeEnrollmentId);
	}

	function app_verified_paid($conn, $enrollmentId)
	{
		try {
			$statement = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM enrollment_payments WHERE enrollment_ID = ? AND status = 'verified'");
			$statement->bind_param('i', $enrollmentId);
			$statement->execute();
			$row = $statement->get_result()->fetch_assoc();
			return $row ? (float)$row['total'] : 0.0;
		} catch (mysqli_sql_exception $exception) {
			return 0.0;
		}
	}

	/* ---- Clean v2.1 helpers: terms, notifications, audit ---- */

	/* Academic calendar. PH school year starts in August: 1st Semester
	   Aug-Dec, 2nd Semester Jan-May, Summer Jun-Jul. January 2027 2nd
	   Semester belongs to school year 2026-2027 — this is the v3 term fix. */
	function app_term_for_date($dateString = null)
	{
		$date = $dateString ? new DateTime($dateString) : new DateTime();
		$year = (int)$date->format('Y');
		$month = (int)$date->format('n');
		if ($month >= 1 && $month <= 5) {
			return array('school_year' => ($year - 1) . '-' . $year, 'semester' => '2nd Semester');
		}
		if ($month >= 6 && $month <= 7) {
			return array('school_year' => ($year - 1) . '-' . $year, 'semester' => 'Summer');
		}
		return array('school_year' => $year . '-' . ($year + 1), 'semester' => '1st Semester');
	}

	function app_school_year_for_date($dateString = null)
	{
		$term = app_term_for_date($dateString);
		return $term['school_year'];
	}

	function app_current_term($conn)
	{
		if (!$conn) {
			return null;
		}
		try {
			$result = $conn->query('SELECT ID, school_year, semester, start_date, end_date, enrollment_open FROM terms WHERE is_current = 1 LIMIT 1');
			$row = $result->fetch_assoc();
			return $row ?: null;
		} catch (mysqli_sql_exception $exception) {
			return null;
		}
	}









	/* In-app notification. Always writes the notice; email is a separate,
	   opt-in mail() hook (see app_mail_notify) so XAMPP without SMTP still
	   works. */
	function app_notify($conn, $accountId, $type, $title, $body, $link = '')
	{
		if (!$conn || (int)$accountId < 1) {
			return false;
		}
		try {
			$statement = $conn->prepare('INSERT INTO notifications (accountID, type, title, body, link) VALUES (?, ?, ?, ?, ?)');
			$statement->bind_param('issss', $accountId, $type, $title, $body, $link);
			$statement->execute();
			return true;
		} catch (mysqli_sql_exception $exception) {
			return false;
		}
	}

	function app_mail_notify($conn, $toEmail, $subject, $body)
	{
		if (app_setting_get($conn, 'smtp_enabled', '0') !== '1') {
			return false;
		}
		$from = app_setting_get($conn, 'mail_from', '');
		if ($from === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
			return false;
		}
		$headers = 'From: ' . $from . "\r\n" . 'Content-Type: text/plain; charset=UTF-8';
		return mail($toEmail, $subject, $body, $headers);
	}

	function app_unread_notifications($conn, $accountId)
	{
		try {
			$statement = $conn->prepare('SELECT COUNT(*) AS n FROM notifications WHERE accountID = ? AND is_read = 0');
			$statement->bind_param('i', $accountId);
			$statement->execute();
			$row = $statement->get_result()->fetch_assoc();
			return $row ? (int)$row['n'] : 0;
		} catch (mysqli_sql_exception $exception) {
			return 0;
		}
	}

	function app_audit($conn, $action, $targetType = '', $targetId = '', $details = '')
	{
		if (!$conn) {
			return;
		}
		$actorId = isset($_SESSION['userID']) ? (int)$_SESSION['userID'] : null;
		$actorRole = isset($_SESSION['type']) ? (string)$_SESSION['type'] : '';
		try {
			$statement = $conn->prepare('INSERT INTO audit_log (actor_userID, actor_role, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?, ?)');
			$statement->bind_param('isssss', $actorId, $actorRole, $action, $targetType, $targetId, $details);
			$statement->execute();
		} catch (mysqli_sql_exception $exception) {
			/* Audit must never break the action it records. */
		}
	}




	function app_upload_file($file, $allowedExtensions, $allowedMimes, $maxBytes, $destDir, $prefix)
	{
		if (!isset($file) || !is_array($file) || !isset($file['error'])) {
			return array('ok' => false, 'path' => '', 'error' => 'No file was uploaded.');
		}
		if ($file['error'] === UPLOAD_ERR_NO_FILE) {
			return array('ok' => false, 'path' => '', 'error' => 'No file was uploaded.');
		}
		if ($file['error'] !== UPLOAD_ERR_OK) {
			return array('ok' => false, 'path' => '', 'error' => 'The file could not be uploaded. Please try again.');
		}
		if ($file['size'] > $maxBytes || $file['size'] <= 0) {
			return array('ok' => false, 'path' => '', 'error' => 'The file must be larger than 0 bytes and no bigger than 5 MB.');
		}
		$extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
		if (!in_array($extension, $allowedExtensions, true)) {
			return array('ok' => false, 'path' => '', 'error' => 'Only JPG, PNG, and PDF files are allowed.');
		}
		if (function_exists('finfo_open')) {
			$info = finfo_open(FILEINFO_MIME_TYPE);
			$mime = $info ? finfo_file($info, $file['tmp_name']) : '';
			if ($info) {
				finfo_close($info);
			}
			if ($mime !== '' && !in_array($mime, $allowedMimes, true)) {
				return array('ok' => false, 'path' => '', 'error' => 'That file type is not allowed.');
			}
		}
		if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
			return array('ok' => false, 'path' => '', 'error' => 'The upload folder could not be created. Please contact the administrator.');
		}
		$filename = $prefix . '_' . bin2hex(random_bytes(16)) . '.' . $extension;
		$target = rtrim($destDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
		if (!move_uploaded_file($file['tmp_name'], $target)) {
			return array('ok' => false, 'path' => '', 'error' => 'The file could not be saved. Please try again.');
		}
		return array('ok' => true, 'path' => $filename, 'error' => '');
	}

}

if (!isset($GLOBALS['appExceptionHandlerRegistered'])) {
	$GLOBALS['appExceptionHandlerRegistered'] = true;
	set_exception_handler(function ($exception) {
		error_log('Online Enrollment request failed: ' . $exception->getMessage());
		if (!headers_sent()) {
			http_response_code(500);
		}
		exit('The request could not be completed. Please try again later.');
	});
}

$dbHost = getenv('ONLINE_ENROLL_DB_HOST') ?: 'localhost';
$dbPort = (int)(getenv('ONLINE_ENROLL_DB_PORT') ?: 3306);
$dbName = getenv('ONLINE_ENROLL_DB_NAME') ?: 'online_enroll';
$dbUser = getenv('ONLINE_ENROLL_DB_USER') ?: 'root';
$dbPass = getenv('ONLINE_ENROLL_DB_PASS');
if ($dbPass === false) {
	$dbPass = '';
}

if (defined('APP_NO_DB')) {
	/* Test mode (tests/run_tests.php): helpers only, no database. */
	$conn = null;
} else {
	try {
		$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
		$conn->set_charset('utf8mb4');
	} catch (mysqli_sql_exception $exception) {
		error_log('Online Enrollment database connection failed: ' . $exception->getMessage());
		http_response_code(503);
		exit('The enrollment service is temporarily unavailable. Please try again later.');
	}
}
$GLOBALS['app_conn'] = $conn;
?>