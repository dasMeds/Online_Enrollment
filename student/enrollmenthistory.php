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
try {
	$statement = $conn->prepare('SELECT ID, subjects, totalunits, approved, status, review_note, total_amount, semester, school_year, created_at FROM student_subject WHERE studentID = ? ORDER BY created_at DESC, ID DESC');
	$statement->bind_param('s', $student['studnum']);
	$statement->execute();
	$result = $statement->get_result();
} catch (mysqli_sql_exception $exception) {
	$statement = $conn->prepare('SELECT ID, subjects, totalunits, approved, semester, school_year, created_at FROM student_subject WHERE studentID = ? ORDER BY created_at DESC, ID DESC');
	$statement->bind_param('s', $student['studnum']);
	$statement->execute();
	$result = $statement->get_result();
}
$enrollments = array();
$counts = array('pending' => 0, 'approved' => 0, 'rejected' => 0);
while ($enrollment = $result->fetch_assoc()) {
	if (isset($enrollment['status'])) {
		$enrollment['status'] = strtolower(trim((string)$enrollment['status']));
	} else {
		$enrollment['status'] = in_array(strtolower(trim((string)$enrollment['approved'])), array('1', 'yes', 'approved', 'true'), true) ? 'approved' : 'pending';
	}
	if (!isset($counts[$enrollment['status']])) {
		$enrollment['status'] = 'pending';
	}
	$counts[$enrollment['status']]++;
	$enrollment['total_amount'] = isset($enrollment['total_amount']) ? (float)$enrollment['total_amount'] : 0.0;
	$enrollment['paid'] = app_verified_paid($conn, (int)$enrollment['ID']);
	$enrollment['balance'] = max(0.0, $enrollment['total_amount'] - $enrollment['paid']);
	$enrollments[] = $enrollment;
}
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
			<img src="../assets/logo.jpg" width="82" height="82" alt="John Paul College">
			<div><p class="eyebrow">Student Records · <?php echo app_h(strtoupper($student['course'])); ?></p><h1>My Enrollment History</h1><p>Hi <?php echo app_h(trim($student['fname'] . ' ' . $student['minitial'] . ' ' . $student['lname'])); ?>. Review your submitted forms and their approval status.</p></div>
		</header>
		<section class="enrollment-stats" aria-label="My enrollment summary">
			<div class="stat-card"><span>Total submissions</span><strong><?php echo count($enrollments); ?></strong></div>
			<div class="stat-card"><span>Pending review</span><strong><?php echo $counts['pending']; ?></strong></div>
			<div class="stat-card"><span>Approved</span><strong><?php echo $counts['approved']; ?></strong></div>
			<div class="stat-card"><span>Rejected</span><strong><?php echo $counts['rejected']; ?></strong></div>
		</section>
		<div class="page-heading"><h2>Submitted enrollment forms</h2><button type="button" class="button" onclick="window.print()">Print history</button></div>
		<div class="table-wrap">
			<table>
				<thead><tr><th scope="col">Submitted</th><th scope="col">Term</th><th scope="col">Subjects</th><th scope="col">Total units</th><th scope="col">Status</th><th scope="col">Registrar note</th><th scope="col">Amount</th><th scope="col">Balance</th><th scope="col">Actions</th></tr></thead>
				<tbody>
				<?php if (!$enrollments) { ?><tr><td colspan="9" class="empty-state"><img src="../assets/empty-state.svg" width="160" height="90" alt=""><br><strong>No enrollment forms yet.</strong><br>Once you submit a form, its details and status will appear here.<br><a class="button" href="enroll.php">Start enrollment</a></td></tr><?php } ?>
				<?php foreach ($enrollments as $enrollment) {
					$term = $enrollment['semester'] === 'Legacy' || $enrollment['school_year'] === 'Legacy' ? 'Legacy record' : trim($enrollment['semester'] . ' ' . $enrollment['school_year']);
					$submitted = $enrollment['created_at'] ? date('M j, Y', strtotime($enrollment['created_at'])) : 'Legacy record';
				?>
					<tr>
						<td><?php echo app_h($submitted); ?></td>
						<td><?php echo app_h($term); ?></td>
						<td class="subject-list"><?php echo app_h(trim($enrollment['subjects'], ", \t\n\r\0\x0B")); ?></td>
						<td><?php echo (int)$enrollment['totalunits']; ?></td>
						<td><span class="status-badge <?php echo app_h(app_status_class($enrollment['status'])); ?>"><?php echo app_h(app_status_label($enrollment['status'])); ?></span></td>
						<td class="review-note"><?php echo app_h(isset($enrollment['review_note']) && $enrollment['review_note'] !== '' ? $enrollment['review_note'] : '—'); ?></td>
						<td class="money"><?php echo number_format($enrollment['total_amount'], 2); ?></td>
						<td class="money"><?php echo number_format($enrollment['balance'], 2); ?></td>
						<td>
							<a class="button" href="assessment.php?id=<?php echo (int)$enrollment['ID']; ?>">Assessment</a>
							<?php if ($enrollment['status'] === 'approved') { ?><a class="button" href="cor.php?id=<?php echo (int)$enrollment['ID']; ?>">Print COR</a><?php } ?>
							<?php if ($enrollment['status'] === 'rejected') { ?><a class="button" href="enroll.php?semester=<?php echo app_h(urlencode($enrollment['semester'])); ?>">Re-enroll</a><?php } ?>
						</td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
		<?php if ($counts['rejected'] > 0) { ?><p>If a form was rejected, read the registrar note above, correct the problem, and submit a new form for the same term from the Enroll page. Your earlier payments carry over to the corrected form.</p><?php } ?>
	</div>
</main>
<footer id="footer"><div class="shell"><?php include __DIR__ . '/includes/menu2.php'; ?></div></footer>
</body>
</html>
