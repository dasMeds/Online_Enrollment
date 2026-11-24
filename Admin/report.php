<?php
require_once __DIR__ . '/includes/conn.php';
require_once __DIR__ . '/logcheck.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_approval'])) {
	app_verify_csrf();
	$enrollmentId = app_int_input('enrollment_id');
	$approved = app_input('approved') === '1' ? 1 : 0;
	if ($enrollmentId < 1) {
		$_SESSION['flash_error'] = 'Choose a valid enrollment.';
	} else {
		$lookup = $conn->prepare('SELECT ID FROM student_subject WHERE ID = ?');
		$lookup->bind_param('i', $enrollmentId);
		$lookup->execute();
		if (!$lookup->get_result()->fetch_assoc()) {
			$_SESSION['flash_error'] = 'The enrollment was not found.';
		} else {
			$update = $conn->prepare('UPDATE student_subject SET approved = ? WHERE ID = ?');
			$update->bind_param('ii', $approved, $enrollmentId);
			$update->execute();
			$_SESSION['flash_success'] = $approved ? 'Enrollment approved.' : 'Enrollment marked as pending.';
		}
	}
	header('Location: report.php');
	exit;
}
$notice = isset($_SESSION['flash_success']) ? $_SESSION['flash_success'] : '';
$error = isset($_SESSION['flash_error']) ? $_SESSION['flash_error'] : '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$report = $conn->query("SELECT e.ID, e.studentID, e.subjects, e.totalunits, e.semester, e.school_year, e.created_at, CASE WHEN LOWER(TRIM(e.approved)) IN ('1', 'yes', 'approved', 'true') THEN 1 ELSE 0 END AS is_approved, s.fname, s.minitial, s.lname, s.course FROM student_subject e LEFT JOIN studenttbl s ON s.studnum = e.studentID ORDER BY e.created_at DESC, e.ID DESC LIMIT 500");
$enrollments = array();
$approvedCount = 0;
while ($row = $report->fetch_assoc()) {
	$row['is_approved'] = (int)$row['is_approved'];
	$approvedCount += $row['is_approved'];
	$enrollments[] = $row;
}
$pendingCount = count($enrollments) - $approvedCount;
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
			<img src="../logo.jpg" width="82" height="82" alt="John Paul College">
			<div><p class="eyebrow">Registrar's Office · Academic Year <?php echo date('Y'); ?>-<?php echo date('Y') + 1; ?></p><h1>Enrollment Report</h1><p>Review and update student enrollment submissions.</p></div>
		</header>
		<?php if ($notice !== '') { ?><p class="form-success" role="status"><?php echo app_h($notice); ?></p><?php } ?>
		<?php if ($error !== '') { ?><p class="form-error" role="alert"><?php echo app_h($error); ?></p><?php } ?>
		<section class="enrollment-stats" aria-label="Enrollment summary">
			<div class="stat-card"><span>Submissions shown</span><strong><?php echo count($enrollments); ?></strong></div>
			<div class="stat-card"><span>Pending review</span><strong><?php echo $pendingCount; ?></strong></div>
			<div class="stat-card"><span>Approved</span><strong><?php echo $approvedCount; ?></strong></div>
		</section>
		<div class="report-toolbar">
			<label for="enrollment-filter">Search by student, number, course, or term
				<input type="search" id="enrollment-filter" placeholder="Start typing to filter">
			</label>
			<div class="report-toolbar-actions"><span id="enrollment-count" aria-live="polite">Showing <?php echo count($enrollments); ?> submissions (latest 500)</span><button type="button" class="button" onclick="window.print()">Print report</button></div>
		</div>
		<div class="table-wrap">
			<table>
				<thead><tr><th scope="col">Submitted</th><th scope="col">Student</th><th scope="col">Student number</th><th scope="col">Course</th><th scope="col">Term</th><th scope="col">Subjects</th><th scope="col">Units</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
				<tbody>
				<?php if (!$enrollments) { ?><tr><td colspan="9" class="empty-state">No enrollment forms have been submitted.</td></tr><?php } ?>
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
						<td><span class="status-badge <?php echo $row['is_approved'] ? 'status-approved' : 'status-pending'; ?>"><?php echo $row['is_approved'] ? 'Approved' : 'Pending'; ?></span></td>
						<td><form method="post" class="inline-form">
							<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
							<input type="hidden" name="enrollment_id" value="<?php echo (int)$row['ID']; ?>">
							<input type="hidden" name="approved" value="<?php echo $row['is_approved'] ? '0' : '1'; ?>">
							<button type="submit" name="set_approval" value="1"><?php echo $row['is_approved'] ? 'Mark pending' : 'Approve'; ?></button>
						</form></td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
	</div>
</main>
<script>
var enrollmentFilter = document.getElementById('enrollment-filter');
var enrollmentRows = Array.prototype.slice.call(document.querySelectorAll('.enrollment-row'));
var enrollmentCount = document.getElementById('enrollment-count');
enrollmentFilter.addEventListener('input', function () {
	var query = enrollmentFilter.value.trim().toLowerCase();
	var visible = 0;
	enrollmentRows.forEach(function (row) {
		var matches = row.textContent.toLowerCase().indexOf(query) !== -1;
		row.hidden = !matches;
		visible += matches ? 1 : 0;
	});
	enrollmentCount.textContent = 'Showing ' + visible + ' of ' + enrollmentRows.length + ' submissions';
});
</script>
</body>
</html>
