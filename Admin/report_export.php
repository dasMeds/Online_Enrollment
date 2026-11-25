<?php
require_once __DIR__ . '/includes/conn.php';
require_once __DIR__ . '/logcheck.php';

/* CSV export of the enrollment report, using the same server-side filters
   as Admin/report.php (status, course, semester, q). */
$filterStatus = isset($_GET['status']) && in_array($_GET['status'], array('pending', 'approved', 'rejected'), true) ? $_GET['status'] : '';
$filterCourse = isset($_GET['course']) && array_key_exists(strtolower((string)$_GET['course']), app_course_options()) ? strtolower((string)$_GET['course']) : '';
$filterSemester = isset($_GET['semester']) && in_array($_GET['semester'], array('1st Semester', '2nd Semester', 'Summer'), true) ? $_GET['semester'] : '';
$filterQ = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

$where = array();
$types = '';
$params = array();
try {
	$baseSelect = "SELECT e.ID, e.studentID, e.subjects, e.totalunits, e.semester, e.school_year, e.created_at, e.status, e.review_note, e.total_amount, s.fname, s.minitial, s.lname, s.course FROM student_subject e LEFT JOIN studenttbl s ON s.studnum = e.studentID";
	if ($filterStatus !== '') {
		$where[] = 'e.status = ?';
		$types .= 's';
		$params[] = $filterStatus;
	}
	if ($filterCourse !== '') {
		$where[] = 's.course = ?';
		$types .= 's';
		$params[] = $filterCourse;
	}
	if ($filterSemester !== '') {
		$where[] = 'e.semester = ?';
		$types .= 's';
		$params[] = $filterSemester;
	}
	if ($filterQ !== '') {
		$where[] = "(CONCAT_WS(' ', s.fname, s.minitial, s.lname) LIKE ? OR e.studentID LIKE ? OR s.course LIKE ?)";
		$types .= 'sss';
		$like = '%' . $filterQ . '%';
		$params[] = $like;
		$params[] = $like;
		$params[] = $like;
	}
	$query = $baseSelect . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY e.created_at DESC, e.ID DESC LIMIT 500';
	$statement = $conn->prepare($query);
	if ($params) {
		app_bind_params($statement, $types, $params);
	}
	$statement->execute();
	$report = $statement->get_result();
} catch (mysqli_sql_exception $exception) {
	$report = $conn->query("SELECT e.ID, e.studentID, e.subjects, e.totalunits, e.semester, e.school_year, e.created_at, CASE WHEN LOWER(TRIM(e.approved)) IN ('1', 'yes', 'approved', 'true') THEN 'approved' ELSE 'pending' END AS status, '' AS review_note, 0 AS total_amount, s.fname, s.minitial, s.lname, s.course FROM student_subject e LEFT JOIN studenttbl s ON s.studnum = e.studentID ORDER BY e.created_at DESC, e.ID DESC LIMIT 500");
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="enrollment-report-' . date('Y-m-d') . '.csv"');
$output = fopen('php://output', 'w');
fputcsv($output, array('Enrollment ID', 'Submitted', 'Student', 'Student number', 'Course', 'Semester', 'School year', 'Subjects', 'Total units', 'Status', 'Registrar note', 'Total amount', 'Verified paid', 'Balance'));
while ($row = $report->fetch_assoc()) {
	$studentName = trim(($row['fname'] ?: '') . ' ' . ($row['minitial'] ?: '') . ' ' . ($row['lname'] ?: ''));
	$total = (float)$row['total_amount'];
	$paid = app_verified_paid($conn, (int)$row['ID']);
	fputcsv($output, array(
		(int)$row['ID'],
		$row['created_at'],
		$studentName,
		$row['studentID'],
		isset($row['course']) ? strtoupper($row['course']) : '',
		$row['semester'],
		$row['school_year'],
		trim($row['subjects'], ", \t\n\r\0\x0B"),
		(int)$row['totalunits'],
		app_status_label($row['status']),
		$row['review_note'],
		number_format($total, 2, '.', ''),
		number_format($paid, 2, '.', ''),
		number_format(max(0.0, $total - $paid), 2, '.', '')
	));
}
fclose($output);
exit;
