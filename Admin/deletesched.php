<?php
require_once __DIR__ . '/includes/conn.php';
require_once __DIR__ . '/logcheck.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: schedreg.php');
	exit;
}
app_verify_csrf();
$scheduleId = app_int_input('schedule_id');
if ($scheduleId < 1) {
	$_SESSION['flash_error'] = 'Choose a valid schedule.';
	header('Location: schedreg.php');
	exit;
}
$delete = $conn->prepare('DELETE FROM sched_tbl WHERE sched_ID = ?');
$delete->bind_param('i', $scheduleId);
$delete->execute();
$_SESSION[$delete->affected_rows ? 'flash_success' : 'flash_error'] = $delete->affected_rows ? 'Schedule deleted.' : 'The schedule was not found.';
header('Location: schedreg.php');
exit;
?>
