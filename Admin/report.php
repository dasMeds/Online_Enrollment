<?php
require_once __DIR__ . '/includes/conn.php';
require_once __DIR__ . '/logcheck.php';

/* Tell the student what happened to their enrollment. */
function report_notify($conn, $enrollmentId, $newStatus, $note)
{
	$lookup = $conn->prepare('SELECT t.accountID, t.fname, t.lname, e.semester, e.school_year FROM student_subject e JOIN studenttbl t ON t.studnum = e.studentID WHERE e.ID = ? LIMIT 1');
	$lookup->bind_param('i', $enrollmentId);
	$lookup->execute();
	$info = $lookup->get_result()->fetch_assoc();
	if (!$info) { return; }
	if ($newStatus === 'approved') {
		app_notify($conn, (int)$info['accountID'], 'enrollment', 'Enrollment approved', 'Your enrollment for ' . $info['semester'] . ' ' . $info['school_year'] . ' was approved. You can now pay and print your Certificate of Registration.', 'enrollmenthistory.php');
	} elseif ($newStatus === 'rejected') {
		app_notify($conn, (int)$info['accountID'], 'enrollment', 'Enrollment rejected', 'Your enrollment for ' . $info['semester'] . ' ' . $info['school_year'] . ' was rejected.' . ($note !== '' ? ' Reason: ' . $note : '') . ' You can submit a corrected form for the same term.', 'enroll.php');
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_status'])) {
	app_verify_csrf();
	$enrollmentId = app_int_input('enrollment_id');
	$newStatus = app_input('new_status');
	$reviewNote = trim(app_input('review_note'));
	if ($enrollmentId < 1) {
		$_SESSION['flash_error'] = 'Choose a valid enrollment.';
	} elseif (!in_array($newStatus, array('approved', 'rejected', 'pending'), true)) {
		$_SESSION['flash_error'] = 'Choose a valid status.';
	} elseif ($newStatus === 'rejected' && $reviewNote === '') {
		$_SESSION['flash_error'] = 'A reason is required when rejecting an enrollment.';
	} else {
		$lookup = $conn->prepare('SELECT ID, studentID, semester, school_year FROM student_subject WHERE ID = ?');
		$lookup->bind_param('i', $enrollmentId);
		$lookup->execute();
		$enrollment = $lookup->get_result()->fetch_assoc();
		if (!$enrollment) {
			$_SESSION['flash_error'] = 'The enrollment was not found.';
		} else {
			$approved = $newStatus === 'approved' ? 1 : 0;
			$reviewedBy = (int)$_SESSION['userID'];
			$note = $reviewNote === '' ? null : $reviewNote;
			try {
				$update = $conn->prepare("UPDATE student_subject SET approved = ?, status = ?, review_note = ?, reviewed_at = CURRENT_TIMESTAMP, reviewed_by = ? WHERE ID = ?");
				$update->bind_param('issii', $approved, $newStatus, $note, $reviewedBy, $enrollmentId);
				$update->execute();
			} catch (mysqli_sql_exception $exception) {
				/* Pre-v1 schema: no status/review columns yet. */
				$update = $conn->prepare('UPDATE student_subject SET approved = ? WHERE ID = ?');
				$update->bind_param('ii', $approved, $enrollmentId);
				$update->execute();
			}
			app_audit($conn, 'enrollment_' . $newStatus, 'enrollment', (string)$enrollmentId, 'Enrollment for ' . $enrollment['studentID'] . ' (' . $enrollment['semester'] . ' ' . $enrollment['school_year'] . ') set to ' . $newStatus . ($note !== null ? '. Note: ' . $note : '') . '.');
			report_notify($conn, $enrollmentId, $newStatus, $reviewNote);
			$_SESSION['flash_success'] = $newStatus === 'approved' ? 'Enrollment approved.' : ($newStatus === 'rejected' ? 'Enrollment rejected. The student can now submit a corrected form for the same term.' : 'Enrollment marked as pending.');
		}
	}
	$redirect = 'report.php';
	if (isset($_GET['status']) || isset($_GET['course']) || isset($_GET['semester']) || isset($_GET['q'])) {
		$redirect .= '?' . http_build_query(array_filter(array('status' => isset($_GET['status']) ? $_GET['status'] : '', 'course' => isset($_GET['course']) ? $_GET['course'] : '', 'semester' => isset($_GET['semester']) ? $_GET['semester'] : '', 'q' => isset($_GET['q']) ? $_GET['q'] : '')));
	}
	header('Location: ' . $redirect);
	exit;
}
$notice = isset($_SESSION['flash_success']) ? $_SESSION['flash_success'] : '';
$error = isset($_SESSION['flash_error']) ? $_SESSION['flash_error'] : '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

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
	/* Pre-v1 schema fallback: approved flag only, no status column. */
	$report = $conn->query("SELECT e.ID, e.studentID, e.subjects, e.totalunits, e.semester, e.school_year, e.created_at, CASE WHEN LOWER(TRIM(e.approved)) IN ('1', 'yes', 'approved', 'true') THEN 'approved' ELSE 'pending' END AS status, '' AS review_note, 0 AS total_amount, s.fname, s.minitial, s.lname, s.course FROM student_subject e LEFT JOIN studenttbl s ON s.studnum = e.studentID ORDER BY e.created_at DESC, e.ID DESC LIMIT 500");
}
$enrollments = array();
$counts = array('pending' => 0, 'approved' => 0, 'rejected' => 0);
$totalBilled = 0.0;
while ($row = $report->fetch_assoc()) {
	$row['status'] = strtolower(trim((string)$row['status']));
	if (!isset($counts[$row['status']])) {
		$row['status'] = 'pending';
	}
	$counts[$row['status']]++;
	$row['total_amount'] = (float)$row['total_amount'];
	$row['paid'] = app_verified_paid($conn, (int)$row['ID']);
	$row['balance'] = max(0.0, $row['total_amount'] - $row['paid']);
	$totalBilled += $row['total_amount'];
	$enrollments[] = $row;
}
$exportQuery = http_build_query(array_filter(array('status' => $filterStatus, 'course' => $filterCourse, 'semester' => $filterSemester, 'q' => $filterQ)));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Enrollment Report | John Paul College</title>
<link href="style.css" rel="stylesheet" type="text/css">
</head>
<body>
<div class="header"></div>
<nav class="container" aria-label="Administrator navigation"><?php include __DIR__ . '/includes/menu1.php'; ?></nav>
<main id="main">
	<div class="shell wide-shell">
		<header class="school-page-header">
			<img src="../assets/logo.jpg" width="82" height="82" alt="John Paul College">
			<div><p class="eyebrow">Registrar's Office · Academic Year <?php echo date('Y'); ?>-<?php echo date('Y') + 1; ?></p><h1>Enrollment Report</h1><p>Review and update student enrollment submissions.</p></div>
		</header>
		<?php if ($notice !== '') { ?><p class="form-success" role="status"><?php echo app_h($notice); ?></p><?php } ?>
		<?php if ($error !== '') { ?><p class="form-error" role="alert"><?php echo app_h($error); ?></p><?php } ?>
		<section class="enrollment-stats" aria-label="Enrollment summary">
			<div class="stat-card"><span>Submissions shown</span><strong><?php echo count($enrollments); ?></strong></div>
			<div class="stat-card"><span>Pending review</span><strong><?php echo $counts['pending']; ?></strong></div>
			<div class="stat-card"><span>Approved</span><strong><?php echo $counts['approved']; ?></strong></div>
			<div class="stat-card"><span>Rejected</span><strong><?php echo $counts['rejected']; ?></strong></div>
			<div class="stat-card"><span>Total billed (shown)</span><strong>&#8369;<?php echo number_format($totalBilled, 2); ?></strong></div>
		</section>
		<form class="catalog-filters" method="get">
			<label for="filter-status">Status
				<select id="filter-status" name="status"><option value="">All statuses</option><?php foreach (array('pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected') as $value => $label) { ?><option value="<?php echo app_h($value); ?>" <?php echo $filterStatus === $value ? 'selected' : ''; ?>><?php echo app_h($label); ?></option><?php } ?></select>
			</label>
			<label for="filter-course">Course
				<select id="filter-course" name="course"><option value="">All courses</option><?php foreach (app_course_options() as $code => $title) { ?><option value="<?php echo app_h($code); ?>" <?php echo $filterCourse === $code ? 'selected' : ''; ?>><?php echo app_h(strtoupper($code)); ?></option><?php } ?></select>
			</label>
			<label for="filter-semester">Semester
				<select id="filter-semester" name="semester"><option value="">All semesters</option><?php foreach (array('1st Semester', '2nd Semester', 'Summer') as $option) { ?><option value="<?php echo app_h($option); ?>" <?php echo $filterSemester === $option ? 'selected' : ''; ?>><?php echo app_h($option); ?></option><?php } ?></select>
			</label>
			<label for="filter-q">Search student, number, or course
				<input type="search" id="filter-q" name="q" value="<?php echo app_h($filterQ); ?>" placeholder="Start typing to filter">
			</label>
			<button type="submit">Apply filters</button>
			<a class="button" href="report.php">Clear</a>
			<a class="button" href="report_export.php<?php echo $exportQuery !== '' ? '?' . app_h($exportQuery) : ''; ?>">Export CSV</a>
			<button type="button" class="button" onclick="window.print()">Print report</button>
		</form>
		<div class="table-wrap">
			<table>
				<thead><tr><th scope="col">Submitted</th><th scope="col">Student</th><th scope="col">Student number</th><th scope="col">Course</th><th scope="col">Term</th><th scope="col">Subjects</th><th scope="col">Units</th><th scope="col">Status</th><th scope="col">Registrar note</th><th scope="col">Amount</th><th scope="col">Paid</th><th scope="col">Balance</th><th scope="col">Action</th></tr></thead>
				<tbody>
				<?php if (!$enrollments) { ?><tr><td colspan="13" class="empty-state"><img src="../assets/empty-state.svg" width="160" height="90" alt=""><br>No enrollment forms match these filters.</td></tr><?php } ?>
				<?php foreach ($enrollments as $row) {
					$studentName = trim(($row['fname'] ?: '') . ' ' . ($row['minitial'] ?: '') . ' ' . ($row['lname'] ?: ''));
					$term = $row['semester'] === 'Legacy' || $row['school_year'] === 'Legacy' ? 'Legacy record' : trim($row['semester'] . ' ' . $row['school_year']);
					$submitted = $row['created_at'] ? date('M j, Y', strtotime($row['created_at'])) : 'Legacy record';
				?>
					<tr class="enrollment-row">
						<td><?php echo app_h($submitted); ?></td>
						<td><?php echo app_h($studentName !== '' ? $studentName : 'Student profile unavailable'); ?></td>
						<td><?php echo app_h($row['studentID'] !== '' ? $row['studentID'] : '—'); ?></td>
						<td><?php echo app_h(isset($row['course']) ? strtoupper($row['course']) : ''); ?></td>
						<td><?php echo app_h($term); ?></td>
						<td class="subject-list"><?php echo app_h(trim($row['subjects'], ", \t\n\r\0\x0B")); ?></td>
						<td><?php echo (int)$row['totalunits']; ?></td>
						<td><span class="status-badge <?php echo app_h(app_status_class($row['status'])); ?>"><?php echo app_h(app_status_label($row['status'])); ?></span></td>
						<td class="review-note"><?php echo app_h($row['review_note'] !== '' && $row['review_note'] !== null ? $row['review_note'] : '—'); ?></td>
						<td class="money"><?php echo number_format($row['total_amount'], 2); ?></td>
						<td class="money"><?php echo number_format($row['paid'], 2); ?></td>
						<td class="money"><?php echo number_format($row['balance'], 2); ?></td>
						<td>
							<form method="post" class="inline-form">
								<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
								<input type="hidden" name="enrollment_id" value="<?php echo (int)$row['ID']; ?>">
								<input type="hidden" name="new_status" value="approved">
								<button type="submit" name="set_status" value="1" onclick="return confirm('Approve this enrollment?');">Approve</button>
							</form>
							<form method="post" class="inline-form" onsubmit="return confirm('Reject this enrollment? The student will see your reason and can submit a corrected form.');">
								<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
								<input type="hidden" name="enrollment_id" value="<?php echo (int)$row['ID']; ?>">
								<input type="hidden" name="new_status" value="rejected">
								<input type="text" name="review_note" maxlength="500" placeholder="Reason for rejection (required)" aria-label="Reason for rejection" required>
								<button type="submit" name="set_status" value="1">Reject</button>
							</form>
							<?php if ($row['status'] !== 'pending') { ?>
							<form method="post" class="inline-form">
								<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
								<input type="hidden" name="enrollment_id" value="<?php echo (int)$row['ID']; ?>">
								<input type="hidden" name="new_status" value="pending">
								<button type="submit" name="set_status" value="1">Mark pending</button>
							</form>
							<?php } ?>
						</td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
	</div>
</main>
</body>
</html>
