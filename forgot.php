<?php
/* Forgot password. Generates a selector + token, stores only the token
   hash, expiry 1 hour. There is no mail server on a stock XAMPP install,
   so the reset link is NOT shown on screen: it is emailed only when the
   admin enables SMTP (app_settings smtp_enabled + mail_from, see
   docs/SETUP.md). For local testing only, setting reset_debug_log = 1 in
   app_settings writes the link to reset_links.log in the project root —
   that file must never exist on a real server. */
require_once __DIR__ . '/conn.php';

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	app_verify_csrf();
	$identifier = trim(app_input('identifier'));
	if ($identifier === '') {
		$error = 'Enter your username or email address.';
	} else {
		$statement = $conn->prepare('SELECT ID, username, email FROM usertbl WHERE username = ? OR email = ? LIMIT 1');
		$statement->bind_param('ss', $identifier, $identifier);
		$statement->execute();
		$account = $statement->get_result()->fetch_assoc();
		/* Always show the same message, whether or not the account exists,
		   so the form cannot be used to probe for accounts. */
		$message = 'If an account matches, a password reset link has been sent to its email address. The link expires in 1 hour.';
		if ($account) {
			$selector = bin2hex(random_bytes(8));
			$token = bin2hex(random_bytes(32));
			$tokenHash = hash('sha256', $token);
			$insert = $conn->prepare('INSERT INTO password_resets (accountID, selector, token_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))');
			$insert->bind_param('iss', $account['ID'], $selector, $tokenHash);
			$insert->execute();
			$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
			$host = isset($_SERVER['HTTP_HOST']) ? (string)$_SERVER['HTTP_HOST'] : 'localhost';
			$link = $scheme . '://' . $host . dirname((string)$_SERVER['SCRIPT_NAME']) . '/reset.php?selector=' . $selector . '&token=' . $token;
			$toEmail = $account['email'] !== '' ? $account['email'] : '';
			if ($toEmail !== '') {
				app_mail_notify($conn, $toEmail, 'John Paul College — password reset', "Use this link within 1 hour to reset your password:\n\n" . $link . "\n\nIf you did not ask for this, ignore this message.");
			}
			if (app_setting_get($conn, 'reset_debug_log', '0') === '1') {
				@file_put_contents(__DIR__ . '/reset_links.log', date('Y-m-d H:i:s') . ' ' . $account['username'] . ' ' . $link . PHP_EOL, FILE_APPEND);
			}
		}
	}
}

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>John Paul College — Online Enrollment | Forgot Password</title>
<link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
<link href="styles/style.css" rel="stylesheet" type="text/css">
</head>
<body>
<div class="loginForm" align="center">
?>
<h1>Forgot password?</h1>
<p>Enter your username or the email address on your account. If it matches, we will send a reset link that expires in 1 hour.</p>
<?php if ($error !== '') { ?><p class="form-error" role="alert"><?php echo app_h($error); ?></p><?php } ?>
<?php if ($message !== '') { ?><p class="form-success" role="status"><?php echo app_h($message); ?></p><?php } ?>
<form class="cmxform" method="post">
	<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
	<label for="identifier">Username or email address</label>
	<input id="identifier" name="identifier" maxlength="254" autocomplete="username" required>
	<button type="submit">Send reset link</button>
</form>
<p><a href="index.php">Back to login</a></p>
</div>
</body>
</html>
