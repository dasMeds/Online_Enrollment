<?php
require_once __DIR__ . '/includes/conn.php';
require_once __DIR__ . '/logcheck.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
	app_verify_csrf();
	$course = app_input('course');
	$name = trim(app_input('section_name'));
	$year = app_int_input('year_level');
	$max = app_int_input('max_num');
	$min = app_int_input('min_num');
	if (!array_key_exists($course, app_course_options()) || $name === '' || strlen($name) > 100 || $year < 1 || $year > 6 || $max < 0 || $max > 65535 || $min < 0 || $min > 65535 || ($max > 0 && $min > $max)) {
		$error = 'Enter a valid section name, course, year level, and capacity range.';
	} else {
		try {
			$insert = $conn->prepare('INSERT INTO sections_tbl (section_name, section_course, yr_level, max_num, min_num) VALUES (?, ?, ?, ?, ?)');
			$insert->bind_param('ssiii', $name, $course, $year, $max, $min);
			$insert->execute();
			$_SESSION['flash_success'] = 'Section added.';
			header('Location: sectionreg.php');
			exit;
		} catch (mysqli_sql_exception $exception) {
			error_log('Section creation failed: ' . $exception->getMessage());
			$error = 'The section could not be saved. Check that the name is unique for this course and year.';
		}
	}
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Add Section | John Paul College</title>
<link href="style.css" rel="stylesheet" type="text/css">
</head>
<body>
<div class="header"></div>
<nav class="container" aria-label="Administrator navigation"><?php include __DIR__ . '/includes/menu1.php'; ?></nav>
<main id="main">
	<div class="shell">
		<h1>Add Section</h1>
		<?php if ($error !== '') { ?><p class="form-error" role="alert"><?php echo app_h($error); ?></p><?php } ?>
		<form class="cmxform" method="post">
			<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
			<fieldset>
				<legend>Section details</legend>
				<label for="section_name">Section name</label>
				<input id="section_name" name="section_name" type="text" maxlength="100" required>
				<label for="course">Course</label>
				<select id="course" name="course" required>
					<?php foreach (app_course_options() as $courseCode => $courseTitle) { ?><option value="<?php echo app_h($courseCode); ?>"><?php echo app_h(strtoupper($courseCode) . ' - ' . $courseTitle); ?></option><?php } ?>
				</select>
				<label for="year_level">Year level</label>
				<select id="year_level" name="year_level" required>
					<?php for ($yearLevel = 1; $yearLevel <= 6; $yearLevel++) { ?><option value="<?php echo $yearLevel; ?>"><?php echo $yearLevel; ?></option><?php } ?>
				</select>
				<label for="min_num">Minimum students</label>
				<input id="min_num" name="min_num" type="number" min="0" value="0" required>
				<label for="max_num">Maximum students (0 = unlimited)</label>
				<input id="max_num" name="max_num" type="number" min="0" value="0" required>
				<button type="submit" name="register" value="1">Add section</button>
			</fieldset>
		</form>
	</div>
</main>
</body>
</html>
