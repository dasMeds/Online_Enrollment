<?php
require_once __DIR__ . '/includes/conn.php';
require_once __DIR__ . '/logcheck.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: subjectreg.php');
	exit;
}
app_verify_csrf();
$subjectId = app_int_input('subject_id');
if ($subjectId < 1) {
	header('Location: subjectreg.php?delete_error=1');
	exit;
}

try {
	$delete = $conn->prepare('DELETE FROM subjecttbl WHERE ID = ?');
	$delete->bind_param('i', $subjectId);
	$delete->execute();
	$_SESSION['flash_success'] = 'Subject deleted.';
	header('Location: subjectreg.php');
} catch (mysqli_sql_exception $exception) {
	error_log('Subject deletion failed: ' . $exception->getMessage());
	$_SESSION['flash_error'] = 'The subject could not be deleted because it is referenced by an enrollment or could not be found.';
	header('Location: subjectreg.php');
}
exit;
?>
