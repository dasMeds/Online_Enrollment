<?php
/* Reset password from a selector + token link (see forgot.php). */
require_once __DIR__ . '/conn.php';

$selector = isset($_GET['selector']) ? (string)$_GET['selector'] : '';
$token = isset($_GET['token']) ? (string)$_GET['token'] : '';
$error = '';
$success = '';
$valid = false;
$accountId = 0;
$resetId = 0;

if ($selector !== '' && $token !== '' && ctype_xdigit($selector) && ctype_xdigit($token)) {
	$statement = $conn->prepare('SELECT ID, accountID, token_hash, expires_at, used FROM password_resets WHERE selector = ? LIMIT 1');
	$statement->bind_param('s', $selector);
	$statement->execute();
	$row = $statement->get_result()->fetch_assoc();
	if ($row && (int)$row['used'] === 0 && strtotime($row['expires_at']) > time() && hash_equals($row['token_hash'], hash('sha256', $token))) {
		$valid = true;
		$accountId = (int)$row['accountID'];
		$resetId = (int)$row['ID'];
	} else {
		$error = 'This reset link is invalid or has expired. Request a new one.';
	}
} else {
	$error = 'This reset link is invalid or has expired. Request a new one.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
	app_verify_csrf();
	$selector = app_input('selector');
	$token = app_input('token');
	$newPassword = app_input('password');
	$confirmPassword = app_input('password2');
	$statement = $conn->prepare('SELECT ID, accountID, token_hash, expires_at, used FROM password_resets WHERE selector = ? LIMIT 1');
	$statement->bind_param('s', $selector);
	$statement->execute();
	$row = $statement->get_result()->fetch_assoc();
	if (!$row || (int)$row['used'] !== 0 || strtotime($row['expires_at']) <= time() || !hash_equals($row['token_hash'], hash('sha256', $token))) {
		$error = 'This reset link is invalid or has expired. Request a new one.';
		$valid = false;
	} elseif (strlen($newPassword) < 12 || strlen($newPassword) > 72) {
		$error = 'The new password must be between 12 and 72 characters.';
	} elseif (!hash_equals($newPassword, $confirmPassword)) {
		$error = 'The new password entries do not match.';
	} else {
		$hash = password_hash($newPassword, PASSWORD_DEFAULT);
		$update = $conn->prepare('UPDATE usertbl SET password = ?, must_change_password = 0 WHERE ID = ?');
		$update->bind_param('si', $hash, $row['accountID']);
		$update->execute();
		$mark = $conn->prepare('UPDATE password_resets SET used = 1 WHERE ID = ?');
		$mark->bind_param('i', $row['ID']);
		$mark->execute();
		$success = 'Your password was reset. You can now sign in with your new password.';
		$valid = false;
	}
}

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>John Paul College — Online Enrollment | Reset Password</title>
<link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
<link href="styles/style.css" rel="stylesheet" type="text/css">
</head>
<body>
<div class="loginForm" align="center">
?>
<h1>Reset password</h1>
<?php if ($error !== '') { ?><p class="form-error" role="alert"><?php echo app_h($error); ?></p><?php } ?>
<?php if ($success !== '') { ?><p class="form-success" role="status"><?php echo app_h($success); ?></p><p><a class="button" href="index.php">Go to login</a></p><?php } ?>
<?php if ($valid) { ?>
<p>Choose a new password (12–72 characters). The link expires 1 hour after it was requested.</p>
<form class="cmxform" method="post">
	<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
	<input type="hidden" name="selector" value="<?php echo app_h($selector); ?>">
	<input type="hidden" name="token" value="<?php echo app_h($token); ?>">
	<label for="password">New password</label>
	<span class="password-field">
		<input id="password" type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required>
		<button type="button" class="toggle-password" data-target="password" aria-pressed="false">Show</button>
	</span>
	<label for="password2">Confirm new password</label>
	<input id="password2" type="password" name="password2" minlength="12" maxlength="72" autocomplete="new-password" required>
	<button type="submit" name="reset_password" value="1">Reset password</button>
</form>
<?php } elseif ($success === '') { ?>
<p><a class="button" href="forgot.php">Request a new reset link</a></p>
<?php } ?>
</div>
<script>
(function () { var btn = document.querySelector('.toggle-password'); var input = document.getElementById('password'); if (!btn || !input) { return; } btn.addEventListener('click', function () { var show = input.type === 'password'; input.type = show ? 'text' : 'password'; btn.textContent = show ? 'Hide' : 'Show'; btn.setAttribute('aria-pressed', show ? 'true' : 'false'); }); })();
</script>
</body>
</html>
