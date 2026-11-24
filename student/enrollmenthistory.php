<?php
require_once __DIR__ . '/includes/conn.php';
require_once __DIR__ . '/logcheck.php';

$accountId = (int)$_SESSION['userID'];
$statement = $conn->prepare('SELECT s.studnum, s.fname, s.minitial, s.lname, s.course FROM studenttbl s WHERE s.accountID = ?');
$statement->bind_param('i', $accountId);
$statement->execute();
$student = $statement->get_result()->fetch_assoc();
if (!$student) {
	http_response_code(404);
	exit('Student profile was not found. Please contact the administrator.');
}
$statement = $conn->prepare('SELECT subjects, totalunits, approved, semester, school_year, created_at FROM student_subject WHERE studentID = ? ORDER BY created_at DESC, ID DESC');
$statement->bind_param('s', $student['studnum']);
$statement->execute();
$result = $statement->get_result();
$enrollments = array();
$approvedCount = 0;
while ($enrollment = $result->fetch_assoc()) {
	$enrollment['is_approved'] = in_array(strtolower(trim((string)$enrollment['approved'])), array('1', 'yes', 'approved', 'true'), true);
	$approvedCount += $enrollment['is_approved'] ? 1 : 0;
	$enrollments[] = $enrollment;
}
$pendingCount = count($enrollments) - $approvedCount;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Enrollment History | John Paul College</title>
<link href="style.css" rel="stylesheet" type="text/css">
</head>
<body>
<div class="header"></div>
<nav class="container" aria-label="Student navigation"><?php include __DIR__ . '/includes/menu.php'; ?></nav>
<main id="main">
	<div class="shell wide-shell">
		<header class="school-page-header">
			<img src="../logo.jpg" width="82" height="82" alt="John Paul College">
			<div><p class="eyebrow">Student Records · <?php echo app_h(strtoupper($student['course'])); ?></p><h1>My Enrollment History</h1><p>Hi <?php echo app_h(trim($student['fname'] . ' ' . $student['minitial'] . ' ' . $student['lname'])); ?>. Review your submitted forms and their approval status.</p></div>
		</header>
		<section class="enrollment-stats" aria-label="My enrollment summary">
			<div class="stat-card"><span>Total submissions</span><strong><?php echo count($enrollments); ?></strong></div>
			<div class="stat-card"><span>Pending review</span><strong><?php echo $pendingCount; ?></strong></div>
			<div class="stat-card"><span>Approved</span><strong><?php echo $approvedCount; ?></strong></div>
		</section>
		<div class="page-heading"><h2>Submitted enrollment forms</h2><button type="button" class="button" onclick="window.print()">Print history</button></div>
		<div class="table-wrap">
			<table>
				<thead><tr><th scope="col">Submitted</th><th scope="col">Term</th><th scope="col">Subjects</th><th scope="col">Total units</th><th scope="col">Status</th></tr></thead>
				<tbody>
				<?php if (!$enrollments) { ?><tr><td colspan="5" class="empty-state"><strong>No enrollment forms yet.</strong><br>Once you submit a form, its details and status will appear here.<br><a class="button" href="enroll.php">Start enrollment</a></td></tr><?php } ?>
				<?php foreach ($enrollments as $enrollment) {
					$term = $enrollment['semester'] === 'Legacy' || $enrollment['school_year'] === 'Legacy' ? 'Legacy record' : trim($enrollment['semester'] . ' ' . $enrollment['school_year']);
					$submitted = $enrollment['created_at'] ? date('M j, Y', strtotime($enrollment['created_at'])) : 'Legacy record';
				?>
					<tr>
						<td><?php echo app_h($submitted); ?></td>
						<td><?php echo app_h($term); ?></td>
						<td class="subject-list"><?php echo app_h(trim($enrollment['subjects'], ", \t\n\r\0\x0B")); ?></td>
						<td><?php echo (int)$enrollment['totalunits']; ?></td>
						<td><span class="status-badge <?php echo $enrollment['is_approved'] ? 'status-approved' : 'status-pending'; ?>"><?php echo $enrollment['is_approved'] ? 'Approved' : 'Pending'; ?></span></td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
	</div>
</main>
<footer id="footer"><div class="shell"><?php include __DIR__ . '/includes/menu2.php'; ?></div></footer>
</body>
</html>
