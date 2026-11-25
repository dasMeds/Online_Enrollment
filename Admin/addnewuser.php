<?php
require_once __DIR__ . '/includes/conn.php';
require_once __DIR__ . '/logcheck.php';

$username = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
	app_verify_csrf();
	$username = trim(app_input('username'));
	$password = app_input('password');
	$course = app_input('course');
	$yrlvlsec = trim(app_input('yrlvlsec'));
	$snum = trim(app_input('snum'));
	$fname = trim(app_input('fname'));
	$minitial = trim(app_input('minitial'));
	$lname = trim(app_input('lname'));
	$fullname = trim($fname . ' ' . $minitial . ' ' . $lname);
	$gender = app_input('gender');
	$bdate = app_input('bdate') !== '' ? app_input('bdate') : null;
	$address = trim(app_input('address'));
	$cnum = trim(app_input('cnumber'));
	$email = trim(app_input('email'));
	$fathername = trim(app_input('fathername'));
	$mothername = trim(app_input('mothername'));
	$guardianname = trim(app_input('guardianname'));
	$civilstatus = app_input('cstatus');
	$bplace = trim(app_input('bplace'));
	$elemschool = trim(app_input('elemschool'));
	$yrelem = app_input('yrgradelem');
	$hsschool = trim(app_input('hschool'));
	$yrgradhs = app_input('yrgradhs');
	$lastschool = trim(app_input('lastschool'));
	$yrlast = app_input('yrlast');
	$gradaddresslast = trim(app_input('address2'));

	$lengthLimits = array(
		'username' => array($username, 50), 'student number' => array($snum, 20),
		'first name' => array($fname, 100), 'middle initial' => array($minitial, 20),
		'last name' => array($lname, 100), 'year level' => array($yrlvlsec, 50),
		'address' => array($address, 500), 'contact number' => array($cnum, 30),
		'email address' => array($email, 254), 'father name' => array($fathername, 150),
		'mother name' => array($mothername, 150), 'guardian name' => array($guardianname, 150),
		'birth place' => array($bplace, 150), 'elementary school' => array($elemschool, 150),
		'elementary graduation year' => array($yrelem, 20), 'high school' => array($hsschool, 150),
		'high school graduation year' => array($yrgradhs, 20), 'last school' => array($lastschool, 150),
		'last graduation year' => array($yrlast, 20), 'last school address' => array($gradaddresslast, 500)
	);
	foreach ($lengthLimits as $field => $limit) {
		if (strlen($limit[0]) > $limit[1]) {
			$error = 'The ' . $field . ' exceeds the allowed length.';
			break;
		}
	}

	if ($error === '' && ($username === '' || strlen($password) < 12 || strlen($password) > 72 || $snum === '' || $fname === '' || $lname === '' || $yrlvlsec === '' || strlen($fullname) > 150 || !array_key_exists($course, app_course_options()))) {
		$error = 'Enter a username, a password between 12 and 72 characters, student number, name, course, and year level using the allowed field lengths.';
	} elseif ($error === '' && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$error = 'Enter a valid email address.';
	} elseif ($error === '' && $bdate !== null && !app_valid_date($bdate)) {
		$error = 'Enter a valid birth date.';
	} elseif ($error === '' && (!in_array($gender, array('', 'male', 'female', 'other'), true) || !in_array($civilstatus, array('', 'single', 'married', 'widow'), true))) {
		$error = 'Choose a valid gender and civil status.';
	}
	if ($error === '') {
		try {
			$conn->begin_transaction();
			/* Admin-created accounts start with a temporary password: the
			   student must change it on first login, and the email is stored
			   so password reset works. */
			try {
				$accountInsert = $conn->prepare('INSERT INTO usertbl (username, password, fullname, user_type, email, must_change_password) VALUES (?, ?, ?, "student", ?, 1)');
				$passwordHash = password_hash($password, PASSWORD_DEFAULT);
				$accountInsert->bind_param('ssss', $username, $passwordHash, $fullname, $email);
			} catch (mysqli_sql_exception $exception) {
				/* Pre-migration: no email / must_change_password columns yet. */
				$accountInsert = $conn->prepare('INSERT INTO usertbl (username, password, fullname, user_type) VALUES (?, ?, ?, "student")');
				$passwordHash = password_hash($password, PASSWORD_DEFAULT);
				$accountInsert->bind_param('sss', $username, $passwordHash, $fullname);
			}
			$accountInsert->execute();
			$accountId = $conn->insert_id;

			$studentInsert = $conn->prepare('INSERT INTO studenttbl (accountID, studnum, fname, minitial, lname, address, contact, emailaddress, birthdate, course, yearlvl, gender, fathername, mothername, guardianname, birthplace, civilstatus, elemschool, dategradelem, hsschool, datehsgrad, lastgrad, datelastgrad, addresslastgrad) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
			$studentInsert->bind_param('i' . str_repeat('s', 23), $accountId, $snum, $fname, $minitial, $lname, $address, $cnum, $email, $bdate, $course, $yrlvlsec, $gender, $fathername, $mothername, $guardianname, $bplace, $civilstatus, $elemschool, $yrelem, $hsschool, $yrgradhs, $lastschool, $yrlast, $gradaddresslast);
			$studentInsert->execute();
			$conn->commit();
			app_audit($conn, 'user_create', 'student', (string)$accountId, 'Created student account ' . $username . ' (' . $snum . ', ' . $fullname . ').');
			$_SESSION['flash_success'] = 'Student account created. The student must change the temporary password on first login.';
			header('Location: userreg.php');
			exit;
		} catch (mysqli_sql_exception $exception) {
			$conn->rollback();
			error_log('Student account creation failed: ' . $exception->getMessage());
			$error = 'The account could not be created. Check that the username and student number are unique.';
		}
	}
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Add Student | John Paul College</title>
<link href="style.css" rel="stylesheet" type="text/css">
</head>
<body>
<div class="header"></div>
<nav class="container" aria-label="Administrator navigation"><?php include __DIR__ . '/includes/menu1.php'; ?></nav>
<main id="main">
	<div class="shell">
		<h1>Add Student</h1>
		<?php if ($error !== '') { ?><p class="form-error" role="alert"><?php echo app_h($error); ?></p><?php } ?>
		<form class="cmxform" method="post">
			<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
			<fieldset>
				<legend>Account credentials</legend>
				<div class="field-grid">
					<label>Username<input name="username" maxlength="50" autocomplete="username" value="<?php echo app_h($username); ?>" required></label>
					<label>Password (12-72 characters)<input name="password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></label>
				</div>
			</fieldset>
			<fieldset>
				<legend>Enrollment details</legend>
				<div class="field-grid">
					<label for="snum">Student number<input id="snum" name="snum" maxlength="20" required></label>
					<label for="course">Course<select id="course" name="course" required><?php foreach (app_course_options() as $courseCode => $courseTitle) { ?><option value="<?php echo app_h($courseCode); ?>"><?php echo app_h(strtoupper($courseCode) . ' - ' . $courseTitle); ?></option><?php } ?></select></label>
					<label for="yrlvlsec">Year level / section<input id="yrlvlsec" name="yrlvlsec" maxlength="50" required></label>
				</div>
			</fieldset>
			<fieldset>
				<legend>Personal details</legend>
				<div class="field-grid">
					<label for="fname">First name<input id="fname" name="fname" maxlength="100" required></label>
					<label for="minitial">Middle initial<input id="minitial" name="minitial" maxlength="20"></label>
					<label for="lname">Last name<input id="lname" name="lname" maxlength="100" required></label>
					<label for="bdate">Birth date<input id="bdate" name="bdate" type="date"></label>
					<label for="gender">Gender<select id="gender" name="gender"><option value="">Not specified</option><option value="female">Female</option><option value="male">Male</option><option value="other">Other</option></select></label>
					<label for="cstatus">Civil status<select id="cstatus" name="cstatus"><option value="">Not specified</option><option value="single">Single</option><option value="married">Married</option><option value="widow">Widowed</option></select></label>
					<label for="bplace">Birth place<input id="bplace" name="bplace" maxlength="150"></label>
					<label for="cnumber">Contact number<input id="cnumber" name="cnumber" maxlength="30" autocomplete="tel"></label>
					<label for="email">Email address<input id="email" name="email" type="email" maxlength="254" autocomplete="email"></label>
					<label for="address">Home address<textarea id="address" name="address" maxlength="500"></textarea></label>
				</div>
			</fieldset>
			<fieldset>
				<legend>Family and education</legend>
				<div class="field-grid">
					<label for="fathername">Father's name<input id="fathername" name="fathername" maxlength="150"></label>
					<label for="mothername">Mother's name<input id="mothername" name="mothername" maxlength="150"></label>
					<label for="guardianname">Guardian's name<input id="guardianname" name="guardianname" maxlength="150"></label>
					<label for="elemschool">Elementary school<input id="elemschool" name="elemschool" maxlength="150"></label>
					<label for="yrgradelem">Elementary graduation year<input id="yrgradelem" name="yrgradelem" inputmode="numeric" maxlength="4"></label>
					<label for="hschool">High school<input id="hschool" name="hschool" maxlength="150"></label>
					<label for="yrgradhs">High school graduation year<input id="yrgradhs" name="yrgradhs" inputmode="numeric" maxlength="4"></label>
					<label for="lastschool">School last attended<input id="lastschool" name="lastschool" maxlength="150"></label>
					<label for="yrlast">Last graduation year<input id="yrlast" name="yrlast" inputmode="numeric" maxlength="4"></label>
					<label for="address2">Last school's address<textarea id="address2" name="address2" maxlength="500"></textarea></label>
				</div>
			</fieldset>
			<button type="submit" name="register" value="1">Create student account</button>
		</form>
	</div>
</main>
<footer id="footer"><div class="shell"><?php include __DIR__ . '/includes/menu2.php'; ?></div></footer>
</body>
</html>
