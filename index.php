<?php
require_once __DIR__ . '/conn.php';
$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	app_verify_csrf();
	$username = trim(app_input('username'));
	$password = app_input('password');
	$statement = $conn->prepare('SELECT ID, username, password, user_type FROM usertbl WHERE username = ? LIMIT 1');
	$statement->bind_param('s', $username);
	$statement->execute();
	$account = $statement->get_result()->fetch_assoc();

	if ($account && (password_verify($password, $account['password']) || hash_equals($account['password'], $password))) {
		if (!password_verify($password, $account['password'])) {
			$upgrade = $conn->prepare('UPDATE usertbl SET password = ? WHERE ID = ?');
			$hash = password_hash($password, PASSWORD_DEFAULT);
			$upgrade->bind_param('si', $hash, $account['ID']);
			$upgrade->execute();
		} elseif (password_needs_rehash($account['password'], PASSWORD_DEFAULT)) {
			$upgrade = $conn->prepare('UPDATE usertbl SET password = ? WHERE ID = ?');
			$hash = password_hash($password, PASSWORD_DEFAULT);
			$upgrade->bind_param('si', $hash, $account['ID']);
			$upgrade->execute();
		}

		session_regenerate_id(true);
		$_SESSION['username'] = $account['username'];
		$_SESSION['type'] = $account['user_type'];
		$_SESSION['logcheck'] = 1;
		$_SESSION['userID'] = (int)$account['ID'];
		unset($_SESSION['csrf_token']);

		if ($account['user_type'] === 'admin') {
			header('Location: Admin/adminindex.php');
			exit;
		}
		if ($account['user_type'] === 'student') {
			header('Location: student/studentindex.php');
			exit;
		}
		session_destroy();
		$loginError = 'This account does not have an enabled role.';
	} else {
		$loginError = 'The username or password is incorrect.';
	}
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Online Enrollment | Login</title>

<link href="styles/style.css" rel="stylesheet" type="text/css"/>

</head>
<body>




	<div class = "loginForm" align="center">
	
	<h1>System Login</h1>
	<p class="login-intro">Sign in to access your student or administrator portal.</p>
	
<form method="POST" autocomplete="on">
<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
<div style="float:left; margin-top: -40px; margin-left: 40px;">
<img src="logo.jpg" alt="Online Enrollment" width="160" height="110">
</div>
<div style="margin-left: auto; margin-right: auto;">
	
	<?php if ($loginError !== '') { ?><p class="form-error" role="alert"><?php echo app_h($loginError); ?></p><?php } ?>
	<label for="username"><b>Username:</b></label>
	<input type="text" class="text" required name="username" id="username" maxlength="50" autocomplete="username"/>
	<label for="password"><b>Password:</b></label>
	<input type="password" class="text" required name="password" id="password" autocomplete="current-password"/>
<input type="submit" name="submit" value="Login" class="btn"/>
	</div>
</form>
</body>
</html>