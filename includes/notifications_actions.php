<?php
/* Notification actions (mark read / mark all read). Include BEFORE any
   output, because the handlers redirect. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
	app_verify_csrf();
	$mark = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE accountID = ? AND is_read = 0');
	$mark->bind_param('i', $_SESSION['userID']);
	$mark->execute();
	$_SESSION['flash_success'] = 'All notifications marked as read.';
	header('Location: notifications.php');
	exit;
}
if (isset($_GET['read']) && ctype_digit((string)$_GET['read'])) {
	$readId = (int)$_GET['read'];
	$mark = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE ID = ? AND accountID = ?');
	$mark->bind_param('ii', $readId, $_SESSION['userID']);
	$mark->execute();
	$link = $conn->prepare('SELECT link FROM notifications WHERE ID = ? AND accountID = ? LIMIT 1');
	$link->bind_param('ii', $readId, $_SESSION['userID']);
	$link->execute();
	$row = $link->get_result()->fetch_assoc();
	if ($row && $row['link'] !== '') {
		header('Location: ' . $row['link']);
		exit;
	}
	header('Location: notifications.php');
	exit;
}
