<?php
require_once __DIR__ . '/includes/conn.php';
require_once __DIR__ . '/logcheck.php';

$accountId = (int)$_SESSION['userID'];
$statement = $conn->prepare('SELECT s.studnum, s.fname, s.minitial, s.lname, s.course FROM studenttbl s WHERE s.accountID = ?');
$statement->bind_param('i', $accountId);
$statement->execute();
$student = $statement->get_result()->fetch_assoc();
if (!$student) {
	http_response_code(404);
	exit('Student profile was not found. Please contact the administrator.');
}

$docTypes = array(
	'id' => 'Valid ID',
	'psa_birth' => 'PSA Birth Certificate',
	'tor' => 'Transcript of Records (TOR)',
	'form137' => 'Form 137 / Report Card',
	'photo' => '2x2 Photo',
	'other' => 'Other document'
);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
	app_verify_csrf();
	$docType = app_input('doc_type');
	if (!array_key_exists($docType, $docTypes)) {
		$error = 'Choose a valid document type.';
	} elseif (!isset($_FILES['document']) || $_FILES['document']['error'] === UPLOAD_ERR_NO_FILE) {
		$error = 'Choose a file to upload.';
	} else {
		$upload = app_upload_file($_FILES['document'], array('jpg', 'jpeg', 'png', 'pdf'), array('image/jpeg', 'image/png', 'application/pdf'), 5 * 1024 * 1024, __DIR__ . '/../uploads/documents', 'doc');
		if (!$upload['ok']) {
			$error = $upload['error'];
		} else {
			try {
				$originalName = (string)$_FILES['document']['name'];
				$mime = isset($_FILES['document']['type']) ? substr((string)$_FILES['document']['type'], 0, 100) : '';
				$size = (int)$_FILES['document']['size'];
				$status = 'pending';
				$insert = $conn->prepare('INSERT INTO student_documents (accountID, studnum, doc_type, file_path, original_name, mime, size_bytes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
				$insert->bind_param('isssssis', $accountId, $student['studnum'], $docType, $upload['path'], $originalName, $mime, $size, $status);
				$insert->execute();
				$success = 'Your document was uploaded and is pending verification.';
				unset($_SESSION['csrf_token']);
			} catch (mysqli_sql_exception $exception) {
				error_log('Document upload failed: ' . $exception->getMessage());
				$error = 'Your document could not be saved. The documents table may not be installed yet — ask the administrator to import database/migrate.sql.';
			}
		}
	}
}

$documents = array();
try {
	$statement = $conn->prepare('SELECT ID, doc_type, file_path, original_name, size_bytes, status, note, uploaded_at FROM student_documents WHERE accountID = ? ORDER BY uploaded_at DESC, ID DESC');
	$statement->bind_param('i', $accountId);
	$statement->execute();
	$result = $statement->get_result();
	while ($row = $result->fetch_assoc()) {
		$documents[] = $row;
	}
} catch (mysqli_sql_exception $exception) {
	$documents = array();
	$error = $error === '' ? 'Documents are not available yet. Ask the administrator to import database/migrate.sql.' : $error;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Documents | John Paul College</title>
<link href="style.css" rel="stylesheet" type="text/css">
<link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
</head>
<body>
<div class="header"></div>
<nav class="container" aria-label="Student navigation"><?php include __DIR__ . '/includes/menu.php'; ?></nav>
<main id="main">
	<div class="shell wide-shell">
		<header class="school-page-header">
			<img src="../assets/logo.jpg" width="82" height="82" alt="John Paul College">
			<div><p class="eyebrow">Student Records · <?php echo app_h(strtoupper($student['course'])); ?></p><h1><svg width="28" height="28" aria-hidden="true"><use href="../assets/icons.svg#icon-doc"/></svg> My Documents</h1><p>Upload the documents the registrar asked for. Only JPG, PNG, or PDF files up to 5 MB are accepted.</p></div>
		</header>
		<?php if ($error !== '') { ?><p class="form-error" role="alert"><?php echo app_h($error); ?></p><?php } ?>
		<?php if ($success !== '') { ?><p class="form-success" role="status"><?php echo app_h($success); ?></p><?php } ?>
		<form class="cmxform" method="post" enctype="multipart/form-data">
			<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
			<fieldset>
				<legend>Upload a document</legend>
				<div class="field-grid">
					<label for="doc_type">Document type<select id="doc_type" name="doc_type" required><?php foreach ($docTypes as $value => $label) { ?><option value="<?php echo app_h($value); ?>"><?php echo app_h($label); ?></option><?php } ?></select></label>
					<label for="document">File<input id="document" name="document" type="file" accept=".jpg,.jpeg,.png,.pdf" required></label>
				</div>
			</fieldset>
			<button type="submit" name="upload_document" value="1">Upload document</button>
		</form>
		<h2>Uploaded documents</h2>
		<div class="table-wrap">
			<table>
				<thead><tr><th>Uploaded</th><th>Document type</th><th>File</th><th>Size</th><th>Status</th><th>Registrar note</th></tr></thead>
				<tbody>
				<?php if (!$documents) { ?><tr><td colspan="6" class="empty-state"><img src="../assets/empty-state.svg" width="160" height="90" alt=""><br>No documents uploaded yet.</td></tr><?php } ?>
				<?php foreach ($documents as $doc) { ?>
					<tr>
						<td><?php echo app_h($doc['uploaded_at'] ? date('M j, Y', strtotime($doc['uploaded_at'])) : '—'); ?></td>
						<td><?php echo app_h($docTypes[$doc['doc_type']] ?? ucfirst($doc['doc_type'])); ?></td>
						<td><a href="../uploads/documents/<?php echo app_h($doc['file_path']); ?>" target="_blank" rel="noopener"><?php echo app_h($doc['original_name'] !== '' ? $doc['original_name'] : $doc['file_path']); ?></a></td>
						<td><?php echo number_format((int)$doc['size_bytes'] / 1024, 1); ?> KB</td>
						<td><span class="status-badge <?php echo app_h(app_status_class($doc['status'])); ?>"><?php echo app_h(ucfirst($doc['status'])); ?></span></td>
						<td class="review-note"><?php echo app_h($doc['note'] !== '' && $doc['note'] !== null ? $doc['note'] : '—'); ?></td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
	</div>
</main>
<footer id="footer"><div class="shell"><?php include __DIR__ . '/includes/menu2.php'; ?></div></footer>
</body>
</html>
