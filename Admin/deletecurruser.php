<?php
require_once __DIR__ . '/includes/conn.php';
require_once __DIR__ . '/logcheck.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: userreg.php');
	exit;
}
app_verify_csrf();
$studentId = app_int_input('student_id');
if ($studentId > 0) {
	$lookup = $conn->prepare('SELECT accountID, studnum, fname, lname FROM studenttbl WHERE ID = ?');
	$lookup->bind_param('i', $studentId);
	$lookup->execute();
	$student = $lookup->get_result()->fetch_assoc();
	if ($student) {
		/* The delete cascades to the student's enrollments, payments and
		   documents. Log it first — after the delete there is nothing left
		   to describe. */
		app_audit($conn, 'student_delete', 'student', (string)$studentId, 'Deleted student ' . $student['studnum'] . ' (' . $student['lname'] . ', ' . $student['fname'] . '); enrollments, payments and documents cascade-deleted.');
		$delete = $conn->prepare('DELETE FROM usertbl WHERE ID = ? AND user_type = "student"');
		$delete->bind_param('i', $student['accountID']);
		$delete->execute();
		$_SESSION['flash_success'] = 'Student account and associated enrollment records deleted.';
		header('Location: userreg.php');
		exit;
	}
}
$_SESSION['flash_error'] = 'The requested student account was not found or could not be deleted.';
header('Location: userreg.php');
exit;
?>
