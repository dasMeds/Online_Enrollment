<?php
require_once __DIR__ . '/includes/conn.php';
require_once __DIR__ . '/logcheck.php';

$accountId = (int)$_SESSION['userID'];
$error = '';
$success = '';
$lookup = $conn->prepare('SELECT s.*, u.username FROM studenttbl s JOIN usertbl u ON u.ID = s.accountID WHERE s.accountID = ? AND u.user_type = "student"');
$lookup->bind_param('i', $accountId);
$lookup->execute();
$student = $lookup->get_result()->fetch_assoc();
if (!$student) {
	http_response_code(404);
	exit('Student profile was not found. Please contact the administrator.');
}

$editable = array('fname', 'minitial', 'lname', 'address', 'contact', 'emailaddress', 'birthdate', 'gender', 'fathername', 'mothername', 'guardianname', 'birthplace', 'civilstatus', 'elemschool', 'dategradelem', 'hsschool', 'datehsgrad', 'lastgrad', 'datelastgrad', 'addresslastgrad');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
	app_verify_csrf();
	$values = array();
	foreach ($editable as $field) {
		$values[$field] = trim(app_input($field));
	}
	if ($values['fname'] === '' || $values['lname'] === '') {
		$error = 'First and last names are required.';
	} elseif ($values['emailaddress'] !== '' && !filter_var($values['emailaddress'], FILTER_VALIDATE_EMAIL)) {
		$error = 'Enter a valid email address.';
	} elseif ($values['birthdate'] !== '' && !app_valid_date($values['birthdate'])) {
		$error = 'Enter a valid birth date.';
	} elseif (!in_array($values['gender'], array('', 'male', 'female', 'other'), true) || !in_array($values['civilstatus'], array('', 'single', 'married', 'widow'), true)) {
		$error = 'Choose a valid gender and civil status.';
	} else {
		$limits = array('fname' => 100, 'minitial' => 20, 'lname' => 100, 'address' => 500, 'contact' => 30, 'emailaddress' => 254, 'gender' => 20, 'fathername' => 150, 'mothername' => 150, 'guardianname' => 150, 'birthplace' => 150, 'civilstatus' => 30, 'elemschool' => 150, 'dategradelem' => 20, 'hsschool' => 150, 'datehsgrad' => 20, 'lastgrad' => 150, 'datelastgrad' => 20, 'addresslastgrad' => 500);
		foreach ($limits as $field => $limit) {
			if (strlen($values[$field]) > $limit) {
				$error = 'One or more profile fields are longer than allowed.';
				break;
			}
		}
	}
	if ($error === '') {
		$birthdate = $values['birthdate'] === '' ? null : $values['birthdate'];
		$update = $conn->prepare('UPDATE studenttbl SET fname = ?, minitial = ?, lname = ?, address = ?, contact = ?, emailaddress = ?, birthdate = ?, gender = ?, fathername = ?, mothername = ?, guardianname = ?, birthplace = ?, civilstatus = ?, elemschool = ?, dategradelem = ?, hsschool = ?, datehsgrad = ?, lastgrad = ?, datelastgrad = ?, addresslastgrad = ? WHERE accountID = ?');
		$update->bind_param('ssssssssssssssssssssi', $values['fname'], $values['minitial'], $values['lname'], $values['address'], $values['contact'], $values['emailaddress'], $birthdate, $values['gender'], $values['fathername'], $values['mothername'], $values['guardianname'], $values['birthplace'], $values['civilstatus'], $values['elemschool'], $values['dategradelem'], $values['hsschool'], $values['datehsgrad'], $values['lastgrad'], $values['datelastgrad'], $values['addresslastgrad'], $accountId);
		$update->execute();
		foreach ($values as $field => $value) {
			$student[$field] = $value;
		}
		$success = 'Profile updated successfully.';
		unset($_SESSION['csrf_token']);
	}
	if ($error !== '') {
		foreach ($editable as $field) {
			$student[$field] = app_input($field);
		}
	}
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Student Profile | John Paul College</title>
<link href="style.css" rel="stylesheet" type="text/css">
</head>
<body>
<div class="header"></div>
<nav class="container" aria-label="Student navigation"><?php include __DIR__ . '/includes/menu.php'; ?></nav>
<main id="main">
	<div class="shell">
		<div class="page-heading"><h1>My Profile</h1><a class="button" href="changepass.php">Change password</a></div>
		<?php if ($error !== '') { ?><p class="form-error" role="alert"><?php echo app_h($error); ?></p><?php } ?>
		<?php if ($success !== '') { ?><p class="form-success" role="status"><?php echo app_h($success); ?></p><?php } ?>
		<form class="cmxform profile-form" method="post">
			<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
			<fieldset>
				<legend>Account and enrollment</legend>
				<p><strong>Username:</strong> <?php echo app_h($student['username']); ?></p>
				<p><strong>Student number:</strong> <?php echo app_h($student['studnum']); ?></p>
				<p><strong>Course:</strong> <?php echo app_h(strtoupper($student['course'])); ?></p>
				<p><strong>Year level / section:</strong> <?php echo app_h($student['yearlvl']); ?></p>
			</fieldset>
			<fieldset>
				<legend>Personal details</legend>
				<label for="fname">First name</label><input id="fname" name="fname" maxlength="100" value="<?php echo app_h($student['fname']); ?>" required>
				<label for="minitial">Middle initial</label><input id="minitial" name="minitial" maxlength="20" value="<?php echo app_h($student['minitial']); ?>">
				<label for="lname">Last name</label><input id="lname" name="lname" maxlength="100" value="<?php echo app_h($student['lname']); ?>" required>
				<label for="birthdate">Birth date</label><input id="birthdate" name="birthdate" type="date" value="<?php echo app_h($student['birthdate']); ?>">
				<label for="gender">Gender</label><select id="gender" name="gender"><option value="">Not specified</option><?php foreach (array('male' => 'Male', 'female' => 'Female', 'other' => 'Other') as $value => $label) { ?><option value="<?php echo app_h($value); ?>" <?php echo $student['gender'] === $value ? 'selected' : ''; ?>><?php echo app_h($label); ?></option><?php } ?></select>
				<label for="civilstatus">Civil status</label><select id="civilstatus" name="civilstatus"><option value="">Not specified</option><?php foreach (array('single' => 'Single', 'married' => 'Married', 'widow' => 'Widowed') as $value => $label) { ?><option value="<?php echo app_h($value); ?>" <?php echo $student['civilstatus'] === $value ? 'selected' : ''; ?>><?php echo app_h($label); ?></option><?php } ?></select>
				<label for="birthplace">Birth place</label><input id="birthplace" name="birthplace" maxlength="150" value="<?php echo app_h($student['birthplace']); ?>">
				<label for="address">Address</label><textarea id="address" name="address" maxlength="500"><?php echo app_h($student['address']); ?></textarea>
				<label for="contact">Contact number</label><input id="contact" name="contact" maxlength="30" value="<?php echo app_h($student['contact']); ?>">
				<label for="emailaddress">Email address</label><input id="emailaddress" name="emailaddress" type="email" maxlength="254" value="<?php echo app_h($student['emailaddress']); ?>">
			</fieldset>
			<fieldset>
				<legend>Family and education</legend>
				<label for="fathername">Father's name</label><input id="fathername" name="fathername" maxlength="150" value="<?php echo app_h($student['fathername']); ?>">
				<label for="mothername">Mother's name</label><input id="mothername" name="mothername" maxlength="150" value="<?php echo app_h($student['mothername']); ?>">
				<label for="guardianname">Guardian's name</label><input id="guardianname" name="guardianname" maxlength="150" value="<?php echo app_h($student['guardianname']); ?>">
				<label for="elemschool">Elementary school</label><input id="elemschool" name="elemschool" maxlength="150" value="<?php echo app_h($student['elemschool']); ?>">
				<label for="dategradelem">Elementary graduation year</label><input id="dategradelem" name="dategradelem" maxlength="20" value="<?php echo app_h($student['dategradelem']); ?>">
				<label for="hsschool">High school</label><input id="hsschool" name="hsschool" maxlength="150" value="<?php echo app_h($student['hsschool']); ?>">
				<label for="datehsgrad">High school graduation year</label><input id="datehsgrad" name="datehsgrad" maxlength="20" value="<?php echo app_h($student['datehsgrad']); ?>">
				<label for="lastgrad">School last attended</label><input id="lastgrad" name="lastgrad" maxlength="150" value="<?php echo app_h($student['lastgrad']); ?>">
				<label for="datelastgrad">Last graduation year</label><input id="datelastgrad" name="datelastgrad" maxlength="20" value="<?php echo app_h($student['datelastgrad']); ?>">
				<label for="addresslastgrad">Last school's address</label><textarea id="addresslastgrad" name="addresslastgrad" maxlength="500"><?php echo app_h($student['addresslastgrad']); ?></textarea>
			</fieldset>
			<button type="submit" name="save_profile" value="1">Save profile</button>
		</form>
	</div>
</main>
<footer id="footer"><div class="shell"><?php include __DIR__ . '/includes/menu2.php'; ?></div></footer>
</body>
</html>
