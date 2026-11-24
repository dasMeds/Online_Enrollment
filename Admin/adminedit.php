<?php
require_once __DIR__ . '/includes/conn.php';
require_once __DIR__ . '/logcheck.php';

$error = '';
$accountId = (int)$_SESSION['userID'];
$lookup = $conn->prepare('SELECT username FROM usertbl WHERE ID = ? AND user_type = "admin"');
$lookup->bind_param('i', $accountId);
$lookup->execute();
$account = $lookup->get_result()->fetch_assoc();
if (!$account) {
	http_response_code(404);
	exit('Administrator account was not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
	app_verify_csrf();
	$username = trim(app_input('username'));
	$newPassword = app_input('password');
	if ($username === '' || strlen($username) > 50) {
		$error = 'Enter a username no longer than 50 characters.';
	} elseif ($newPassword !== '' && (strlen($newPassword) < 12 || strlen($newPassword) > 72)) {
		$error = 'A new password must be between 12 and 72 characters. Leave it blank to keep the current password.';
	} else {
		try {
			if ($newPassword === '') {
				$update = $conn->prepare('UPDATE usertbl SET username = ? WHERE ID = ? AND user_type = "admin"');
				$update->bind_param('si', $username, $accountId);
			} else {
				$hash = password_hash($newPassword, PASSWORD_DEFAULT);
				$update = $conn->prepare('UPDATE usertbl SET username = ?, password = ? WHERE ID = ? AND user_type = "admin"');
				$update->bind_param('ssi', $username, $hash, $accountId);
			}
			$update->execute();
			session_regenerate_id(true);
			$_SESSION['username'] = $username;
			$_SESSION['flash_success'] = 'Administrator account updated.';
			header('Location: userreg.php');
			exit;
		} catch (mysqli_sql_exception $exception) {
			error_log('Administrator account update failed: ' . $exception->getMessage());
			$error = 'The account could not be updated. Check that the username is unique.';
		}
	}
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Edit Administrator Account | John Paul College</title>
<link href="style.css" rel="stylesheet" type="text/css">
</head>
<body>
<div class="header"></div>
<nav class="container" aria-label="Administrator navigation"><?php include __DIR__ . '/includes/menu1.php'; ?></nav>
<main id="main">
	<div class="shell">
		<h1>Administrator Account</h1>
		<?php if ($error !== '') { ?><p class="form-error" role="alert"><?php echo app_h($error); ?></p><?php } ?>
		<form class="cmxform" method="post">
			<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
			<fieldset>
				<legend>Account details</legend>
				<label for="username">Username</label>
				<input id="username" name="username" type="text" maxlength="50" autocomplete="username" value="<?php echo app_h(app_input('username', $account['username'])); ?>" required>
				<label for="password">New password</label>
				<input id="password" name="password" type="password" minlength="12" maxlength="72" autocomplete="new-password">
				<p>Leave the password blank to keep the current password.</p>
				<button type="submit" name="register" value="1">Save account</button>
			</fieldset>
		</form>
	</div>
</main>
</body>
</html>
