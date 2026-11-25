<?php
require_once __DIR__ . '/includes/conn.php';
require_once __DIR__ . '/logcheck.php';

$accountId = (int)$_SESSION['userID'];
$statement = $conn->prepare('SELECT s.studnum, s.fname, s.minitial, s.lname, s.course, s.yearlvl FROM studenttbl s WHERE s.accountID = ?');
$statement->bind_param('i', $accountId);
$statement->execute();
$student = $statement->get_result()->fetch_assoc();
if (!$student) {
	http_response_code(404);
	exit('Student profile was not found. Please contact the administrator.');
}

$enrollmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$enrollment = null;
$items = array();
$message = '';
if ($enrollmentId < 1) {
	$message = 'Choose an enrollment from your enrollment history first.';
} else {
	try {
		$statement = $conn->prepare('SELECT ID, subjects, totalunits, approved, status, tuition_amount, entrance_fee, internet_fee, misc_fee, lab_fee, tutorial_fee, total_amount, semester, school_year, created_at FROM student_subject WHERE ID = ? AND studentID = ? LIMIT 1');
		$statement->bind_param('is', $enrollmentId, $student['studnum']);
		$statement->execute();
		$enrollment = $statement->get_result()->fetch_assoc();
	} catch (mysqli_sql_exception $exception) {
		$statement = $conn->prepare('SELECT ID, subjects, totalunits, approved, semester, school_year, created_at FROM student_subject WHERE ID = ? AND studentID = ? LIMIT 1');
		$statement->bind_param('is', $enrollmentId, $student['studnum']);
		$statement->execute();
		$enrollment = $statement->get_result()->fetch_assoc();
	}
	if (!$enrollment) {
		$message = 'That enrollment was not found.';
	} else {
		$enrollment['status'] = isset($enrollment['status']) ? strtolower(trim((string)$enrollment['status'])) : (in_array(strtolower(trim((string)$enrollment['approved'])), array('1', 'yes', 'approved', 'true'), true) ? 'approved' : 'pending');
		if ($enrollment['status'] !== 'approved') {
			$message = 'This Certificate of Registration is available only after your enrollment is approved. Its current status is ' . app_status_label($enrollment['status']) . '.';
			$enrollment = null;
		}
	}
}
if ($enrollment) {
	foreach (array('tuition_amount', 'entrance_fee', 'internet_fee', 'misc_fee', 'lab_fee', 'tutorial_fee', 'total_amount') as $field) {
		$enrollment[$field] = isset($enrollment[$field]) ? (float)$enrollment[$field] : 0.0;
	}
	if ($enrollment['total_amount'] <= 0) {
		$fees = app_fee_settings($conn);
		$statement = $conn->prepare('SELECT COALESCE(SUM(s.islab), 0) AS labs, COALESCE(SUM(s.istutorial), 0) AS tutorials FROM student_subject_items i JOIN subjecttbl s ON s.ID = i.subject_ID WHERE i.enrollment_ID = ?');
		$statement->bind_param('i', $enrollmentId);
		$statement->execute();
		$counts = $statement->get_result()->fetch_assoc();
		$calc = app_assessment_calc($fees, (int)$enrollment['totalunits'], $counts ? (int)$counts['labs'] : 0, $counts ? (int)$counts['tutorials'] : 0);
		$enrollment['tuition_amount'] = $calc['tuition'];
		$enrollment['entrance_fee'] = $calc['entrance'];
		$enrollment['internet_fee'] = $calc['internet'];
		$enrollment['misc_fee'] = $calc['misc'];
		$enrollment['lab_fee'] = $calc['lab'];
		$enrollment['tutorial_fee'] = $calc['tutorial'];
		$enrollment['total_amount'] = $calc['total'];
	}
	$statement = $conn->prepare('SELECT s.ID, s.subjectcode, s.subjecttitle, s.numofunits, s.room, s.day, s.time, s.instructor FROM subjecttbl s JOIN student_subject_items i ON i.subject_ID = s.ID WHERE i.enrollment_ID = ? ORDER BY s.subjectcode');
	$statement->bind_param('i', $enrollmentId);
	$statement->execute();
	$result = $statement->get_result();
	while ($row = $result->fetch_assoc()) {
		$slots = app_subject_slots($conn, $row);
		$labels = array();
		$room = $row['room'];
		foreach ($slots as $slot) {
			$labels[] = $slot['label'];
			if ($slot['room'] !== '') {
				$room = $slot['room'];
			}
		}
		$row['schedule_text'] = $labels ? implode('; ', $labels) : trim($row['day'] . ' ' . $row['time']);
		$row['room_text'] = $room;
		$items[] = $row;
	}
	$verifiedPaid = app_verified_paid($conn, $enrollmentId);
	$balance = max(0.0, $enrollment['total_amount'] - $verifiedPaid);
	$studentName = trim($student['fname'] . ' ' . $student['minitial'] . ' ' . $student['lname']);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Certificate of Registration | John Paul College</title>
<link href="style.css" rel="stylesheet" type="text/css">
<link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
</head>
<body>
<main id="main">
	<div class="shell cor">
		<?php if (!$enrollment) { ?>
			<h1>Certificate of Registration</h1>
			<p class="form-error" role="alert"><?php echo app_h($message); ?></p>
			<p><a class="button no-print" href="enrollmenthistory.php">Back to enrollment history</a></p>
		<?php } else { ?>
		<header class="enrollment-header">
			<img src="../assets/logo.jpg" width="130" height="100" alt="John Paul College">
			<p>John Paul College<br>MG ANDAYA COMPOUND<br>Odiong, Roxas, Oriental Mindoro, Philippines</p>
			<h1>Certificate of Registration</h1>
			<p><?php echo app_h($enrollment['semester']); ?> &mdash; School Year <?php echo app_h($enrollment['school_year']); ?></p>
			<p><strong>Status:</strong> Approved &nbsp; <strong>Submitted:</strong> <?php echo app_h($enrollment['created_at'] ? date('F j, Y', strtotime($enrollment['created_at'])) : '—'); ?></p>
		</header>
		<p><strong>Student:</strong> <?php echo app_h($studentName); ?> &nbsp; <strong>Student number:</strong> <?php echo app_h($student['studnum']); ?><br>
		<strong>Course:</strong> <?php echo app_h(strtoupper($student['course'])); ?> &nbsp; <strong>Year level:</strong> <?php echo app_h($student['yearlvl']); ?></p>
		<div class="table-wrap">
			<table>
				<thead><tr><th>Subject code</th><th>Subject title</th><th>Units</th><th>Schedule</th><th>Room</th><th>Instructor</th></tr></thead>
				<tbody>
				<?php foreach ($items as $item) { ?>
					<tr>
						<td><?php echo app_h($item['subjectcode']); ?></td>
						<td><?php echo app_h($item['subjecttitle']); ?></td>
						<td><?php echo (int)$item['numofunits']; ?></td>
						<td><?php echo app_h($item['schedule_text'] !== '' ? $item['schedule_text'] : 'Not scheduled'); ?></td>
						<td><?php echo app_h($item['room_text'] !== '' ? $item['room_text'] : '—'); ?></td>
						<td><?php echo app_h($item['instructor'] !== '' ? $item['instructor'] : '—'); ?></td>
					</tr>
				<?php } ?>
				</tbody>
				<tfoot><tr><th colspan="2">Total units</th><th><?php echo (int)$enrollment['totalunits']; ?></th><th colspan="3"></th></tr></tfoot>
			</table>
		</div>
		<h2>Assessment</h2>
		<div class="table-wrap">
			<table>
				<tbody>
					<tr><td>Tuition (<?php echo (int)$enrollment['totalunits']; ?> units)</td><td class="money"><?php echo number_format($enrollment['tuition_amount'], 2); ?></td></tr>
					<tr><td>Entrance fee</td><td class="money"><?php echo number_format($enrollment['entrance_fee'], 2); ?></td></tr>
					<tr><td>Internet</td><td class="money"><?php echo number_format($enrollment['internet_fee'], 2); ?></td></tr>
					<tr><td>Miscellaneous</td><td class="money"><?php echo number_format($enrollment['misc_fee'], 2); ?></td></tr>
					<tr><td>Lab fees</td><td class="money"><?php echo number_format($enrollment['lab_fee'], 2); ?></td></tr>
					<tr><td>Tutorial fees</td><td class="money"><?php echo number_format($enrollment['tutorial_fee'], 2); ?></td></tr>
					<tr><th>Total amount</th><th class="money"><?php echo number_format($enrollment['total_amount'], 2); ?></th></tr>
					<tr><td>Verified paid</td><td class="money"><?php echo number_format($verifiedPaid, 2); ?></td></tr>
					<tr><th>Balance</th><th class="money"><?php echo number_format($balance, 2); ?></th></tr>
				</tbody>
			</table>
		</div>
		<div class="signature-row">
			<div>Registrar</div>
			<div>Cashier</div>
			<div>Student</div>
		</div>
		<p class="no-print"><button type="button" onclick="window.print()">Print Certificate of Registration</button> <a class="button" href="enrollmenthistory.php">Back to enrollment history</a> <a class="button" href="assessment.php?id=<?php echo (int)$enrollment['ID']; ?>">View assessment</a></p>
		<?php } ?>
	</div>
</main>
</body>
</html>
