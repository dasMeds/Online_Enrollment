<?php
require_once __DIR__ . '/includes/conn.php';
require_once __DIR__ . '/logcheck.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: enroll.php');
	exit;
}
app_verify_csrf();

$semester = isset($_POST['sem']) && in_array($_POST['sem'], array('1st Semester', '2nd Semester', 'Summer'), true) ? $_POST['sem'] : '';
$rawIds = isset($_POST['reg']) ? (isset($_POST['subject_ids']) ? $_POST['subject_ids'] : array()) : (isset($_POST['subject']) ? $_POST['subject'] : array());
$error = '';
$subjects = array();
$studentStatement = $conn->prepare('SELECT studnum, fname, minitial, lname, course, yearlvl FROM studenttbl WHERE accountID = ?');
$studentStatement->bind_param('i', $_SESSION['userID']);
$studentStatement->execute();
$student = $studentStatement->get_result()->fetch_assoc();

if (!$student) {
	http_response_code(404);
	exit('Student profile was not found. Please contact the administrator.');
}

$ids = array();
if (is_array($rawIds)) {
	foreach ($rawIds as $rawId) {
		if (is_scalar($rawId) && ctype_digit((string)$rawId) && (int)$rawId > 0) {
			$ids[] = (int)$rawId;
		}
	}
}
$ids = array_values(array_unique($ids));
if (!$ids) {
	$error = 'Select at least one subject to continue.';
} elseif (!$semester) {
	$error = 'Choose a valid semester.';
} else {
	$placeholders = implode(',', array_fill(0, count($ids), '?'));
	$query = 'SELECT ID, subjectcode, subjecttitle, numofunits, room, day, time, instructor, islab, istutorial FROM subjecttbl WHERE course_con = ? AND semester = ? AND ID IN (' . $placeholders . ') ORDER BY subjectcode';
	$statement = $conn->prepare($query);
	$types = 'ss' . str_repeat('i', count($ids));
	$params = array_merge(array($student['course'], $semester), $ids);
	app_bind_params($statement, $types, $params);
	$statement->execute();
	$result = $statement->get_result();
	while ($subject = $result->fetch_assoc()) {
		$subjects[] = $subject;
	}
	if (count($subjects) !== count($ids)) {
		$error = 'One or more selected subjects are no longer available for your course.';
	}
}

$totalUnits = 0;
$extraFees = 0;
$codes = array();
foreach ($subjects as $subject) {
	$totalUnits += (int)$subject['numofunits'];
	$extraFees += ((int)$subject['islab'] + (int)$subject['istutorial']) * 1500;
	$codes[] = $subject['subjectcode'];
}

if (isset($_POST['reg']) && $error === '') {
	$subjectList = implode(',', $codes) . ',';
	$schoolYear = date('Y') . '-' . (date('Y') + 1);
	try {
		$conn->begin_transaction();
		$insert = $conn->prepare('INSERT INTO student_subject (studentID, subjects, totalunits, approved, semester, school_year, created_at) VALUES (?, ?, ?, 0, ?, ?, CURRENT_TIMESTAMP)');
		$insert->bind_param('ssiss', $student['studnum'], $subjectList, $totalUnits, $semester, $schoolYear);
		$insert->execute();
		$enrollmentId = $conn->insert_id;
		$itemInsert = $conn->prepare('INSERT INTO student_subject_items (enrollment_ID, subject_ID) VALUES (?, ?)');
		foreach ($subjects as $subject) {
			$subjectId = (int)$subject['ID'];
			$itemInsert->bind_param('ii', $enrollmentId, $subjectId);
			$itemInsert->execute();
		}
		$conn->commit();
		unset($_SESSION['csrf_token']);
		$_SESSION['flash_success'] = 'Your enrollment form was submitted successfully.';
		header('Location: studentindex.php');
		exit;
	} catch (mysqli_sql_exception $exception) {
		$conn->rollback();
		error_log('Enrollment submission failed: ' . $exception->getMessage());
		$error = $exception->getCode() === 1062
			? 'An enrollment form has already been submitted for this semester and school year.'
			: 'Your enrollment could not be submitted. Please try again.';
	}
}
$schoolYear = date('Y') . '-' . (date('Y') + 1);

$tuition = $totalUnits * 296;
$total = $tuition + 2050 + 150 + 50 + $extraFees;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Enrollment Preview | John Paul College</title>
<link href="style.css" rel="stylesheet" type="text/css">
</head>
<body class="enrollment-preview">
<main class="shell">
	<header class="enrollment-header">
		<img src="../logo.jpg" width="130" height="100" alt="John Paul College">
		<p>John Paul College<br>MG ANDAYA COMPOUND<br>Odiong, Roxas, Oriental Mindoro, Philippines</p>
		<h1>Unofficial Enrollment Form</h1>
		<p><?php echo app_h($semester); ?> &mdash; School Year <?php echo app_h($schoolYear); ?></p>
		<p><strong>Student:</strong> <?php echo app_h($student['fname'] . ' ' . $student['minitial'] . ' ' . $student['lname']); ?>
			&nbsp; <strong>Course:</strong> <?php echo app_h(strtoupper($student['course'])); ?>
			&nbsp; <strong>Year:</strong> <?php echo app_h($student['yearlvl']); ?></p>
	</header>
	<?php if ($error !== '') { ?><p class="form-error" role="alert"><?php echo app_h($error); ?></p><p><a href="enroll.php">Return to subject selection</a></p><?php } ?>
	<?php if ($subjects && $error === '') { ?>
	<div class="table-wrap">
	<table>
		<thead><tr><th>Subject</th><th>Units</th><th>Per Unit</th><th>Tutorial</th><th>Lab</th><th>Room</th><th>Day</th><th>Time</th><th>Instructor</th><th>Total</th></tr></thead>
		<tbody>
		<?php foreach ($subjects as $subject) { ?>
			<tr>
				<td><?php echo app_h($subject['subjectcode']); ?></td>
				<td><?php echo (int)$subject['numofunits']; ?></td>
				<td>296.00</td>
				<td><?php echo $subject['istutorial'] ? '1,500.00' : '-'; ?></td>
				<td><?php echo $subject['islab'] ? '1,500.00' : '-'; ?></td>
				<td><?php echo app_h($subject['room']); ?></td>
				<td><?php echo app_h($subject['day']); ?></td>
				<td><?php echo app_h($subject['time']); ?></td>
				<td><?php echo app_h($subject['instructor']); ?></td>
				<td><?php echo number_format((int)$subject['numofunits'] * 296, 2); ?></td>
			</tr>
		<?php } ?>
		</tbody>
		<tfoot>
			<tr><th colspan="9">Entrance fee</th><td>2,050.00</td></tr>
			<tr><th colspan="9">Internet</th><td>150.00</td></tr>
			<tr><th colspan="9">Miscellaneous</th><td>50.00</td></tr>
			<tr><th colspan="9">Total units: <?php echo $totalUnits; ?></th><th><?php echo number_format($total, 2); ?></th></tr>
		</tfoot>
	</table>
	</div>
	<form method="post">
		<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
		<input type="hidden" name="sem" value="<?php echo app_h($semester); ?>">
		<?php foreach ($ids as $id) { ?><input type="hidden" name="subject_ids[]" value="<?php echo (int)$id; ?>"><?php } ?>
		<button type="submit" name="reg" value="1" onclick="return confirm('Submit this enrollment form?');">Submit enrollment form</button>
		<button type="button" onclick="window.print()">Print</button>
	</form>
	<?php } ?>
</main>
</body>
</html>
