<?php
require_once __DIR__ . '/includes/conn.php';
header('Cache-Control: no-store, private');
header('Pragma: no-cache');
if (empty($_SESSION['logcheck']) || empty($_SESSION['type']) || $_SESSION['type'] !== 'student' || empty($_SESSION['userID'])) {
	header('Location: ../index.php');
	exit;
}
?>