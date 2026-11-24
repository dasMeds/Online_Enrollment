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
$semester = isset($_GET['semester']) && in_array($_GET['semester'], $semesters, true) ? $_GET['semester'] : '1st Semester';
$statement = $conn->prepare('SELECT ID, subjectcode, subjecttitle, description, numofunits FROM subjecttbl WHERE course_con = ? AND semester = ? ORDER BY subjectcode');
$statement->bind_param('ss', $student['course'], $semester);
$statement->execute();
$subjects = $statement->get_result();
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
	<div class="shell">
		<h1>Enroll in Subjects</h1>
		<p><strong>Year level:</strong> <?php echo app_h($student['yearlvl']); ?> &nbsp; <strong>Course:</strong> <?php echo app_h(strtoupper($student['course'])); ?></p>
		<form class="catalog-filters" method="get">
			<label for="semester">Choose semester
				<select id="semester" name="semester"><?php foreach ($semesters as $option) { ?><option value="<?php echo app_h($option); ?>" <?php echo $semester === $option ? 'selected' : ''; ?>><?php echo app_h($option); ?></option><?php } ?></select>
			</label>
			<button type="submit">Show subjects</button>
			<span><?php echo $subjects->num_rows; ?> subjects available</span>
		</form>
		<form class="cmxform" method="post" action="showsubs.php">
			<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
			<input type="hidden" name="sem" value="<?php echo app_h($semester); ?>">
			<label class="subject-search" for="subject-search">Find a subject<input type="search" id="subject-search" placeholder="Search subject code or name"></label>
			<div class="table-wrap">
				<table>
					<thead><tr><th>Subject Code</th><th>Subject Title</th><th>Description</th><th>Units</th><th>Select</th></tr></thead>
					<tbody>
					<?php if ($subjects->num_rows === 0) { ?>
						<tr><td colspan="5">No subjects are currently available for your course.</td></tr>
					<?php } else { while ($subject = $subjects->fetch_assoc()) { ?>
						<tr class="subject-row">
							<td><?php echo app_h($subject['subjectcode']); ?></td>
							<td><?php echo app_h($subject['subjecttitle']); ?></td>
							<td><?php echo app_h($subject['description']); ?></td>
							<td><?php echo app_h($subject['numofunits']); ?></td>
							<td><input type="checkbox" name="subject[]" value="<?php echo (int)$subject['ID']; ?>" aria-label="Select <?php echo app_h($subject['subjectcode']); ?>"></td>
						</tr>
					<?php } } ?>
					</tbody>
				</table>
			</div>
			<p id="subject-search-count" aria-live="polite"></p>
			<p><button type="submit" name="preview" value="1">Review selected subjects</button> <button type="reset">Clear selection</button></p>
		</form>
	</div>
</main>
<footer id="footer"><div class="shell"><?php include __DIR__ . '/includes/menu2.php'; ?></div></footer>
<script>
var subjectSearch = document.getElementById('subject-search');
var subjectRows = Array.prototype.slice.call(document.querySelectorAll('.subject-row'));
var subjectSearchCount = document.getElementById('subject-search-count');
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
</script>
</body>
</html>
