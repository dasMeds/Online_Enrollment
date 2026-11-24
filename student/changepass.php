<?php
require_once __DIR__ . '/includes/conn.php';
require_once __DIR__ . '/logcheck.php';

$error = '';
$success = '';
$accountId = (int)$_SESSION['userID'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cpass'])) {
	app_verify_csrf();
	$oldPassword = app_input('opassword');
	$newPassword = app_input('password');
	$confirmPassword = app_input('password2');
	$statement = $conn->prepare('SELECT password FROM usertbl WHERE ID = ? AND user_type = "student"');
	$statement->bind_param('i', $accountId);
	$statement->execute();
	$account = $statement->get_result()->fetch_assoc();

	if (!$account || !(password_verify($oldPassword, $account['password']) || hash_equals($account['password'], $oldPassword))) {
		$error = 'The current password is incorrect.';
	} elseif (strlen($newPassword) < 12 || strlen($newPassword) > 72) {
		$error = 'The new password must be between 12 and 72 characters.';
	} elseif (!hash_equals($newPassword, $confirmPassword)) {
		$error = 'The new password entries do not match.';
	} else {
		$hash = password_hash($newPassword, PASSWORD_DEFAULT);
		$update = $conn->prepare('UPDATE usertbl SET password = ? WHERE ID = ? AND user_type = "student"');
		$update->bind_param('si', $hash, $accountId);
		$update->execute();
		session_regenerate_id(true);
		$success = 'Password updated successfully.';
		unset($_SESSION['csrf_token']);
	}
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Change Password | John Paul College</title>
<link href="style.css" rel="stylesheet" type="text/css">
</head>
<body>
<div class="header"></div>
<nav class="container" aria-label="Student navigation"><?php include __DIR__ . '/includes/menu.php'; ?></nav>
<main id="main">
	<div class="shell">
		<h1>Change Password</h1>
		<?php if ($error !== '') { ?><p class="form-error" role="alert"><?php echo app_h($error); ?></p><?php } ?>
		<?php if ($success !== '') { ?><p class="form-success" role="status"><?php echo app_h($success); ?></p><?php } ?>
		<form class="cmxform" method="post">
			<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
			<fieldset>
				<legend>Verify your identity</legend>
				<label for="opassword">Current password</label>
				<input id="opassword" type="password" name="opassword" autocomplete="current-password" required>
				<label for="password">New password (12-72 characters)</label>
				<input id="password" type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required>
				<label for="password2">Confirm new password</label>
				<input id="password2" type="password" name="password2" minlength="12" maxlength="72" autocomplete="new-password" required>
				<button type="submit" name="cpass" value="1">Change password</button>
			</fieldset>
		</form>
	</div>
</main>
<footer id="footer"><div class="shell"><?php include __DIR__ . '/includes/menu2.php'; ?></div></footer>
</body>
</html>
