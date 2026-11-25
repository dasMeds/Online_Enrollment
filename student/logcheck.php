<?php
require_once __DIR__ . '/includes/conn.php';
header('Cache-Control: no-store, private');
/* Idle timeout: 30 minutes of inactivity logs the user out. */
if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) > 1800) {
	$_SESSION = array();
	if (ini_get('session.use_cookies')) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
	}
	session_destroy();
	header('Location: ../index.php?timeout=1');
	exit;
}
$_SESSION['last_activity'] = time();
header('Pragma: no-cache');
if (empty($_SESSION['logcheck']) || empty($_SESSION['type']) || $_SESSION['type'] !== 'student' || empty($_SESSION['userID'])) {
	header('Location: ../index.php');
	exit;
}
/* Forced password change: accounts created by an admin (or approved from
   an application) must set their own password before using the site. */
if (!empty($_SESSION['must_change']) && basename((string)$_SERVER['SCRIPT_NAME']) !== 'changepass.php') {
	header('Location: changepass.php?forced=1');
	exit;
}
?>