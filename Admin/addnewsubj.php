<?php
require_once __DIR__ . '/includes/conn.php';
require_once __DIR__ . '/logcheck.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
	app_verify_csrf();
	$course = app_input('course');
	$semester = app_input('semester');
	$stitle = trim(app_input('stitle'));
	$scode = trim(app_input('scode'));
	$sdescription = trim(app_input('cdescription'));
	$numunits = app_int_input('numunits');
	$sroom = trim(app_input('sroom'));
	$stime = trim(app_input('stime'));
	$sday = trim(app_input('sday'));
	$sinstructor = trim(app_input('sinstructor'));
	$islab = isset($_POST['islab']) ? 1 : 0;
	$istutorial = isset($_POST['istutorial']) ? 1 : 0;
	$capacity = app_int_input('capacity', 40);
	if ($capacity < 0 || $capacity > 500) {
		$capacity = 40;
	}

	$lengthLimits = array(array($scode, 30), array($stitle, 150), array($sroom, 100), array($stime, 100), array($sinstructor, 150), array($sday, 50));
	foreach ($lengthLimits as $field) {
		if (strlen($field[0]) > $field[1]) {
			$error = 'One or more subject fields exceed the allowed length.';
			break;
		}
	}
	if ($error === '' && (!array_key_exists($course, app_course_options()) || !in_array($semester, array('1st Semester', '2nd Semester', 'Summer'), true) || $scode === '' || $stitle === '' || $numunits < 1 || $numunits > 6)) {
		$error = 'Enter a valid course, semester, subject code, title, and unit count.';
	}
	if ($error === '') {
		try {
			$insert = $conn->prepare('INSERT INTO subjecttbl (subjectcode, subjecttitle, numofunits, capacity, room, time, instructor, day, islab, istutorial, description, course_con, semester) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
			$insert->bind_param('ssiissssiiiss', $scode, $stitle, $numunits, $capacity, $sroom, $stime, $sinstructor, $sday, $islab, $istutorial, $sdescription, $course, $semester);
			$insert->execute();
			$_SESSION['flash_success'] = 'Subject added.';
			header('Location: subjectreg.php');
			exit;
		} catch (mysqli_sql_exception $exception) {
			error_log('Subject creation failed: ' . $exception->getMessage());
			$error = 'The subject could not be saved. Check that the subject code is not already in use.';
		}
	}
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Add Subject | John Paul College</title>
<link href="style.css" rel="stylesheet" type="text/css">
</head>
<body>
<div class="header"></div>
<nav class="container" aria-label="Administrator navigation"><?php include __DIR__ . '/includes/menu1.php'; ?></nav>
<main id="main">
	<div class="shell">
		<h1>Add Subject</h1>
		<?php if ($error !== '') { ?><p class="form-error" role="alert"><?php echo app_h($error); ?></p><?php } ?>
		<form class="cmxform" method="post">
			<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
			<fieldset>
				<legend>Subject details</legend>
				<div class="field-grid">
					<label for="course">Course<select id="course" name="course" required><?php foreach (app_course_options() as $courseCode => $courseTitle) { ?><option value="<?php echo app_h($courseCode); ?>"><?php echo app_h(strtoupper($courseCode) . ' - ' . $courseTitle); ?></option><?php } ?></select></label>
					<label for="semester">Semester<select id="semester" name="semester" required><option value="1st Semester">1st Semester</option><option value="2nd Semester">2nd Semester</option><option value="Summer">Summer</option></select></label>
					<label for="scode">Subject code<input id="scode" name="scode" maxlength="30" required></label>
					<label for="stitle">Subject title<input id="stitle" name="stitle" maxlength="150" required></label>
					<label for="numunits">Units<select id="numunits" name="numunits" required><?php for ($units = 1; $units <= 6; $units++) { ?><option value="<?php echo $units; ?>"><?php echo $units; ?></option><?php } ?></select></label>
					<label for="capacity">Seat capacity<input id="capacity" name="capacity" type="number" min="0" max="500" value="40"></label>
					<label for="sroom">Room<input id="sroom" name="sroom" maxlength="100"></label>
					<label for="sday">Day<input id="sday" name="sday" maxlength="50"></label>
					<label for="stime">Time<input id="stime" name="stime" maxlength="100"></label>
					<label for="sinstructor">Instructor<input id="sinstructor" name="sinstructor" maxlength="150"></label>
					<label for="cdescription">Description<textarea id="cdescription" name="cdescription"></textarea></label>
				</div>
				<label class="check-label"><input type="checkbox" name="islab" value="1"> Includes lab fee</label>
				<label class="check-label"><input type="checkbox" name="istutorial" value="1"> Includes tutorial fee</label>
			</fieldset>
			<button type="submit" name="register" value="1">Add subject</button>
		</form>
	</div>
</main>
<footer id="footer"><div class="shell"><?php include __DIR__ . '/includes/menu2.php'; ?></div></footer>
</body>
</html>
