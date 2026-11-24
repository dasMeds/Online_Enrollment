<?php
require_once __DIR__ . '/includes/conn.php';
require_once __DIR__ . '/logcheck.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: sectionreg.php');
	exit;
}
app_verify_csrf();
$sectionId = app_int_input('section_id');
if ($sectionId < 1) {
	$_SESSION['flash_error'] = 'Choose a valid section.';
	header('Location: sectionreg.php');
	exit;
}
try {
	$delete = $conn->prepare('DELETE FROM sections_tbl WHERE section_ID = ?');
	$delete->bind_param('i', $sectionId);
	$delete->execute();
	$_SESSION[$delete->affected_rows ? 'flash_success' : 'flash_error'] = $delete->affected_rows ? 'Section and its schedules deleted.' : 'The section was not found.';
} catch (mysqli_sql_exception $exception) {
	error_log('Section deletion failed: ' . $exception->getMessage());
	$_SESSION['flash_error'] = 'The section could not be deleted.';
}
header('Location: sectionreg.php');
exit;
?>
