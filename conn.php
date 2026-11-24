<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
	ini_set('session.use_strict_mode', '1');
	session_set_cookie_params(array(
		'httponly' => true,
		'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
		'samesite' => 'Lax'
	));
	session_start();
}

if (!function_exists('app_h')) {
	function app_h($value)
	{
		if (!is_scalar($value)) {
			return '';
		}
		return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	function app_input($name, $default = '')
	{
		return isset($_POST[$name]) && is_string($_POST[$name]) ? $_POST[$name] : $default;
	}

	function app_int_input($name, $default = -1)
	{
		$value = app_input($name);
		return ctype_digit($value) ? (int)$value : $default;
	}

	function app_bind_params($statement, $types, &$values)
	{
		$parameters = array($types);
		foreach ($values as &$value) {
			$parameters[] = &$value;
		}
		unset($value);
		return call_user_func_array(array($statement, 'bind_param'), $parameters);
	}

	function app_csrf_token()
	{
		if (empty($_SESSION['csrf_token'])) {
			$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
		}
		return $_SESSION['csrf_token'];
	}

	function app_verify_csrf()
	{
		$token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
		if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
			http_response_code(400);
			exit('Invalid request token. Please reload the page and try again.');
		}
	}

	function app_valid_date($value)
	{
		$date = DateTime::createFromFormat('!Y-m-d', (string)$value);
		return $date !== false && $date->format('Y-m-d') === (string)$value;
	}

	function app_course_options()
	{
		return array(
			'bsit' => 'Bachelor of Science in Information Technology',
			'bscs' => 'Bachelor of Science in Computer Science',
			'bsba' => 'Bachelor of Science in Business Administration',
			'bsa' => 'Bachelor of Science in Accountancy',
			'bsed' => 'Bachelor of Secondary Education',
			'bsce' => 'Bachelor of Science in Civil Engineering',
			'bsn' => 'Bachelor of Science in Nursing',
			'bshm' => 'Bachelor of Science in Hospitality Management',
			'bspsy' => 'Bachelor of Science in Psychology',
			'bsarch' => 'Bachelor of Science in Architecture'
		);
	}
}

if (!isset($GLOBALS['appExceptionHandlerRegistered'])) {
	$GLOBALS['appExceptionHandlerRegistered'] = true;
	set_exception_handler(function ($exception) {
		error_log('Online Enrollment request failed: ' . $exception->getMessage());
		if (!headers_sent()) {
			http_response_code(500);
		}
		exit('The request could not be completed. Please try again later.');
	});
}

$dbHost = getenv('ONLINE_ENROLL_DB_HOST') ?: 'localhost';
$dbPort = (int)(getenv('ONLINE_ENROLL_DB_PORT') ?: 3306);
$dbName = getenv('ONLINE_ENROLL_DB_NAME') ?: 'online_enroll';
$dbUser = getenv('ONLINE_ENROLL_DB_USER') ?: 'root';
$dbPass = getenv('ONLINE_ENROLL_DB_PASS');
if ($dbPass === false) {
	$dbPass = '';
}

try {
	$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
	$conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $exception) {
	error_log('Online Enrollment database connection failed: ' . $exception->getMessage());
	http_response_code(503);
	exit('The enrollment service is temporarily unavailable. Please try again later.');
}
?>