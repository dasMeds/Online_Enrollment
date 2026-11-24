<?php
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(array(
	'httponly' => true,
	'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
	'samesite' => 'Lax'
));
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
	if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
		http_response_code(400);
		exit('Invalid request token. Please reload and try again.');
	}
	$_SESSION = array();
	if (ini_get('session.use_cookies')) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
	}
	session_destroy();
	header('Location: index.php');
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
	header('Allow: GET, POST');
	http_response_code(405);
	exit('Method not allowed.');
}

if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Log Out | Online Enrollment</title></head>
<body>
<main>
	<h1>Log out?</h1>
	<form method="post">
		<input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
		<button type="submit">Log out</button>
		<a href="index.php">Cancel</a>
	</form>
</main>
</body>
</html>
