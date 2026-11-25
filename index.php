<?php
require_once __DIR__ . '/conn.php';
$loginError = '';
$timeout = isset($_GET['timeout']) ? true : false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	app_verify_csrf();
	$username = trim(app_input('username'));
	$password = app_input('password');
	$ip = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';

	/* Login rate limiting: 5 failed attempts for this username + IP in
	   15 minutes blocks further attempts with a clear message. A
	   successful login clears the attempts for that username. */
	$blocked = false;
	try {
		$countStatement = $conn->prepare('SELECT COUNT(*) AS n FROM login_attempts WHERE username = ? AND ip_address = ? AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
		$countStatement->bind_param('ss', $username, $ip);
		$countStatement->execute();
		$blocked = (int)$countStatement->get_result()->fetch_assoc()['n'] >= 5;
	} catch (mysqli_sql_exception $exception) {
		$blocked = false;
	}
	if ($blocked) {
		$loginError = 'Too many failed login attempts. Please wait 15 minutes and try again.';
	} else {
		try {
			$statement = $conn->prepare('SELECT ID, username, password, user_type, must_change_password FROM usertbl WHERE username = ? LIMIT 1');
			$statement->bind_param('s', $username);
			$statement->execute();
			$account = $statement->get_result()->fetch_assoc();
		} catch (mysqli_sql_exception $exception) {
			/* Pre-migration: no must_change_password column yet. */
			$statement = $conn->prepare('SELECT ID, username, password, user_type FROM usertbl WHERE username = ? LIMIT 1');
			$statement->bind_param('s', $username);
			$statement->execute();
			$account = $statement->get_result()->fetch_assoc();
			if ($account) { $account['must_change_password'] = 0; }
		}

		/* v3: password_verify only. The old plaintext fallback
		   (hash_equals($account['password'], $password)) was removed;
		   all seeded demo passwords are already password_hash() values.
		   Any legacy plaintext account must be reset by an administrator. */
		if ($account && password_verify($password, $account['password'])) {
			if (password_needs_rehash($account['password'], PASSWORD_DEFAULT)) {
				$upgrade = $conn->prepare('UPDATE usertbl SET password = ? WHERE ID = ?');
				$hash = password_hash($password, PASSWORD_DEFAULT);
				$upgrade->bind_param('si', $hash, $account['ID']);
				$upgrade->execute();
			}
			try {
				$clear = $conn->prepare('DELETE FROM login_attempts WHERE username = ?');
				$clear->bind_param('s', $username);
				$clear->execute();
			} catch (mysqli_sql_exception $exception) {
			}

			session_regenerate_id(true);
			$_SESSION['username'] = $account['username'];
			$_SESSION['type'] = $account['user_type'];
			$_SESSION['logcheck'] = 1;
			$_SESSION['userID'] = (int)$account['ID'];
			$_SESSION['last_activity'] = time();
			unset($_SESSION['csrf_token']);

			if ((int)$account['must_change_password'] === 1 && $account['user_type'] === 'student') {
				$_SESSION['must_change'] = 1;
				header('Location: student/changepass.php?forced=1');
				exit;
			}
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
			try {
				$insert = $conn->prepare('INSERT INTO login_attempts (username, ip_address) VALUES (?, ?)');
				$insert->bind_param('ss', $username, $ip);
				$insert->execute();
			} catch (mysqli_sql_exception $exception) {
			}
			$loginError = 'The username or password is incorrect.';
		}
	}
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>John Paul College — Online Enrollment | Login</title>
<link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
<link href="styles/style.css" rel="stylesheet" type="text/css">
</head>
<body>
	<div class="loginForm" align="center">
		<div class="login-brand">
			<img src="assets/logo.jpg" alt="John Paul College logo" width="84" height="84">
		</div>
		<h1>John Paul College</h1>
		<p class="login-intro">Online Enrollment — sign in to access your student or administrator portal.</p>
		<?php if ($timeout) { ?><p class="form-error" role="alert">You were signed out after 30 minutes of inactivity. Please sign in again.</p><?php } ?>
		<form method="POST" autocomplete="on">
			<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
			<?php if ($loginError !== '') { ?><p class="form-error" role="alert"><?php echo app_h($loginError); ?></p><?php } ?>
			<label for="username"><b>Username:</b></label>
			<input type="text" class="text" required name="username" id="username" maxlength="50" autocomplete="username">
			<label for="password"><b>Password:</b></label>
			<span class="password-field">
				<input type="password" class="text" required name="password" id="password" autocomplete="current-password">
				<button type="button" class="toggle-password" data-target="password" aria-pressed="false">Show</button>
			</span>
			<input type="submit" name="submit" value="Login" class="btn">
			<p><a class="forgot-link" href="forgot.php">Forgot password?</a></p>
		</form>
	</div>
	<script>
	/* Show / hide password (clean v2.1: no shared JS file). */
	(function () { var btn = document.querySelector('.toggle-password'); var input = document.getElementById('password'); if (!btn || !input) { return; } btn.addEventListener('click', function () { var show = input.type === 'password'; input.type = show ? 'text' : 'password'; btn.textContent = show ? 'Hide' : 'Show'; btn.setAttribute('aria-pressed', show ? 'true' : 'false'); }); })();
	</script>
</body>
</html>
