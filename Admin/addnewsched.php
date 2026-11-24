<?php
require_once __DIR__ . '/includes/conn.php';
require_once __DIR__ . '/logcheck.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
	app_verify_csrf();
	$sectionId = app_int_input('section_ID');
	$subjectId = app_int_input('subject_ID');
	$instructorId = app_int_input('Instructor_ID');
	$roomId = app_int_input('room_ID');
	$timeFrom = app_input('time_from');
	$timeTo = app_input('time_to');
	$day = app_input('day');

	if ($sectionId < 1 || $subjectId < 1 || $instructorId < 0 || $roomId < 0 || $instructorId > 4294967295 || $roomId > 4294967295 || !in_array($day, array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), true) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $timeFrom) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $timeTo) || $timeTo <= $timeFrom) {
		$error = 'Choose a section, a same-course subject, a day, valid instructor and room IDs, and a valid time range.';
	} else {
		try {
			$assignment = $conn->prepare('SELECT sec.section_course, sub.course_con, sub.semester FROM sections_tbl sec JOIN subjecttbl sub ON sub.ID = ? WHERE sec.section_ID = ?');
			$assignment->bind_param('ii', $subjectId, $sectionId);
			$assignment->execute();
			$assigned = $assignment->get_result()->fetch_assoc();
			if (!$assigned || $assigned['section_course'] !== $assigned['course_con']) {
				$error = 'The selected subject must belong to the selected section course.';
			} else {
				$conflict = $conn->prepare('SELECT sched_ID FROM sched_tbl WHERE section_ID = ? AND semester = ? AND day = ? AND (subject_ID = ? OR (time_from < ? AND time_to > ?)) LIMIT 1');
				$conflict->bind_param('ississ', $sectionId, $assigned['semester'], $day, $subjectId, $timeTo, $timeFrom);
				$conflict->execute();
				if ($conflict->get_result()->fetch_assoc()) {
					$error = 'This subject or time slot is already scheduled for that section and semester.';
				} else {
					$insert = $conn->prepare('INSERT INTO sched_tbl (section_ID, subject_ID, Instructor_ID, time_from, time_to, room_ID, semester, day) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
					$insert->bind_param('iiississ', $sectionId, $subjectId, $instructorId, $timeFrom, $timeTo, $roomId, $assigned['semester'], $day);
					$insert->execute();
					$_SESSION['flash_success'] = 'Schedule added.';
					header('Location: schedreg.php');
					exit;
				}
			}
		} catch (mysqli_sql_exception $exception) {
			error_log('Schedule creation failed: ' . $exception->getMessage());
			$error = 'The schedule could not be saved. Verify that the selected section and subject still exist.';
		}
	}
}

$sections = $conn->query('SELECT section_ID, section_name, section_course, yr_level FROM sections_tbl ORDER BY section_course, yr_level, section_name');
$subjects = $conn->query('SELECT ID, subjectcode, subjecttitle, course_con, semester FROM subjecttbl ORDER BY course_con, semester, subjectcode');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Add Schedule | John Paul College</title>
<link href="style.css" rel="stylesheet" type="text/css">
</head>
<body>
<div class="header"></div>
<nav class="container" aria-label="Administrator navigation"><?php include __DIR__ . '/includes/menu1.php'; ?></nav>
<main id="main">
	<div class="shell">
		<h1>Add Schedule</h1>
		<?php if ($error !== '') { ?><p class="form-error" role="alert"><?php echo app_h($error); ?></p><?php } ?>
		<?php if ($sections->num_rows === 0) { ?><p class="form-error" role="status">Create a section before adding a schedule. <a href="addnewsection.php">Add section</a></p><?php } ?>
		<form class="cmxform" method="post">
			<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
			<fieldset>
				<legend>Schedule details</legend>
				<label for="section_ID">Section</label>
				<select id="section_ID" name="section_ID" required>
					<option value="">Select a section</option>
					<?php while ($section = $sections->fetch_assoc()) { ?><option value="<?php echo (int)$section['section_ID']; ?>"><?php echo app_h($section['section_course'] . ' ' . $section['section_name'] . ' - Year ' . $section['yr_level']); ?></option><?php } ?>
				</select>
				<label for="subject_ID">Subject</label>
				<select id="subject_ID" name="subject_ID" required>
					<option value="">Select a subject</option>
					<?php while ($subject = $subjects->fetch_assoc()) { ?><option value="<?php echo (int)$subject['ID']; ?>"><?php echo app_h(strtoupper($subject['course_con']) . ' · ' . $subject['semester'] . ' · ' . $subject['subjectcode'] . ' - ' . $subject['subjecttitle']); ?></option><?php } ?>
				</select>
				<label for="day">Day</label>
				<select id="day" name="day" required><option value="Monday">Monday</option><option value="Tuesday">Tuesday</option><option value="Wednesday">Wednesday</option><option value="Thursday">Thursday</option><option value="Friday">Friday</option><option value="Saturday">Saturday</option></select>
				<label for="Instructor_ID">Instructor ID</label>
				<input id="Instructor_ID" name="Instructor_ID" type="number" min="0" value="0" required>
				<label for="room_ID">Room ID</label>
				<input id="room_ID" name="room_ID" type="number" min="0" value="0" required>
				<label for="time_from">Starts at</label>
				<input id="time_from" name="time_from" type="time" required>
				<label for="time_to">Ends at</label>
				<input id="time_to" name="time_to" type="time" required>
				<button type="submit" name="register" value="1" <?php echo $sections->num_rows === 0 ? 'disabled' : ''; ?>>Add schedule</button>
			</fieldset>
		</form>
	</div>
</main>
</body>
</html>
