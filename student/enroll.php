<?php
require_once __DIR__ . '/includes/conn.php';
require_once __DIR__ . '/logcheck.php';

$statement = $conn->prepare('SELECT studnum, course, yearlvl FROM studenttbl WHERE accountID = ?');
$statement->bind_param('i', $_SESSION['userID']);
$statement->execute();
$student = $statement->get_result()->fetch_assoc();
if (!$student) {
	http_response_code(404);
	exit('Student profile was not found. Please contact the administrator.');
}

$semesters = array('1st Semester', '2nd Semester', 'Summer');
/* Term fix: the school year comes from the current term, never from
   date('Y') — a January 2nd-semester enrollment stays 2026-2027. */
$currentTerm = app_current_term($conn);
$schoolYear = $currentTerm ? $currentTerm['school_year'] : app_school_year_for_date(date('Y-m-d'));
$semester = isset($_GET['semester']) && in_array($_GET['semester'], $semesters, true)
	? $_GET['semester']
	: ($currentTerm ? $currentTerm['semester'] : '1st Semester');
$fees = app_fee_settings($conn);
$enrollmentState = app_enrollment_open($conn);
$statement = $conn->prepare('SELECT ID, subjectcode, subjecttitle, description, numofunits, room, day, time, instructor FROM subjecttbl WHERE course_con = ? AND semester = ? ORDER BY subjectcode');
$statement->bind_param('ss', $student['course'], $semester);
$statement->execute();
$subjectRows = array();
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
	$row['seats_left'] = app_capacity_remaining($conn, (int)$row['ID'], $semester, $schoolYear);
	$subjectRows[] = $row;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Enroll | John Paul College</title>
<link href="style.css" rel="stylesheet" type="text/css">
</head>
<body>
<div class="header"></div>
<nav class="container" aria-label="Student navigation"><?php include __DIR__ . '/includes/menu.php'; ?></nav>
<main id="main">
	<div class="shell wide-shell">
		<h1>Enroll in Subjects</h1>
		<p><strong>Year level:</strong> <?php echo app_h($student['yearlvl']); ?> &nbsp; <strong>Course:</strong> <?php echo app_h(strtoupper($student['course'])); ?> &nbsp; <strong>Maximum units:</strong> <?php echo (int)$fees['max_units']; ?></p>
		<?php if (!$enrollmentState['open']) { ?><p class="enrollment-closed" role="alert"><?php echo app_h($enrollmentState['reason']); ?></p><?php } ?>
		<form class="catalog-filters" method="get">
			<label for="semester">Choose semester
				<select id="semester" name="semester"><?php foreach ($semesters as $option) { ?><option value="<?php echo app_h($option); ?>" <?php echo $semester === $option ? 'selected' : ''; ?>><?php echo app_h($option); ?></option><?php } ?></select>
			</label>
			<button type="submit">Show subjects</button>
			<span><?php echo count($subjectRows); ?> subjects available</span>
		</form>
		<form class="cmxform" method="post" action="showsubs.php">
			<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
			<input type="hidden" name="sem" value="<?php echo app_h($semester); ?>">
			<label class="subject-search" for="subject-search">Find a subject<input type="search" id="subject-search" placeholder="Search subject code or name"></label>
			<div class="table-wrap">
				<table>
					<thead><tr><th>Subject Code</th><th>Subject Title</th><th>Description</th><th>Units</th><th>Schedule</th><th>Room</th><th>Instructor</th><th>Seats left</th><th>Select</th></tr></thead>
					<tbody>
					<?php if (!$subjectRows) { ?>
						<tr><td colspan="9">No subjects are currently available for your course.</td></tr>
					<?php } else { foreach ($subjectRows as $subject) { ?>
						<tr class="subject-row">
							<td><?php echo app_h($subject['subjectcode']); ?></td>
							<td><?php echo app_h($subject['subjecttitle']); ?></td>
							<td><?php echo app_h($subject['description']); ?></td>
							<td><?php echo (int)$subject['numofunits']; ?></td>
							<td><?php echo app_h($subject['schedule_text'] !== '' ? $subject['schedule_text'] : 'Not scheduled'); ?></td>
							<td><?php echo app_h($subject['room_text'] !== '' ? $subject['room_text'] : '—'); ?></td>
							<td><?php echo app_h($subject['instructor'] !== '' ? $subject['instructor'] : '—'); ?></td>
							<td><?php echo (int)$subject['seats_left']; ?></td>
							<td><input type="checkbox" class="subject-check" name="subject[]" value="<?php echo (int)$subject['ID']; ?>" data-units="<?php echo (int)$subject['numofunits']; ?>" aria-label="Select <?php echo app_h($subject['subjectcode']); ?>" <?php echo ($subject['seats_left'] <= 0 || !$enrollmentState['open']) ? 'disabled' : ''; ?>></td>
						</tr>
					<?php } } ?>
					</tbody>
				</table>
			</div>
			<p id="subject-search-count" aria-live="polite"></p>
			<p><strong>Selected units: <span id="selected-units">0</span> / <?php echo (int)$fees['max_units']; ?></strong></p>
			<p><button type="submit" name="preview" value="1" <?php echo !$enrollmentState['open'] ? 'disabled' : ''; ?>>Review selected subjects</button> <button type="reset">Clear selection</button></p>
		</form>
	</div>
</main>
<footer id="footer"><div class="shell"><?php include __DIR__ . '/includes/menu2.php'; ?></div></footer>
<script>
var subjectSearch = document.getElementById('subject-search');
var subjectRows = Array.prototype.slice.call(document.querySelectorAll('.subject-row'));
var subjectSearchCount = document.getElementById('subject-search-count');
var selectedUnits = document.getElementById('selected-units');
var maxUnits = <?php echo (int)$fees['max_units']; ?>;
function updateUnits() {
	var total = 0;
	Array.prototype.slice.call(document.querySelectorAll('.subject-check')).forEach(function (box) {
		if (box.checked) {
			total += parseInt(box.getAttribute('data-units'), 10) || 0;
		}
	});
	selectedUnits.textContent = total;
	selectedUnits.style.color = total > maxUnits ? '#7a1f1f' : '';
}
if (subjectSearch) {
	subjectSearch.addEventListener('input', function () {
		var query = subjectSearch.value.trim().toLowerCase();
		var visible = 0;
		subjectRows.forEach(function (row) {
			var matches = row.textContent.toLowerCase().indexOf(query) !== -1;
			row.hidden = !matches;
			visible += matches ? 1 : 0;
		});
		subjectSearchCount.textContent = 'Showing ' + visible + ' matching subjects.';
	});
}
Array.prototype.slice.call(document.querySelectorAll('.subject-check')).forEach(function (box) {
	box.addEventListener('change', updateUnits);
});
updateUnits();
</script>
</body>
</html>
