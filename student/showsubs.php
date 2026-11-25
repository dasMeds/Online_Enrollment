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

/* Term fix: the school year comes from the current term, never from
   date('Y') — a January 2nd-semester enrollment stays 2026-2027. */
$currentTerm = app_current_term($conn);
$schoolYear = $currentTerm ? $currentTerm['school_year'] : app_school_year_for_date(date('Y-m-d'));
$termId = $currentTerm ? (int)$currentTerm['ID'] : null;
$fees = app_fee_settings($conn);
$enrollmentState = app_enrollment_open($conn);
$conflicts = array();
$unparsable = array();
$slotMap = array();
$seatsMap = array();
$existingEnrollment = null;
$reuseId = 0;

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
} elseif (!$enrollmentState['open']) {
	$error = $enrollmentState['reason'];
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

/* Existing enrollment for this term: a pending/approved form blocks a
   second submission; a rejected form is reused (see below) so the student
   can re-enroll after a rejection instead of hitting the UNIQUE term key. */
if ($error === '' && $subjects) {
	try {
		$lookup = $conn->prepare("SELECT ID, status FROM student_subject WHERE studentID = ? AND semester = ? AND school_year = ? LIMIT 1");
		$lookup->bind_param('sss', $student['studnum'], $semester, $schoolYear);
		$lookup->execute();
		$existingEnrollment = $lookup->get_result()->fetch_assoc();
	} catch (mysqli_sql_exception $exception) {
		$lookup = $conn->prepare('SELECT ID, approved FROM student_subject WHERE studentID = ? AND semester = ? AND school_year = ? LIMIT 1');
		$lookup->bind_param('sss', $student['studnum'], $semester, $schoolYear);
		$lookup->execute();
		$row = $lookup->get_result()->fetch_assoc();
		$existingEnrollment = $row ? array('ID' => $row['ID'], 'status' => ((int)$row['approved'] === 1 ? 'approved' : 'pending')) : null;
	}
	if ($existingEnrollment) {
		if (strtolower((string)$existingEnrollment['status']) === 'rejected') {
			$reuseId = (int)$existingEnrollment['ID'];
		} else {
			$error = 'An enrollment form has already been submitted for this semester and school year.';
		}
	}
}

$totalUnits = 0;
$labCount = 0;
$tutorialCount = 0;
$codes = array();
foreach ($subjects as $subject) {
	$totalUnits += (int)$subject['numofunits'];
	$labCount += (int)$subject['islab'];
	$tutorialCount += (int)$subject['istutorial'];
	$codes[] = $subject['subjectcode'];
}
$assessment = app_assessment_calc($fees, $totalUnits, $labCount, $tutorialCount);
$tuition = $assessment['tuition'];
$total = $assessment['total'];

/* Server-side schedule safety: runs on preview AND again on submit, so a
   changed schedule or a full section cannot slip through the browser. */
if ($error === '' && $subjects) {
	if ($totalUnits > $fees['max_units']) {
		$error = 'You selected ' . $totalUnits . ' units, which is more than the maximum of ' . $fees['max_units'] . ' units per term.';
	}
	foreach ($subjects as $subject) {
		$subjectId = (int)$subject['ID'];
		$slots = app_subject_slots($conn, $subject);
		$slotMap[$subjectId] = $slots;
		if (!$slots) {
			$unparsable[] = $subject['subjectcode'];
		}
		$remaining = app_capacity_remaining($conn, $subjectId, $semester, $schoolYear, $reuseId);
		$seatsMap[$subjectId] = $remaining;
		if ($remaining <= 0) {
			$error = 'The subject ' . $subject['subjectcode'] . ' is already full. Please remove it and choose another subject.';
		}
	}
	/* Time conflicts within the selection. */
	if ($error === '') {
		$count = count($subjects);
		for ($i = 0; $i < $count; $i++) {
			for ($j = $i + 1; $j < $count; $j++) {
				$a = $subjects[$i];
				$b = $subjects[$j];
				foreach ($slotMap[(int)$a['ID']] as $slotA) {
					foreach ($slotMap[(int)$b['ID']] as $slotB) {
						if (app_slots_overlap($slotA, $slotB)) {
							$conflicts[] = $a['subjectcode'] . ' and ' . $b['subjectcode'] . ' both meet ' . $slotA['label'] . '.';
						}
					}
				}
			}
		}
		/* Time conflicts against the student's other pending/approved
		   enrollments in the same term. */
		try {
			$other = $conn->prepare("SELECT ID FROM student_subject WHERE studentID = ? AND semester = ? AND school_year = ? AND status IN ('pending', 'approved') AND ID <> ?");
			$other->bind_param('sssi', $student['studnum'], $semester, $schoolYear, $reuseId);
			$other->execute();
			$otherIds = array();
			$otherResult = $other->get_result();
			while ($row = $otherResult->fetch_assoc()) {
				$otherIds[] = (int)$row['ID'];
			}
		} catch (mysqli_sql_exception $exception) {
			$other = $conn->prepare('SELECT ID FROM student_subject WHERE studentID = ? AND semester = ? AND school_year = ? AND approved = 1 AND ID <> ?');
			$other->bind_param('sssi', $student['studnum'], $semester, $schoolYear, $reuseId);
			$other->execute();
			$otherIds = array();
			$otherResult = $other->get_result();
			while ($row = $otherResult->fetch_assoc()) {
				$otherIds[] = (int)$row['ID'];
			}
		}
		if ($otherIds) {
			$placeholders = implode(',', array_fill(0, count($otherIds), '?'));
			$query = 'SELECT s.ID, s.subjectcode, s.room, s.day, s.time FROM subjecttbl s JOIN student_subject_items i ON i.subject_ID = s.ID WHERE i.enrollment_ID IN (' . $placeholders . ')';
			$statement = $conn->prepare($query);
			$types = str_repeat('i', count($otherIds));
			app_bind_params($statement, $types, $otherIds);
			$statement->execute();
			$otherResult = $statement->get_result();
			while ($otherSubject = $otherResult->fetch_assoc()) {
				foreach (app_subject_slots($conn, $otherSubject) as $otherSlot) {
					foreach ($subjects as $subject) {
						foreach ($slotMap[(int)$subject['ID']] as $slot) {
							if (app_slots_overlap($slot, $otherSlot)) {
								$conflicts[] = $subject['subjectcode'] . ' conflicts with ' . $otherSubject['subjectcode'] . ' from an earlier enrollment (' . $otherSlot['label'] . ').';
							}
						}
					}
				}
			}
		}
		$conflicts = array_values(array_unique($conflicts));
		if ($conflicts) {
			$error = 'Schedule conflict: ' . implode(' ', $conflicts);
		}
	}
}

if (isset($_POST['reg']) && $error === '') {
	$subjectList = implode(',', $codes) . ',';
	try {
		$conn->begin_transaction();
		if ($reuseId > 0) {
			/* Rejected form, reused in place: the UNIQUE term key stays
			   satisfied, the enrollment ID (and any payments already made
			   against it) is preserved, and the old review is cleared. */
			$update = $conn->prepare("UPDATE student_subject SET subjects = ?, totalunits = ?, approved = 0, status = 'pending', review_note = NULL, reviewed_at = NULL, reviewed_by = NULL, tuition_amount = ?, entrance_fee = ?, internet_fee = ?, misc_fee = ?, lab_fee = ?, tutorial_fee = ?, total_amount = ?, term_id = ?, created_at = CURRENT_TIMESTAMP WHERE ID = ? AND studentID = ?");
			$update->bind_param('sidddddddiis', $subjectList, $totalUnits, $assessment['tuition'], $assessment['entrance'], $assessment['internet'], $assessment['misc'], $assessment['lab'], $assessment['tutorial'], $assessment['total'], $termId, $reuseId, $student['studnum']);
			$update->execute();
			$enrollmentId = $reuseId;
			$deleteItems = $conn->prepare('DELETE FROM student_subject_items WHERE enrollment_ID = ?');
			$deleteItems->bind_param('i', $enrollmentId);
			$deleteItems->execute();
		} else {
			$approved = 0;
			$status = 'pending';
			$insert = $conn->prepare('INSERT INTO student_subject (studentID, subjects, totalunits, approved, status, semester, school_year, term_id, created_at, tuition_amount, entrance_fee, internet_fee, misc_fee, lab_fee, tutorial_fee, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?, ?, ?, ?, ?, ?, ?)');
			$insert->bind_param('ssiisssiddddddd', $student['studnum'], $subjectList, $totalUnits, $approved, $status, $semester, $schoolYear, $termId, $assessment['tuition'], $assessment['entrance'], $assessment['internet'], $assessment['misc'], $assessment['lab'], $assessment['tutorial'], $assessment['total']);
			$insert->execute();
			$enrollmentId = $conn->insert_id;
		}
		$itemInsert = $conn->prepare('INSERT INTO student_subject_items (enrollment_ID, subject_ID) VALUES (?, ?)');
		foreach ($subjects as $subject) {
			$subjectId = (int)$subject['ID'];
			$itemInsert->bind_param('ii', $enrollmentId, $subjectId);
			$itemInsert->execute();
		}
		$conn->commit();
		unset($_SESSION['csrf_token']);
		$_SESSION['flash_success'] = $reuseId > 0
			? 'Your corrected enrollment form was submitted successfully.'
			: 'Your enrollment form was submitted successfully.';
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
		<img src="../assets/logo.jpg" width="130" height="100" alt="John Paul College">
		<p>John Paul College<br>MG ANDAYA COMPOUND<br>Odiong, Roxas, Oriental Mindoro, Philippines</p>
		<h1>Unofficial Enrollment Form</h1>
		<p><?php echo app_h($semester); ?> &mdash; School Year <?php echo app_h($schoolYear); ?></p>
		<p><strong>Student:</strong> <?php echo app_h($student['fname'] . ' ' . $student['minitial'] . ' ' . $student['lname']); ?>
			&nbsp; <strong>Course:</strong> <?php echo app_h(strtoupper($student['course'])); ?>
			&nbsp; <strong>Year:</strong> <?php echo app_h($student['yearlvl']); ?></p>
		<?php if ($reuseId > 0) { ?><p class="form-success" role="status">This replaces your earlier rejected form for this term. Any payments already recorded against it carry over.</p><?php } ?>
	</header>
	<?php if ($error !== '') { ?><p class="form-error" role="alert"><?php echo app_h($error); ?></p><p><a href="enroll.php">Return to subject selection</a></p><?php } ?>
	<?php if ($subjects && $error === '') { ?>
	<?php if ($unparsable) { ?><p class="form-error" role="note">No schedule is listed for <?php echo app_h(implode(', ', $unparsable)); ?>, so those subjects were not checked for time conflicts. Please confirm their schedule with the registrar.</p><?php } ?>
	<div class="table-wrap">
	<table>
		<thead><tr><th>Subject</th><th>Units</th><th>Per Unit</th><th>Tutorial</th><th>Lab</th><th>Room</th><th>Schedule</th><th>Instructor</th><th>Seats left</th><th>Total</th></tr></thead>
		<tbody>
		<?php foreach ($subjects as $subject) {
			$labels = array();
			foreach ($slotMap[(int)$subject['ID']] as $slot) {
				$labels[] = $slot['label'];
			}
			$scheduleText = $labels ? implode('; ', $labels) : trim($subject['day'] . ' ' . $subject['time']);
		?>
			<tr>
				<td><?php echo app_h($subject['subjectcode']); ?></td>
				<td><?php echo (int)$subject['numofunits']; ?></td>
				<td><?php echo number_format($fees['per_unit'], 2); ?></td>
				<td><?php echo $subject['istutorial'] ? number_format($fees['tutorial'], 2) : '-'; ?></td>
				<td><?php echo $subject['islab'] ? number_format($fees['lab'], 2) : '-'; ?></td>
				<td><?php echo app_h($subject['room']); ?></td>
				<td><?php echo app_h($scheduleText !== '' ? $scheduleText : 'Not scheduled'); ?></td>
				<td><?php echo app_h($subject['instructor']); ?></td>
				<td><?php echo (int)$seatsMap[(int)$subject['ID']]; ?></td>
				<td><?php echo number_format((int)$subject['numofunits'] * $fees['per_unit'], 2); ?></td>
			</tr>
		<?php } ?>
		</tbody>
		<tfoot>
			<tr><th colspan="9">Entrance fee</th><td><?php echo number_format($assessment['entrance'], 2); ?></td></tr>
			<tr><th colspan="9">Internet</th><td><?php echo number_format($assessment['internet'], 2); ?></td></tr>
			<tr><th colspan="9">Miscellaneous</th><td><?php echo number_format($assessment['misc'], 2); ?></td></tr>
			<tr><th colspan="9">Total units: <?php echo $totalUnits; ?> (maximum <?php echo (int)$fees['max_units']; ?>)</th><th><?php echo number_format($total, 2); ?></th></tr>
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
