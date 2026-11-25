<?php
require_once __DIR__ . '/includes/conn.php';
require_once __DIR__ . '/logcheck.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_document_status'])) {
	app_verify_csrf();
	$documentId = app_int_input('document_id');
	$newStatus = app_input('new_status');
	$note = trim(app_input('note'));
	if ($documentId < 1) {
		$error = 'Choose a valid document.';
	} elseif (!in_array($newStatus, array('verified', 'rejected', 'pending'), true)) {
		$error = 'Choose a valid status.';
	} elseif ($newStatus === 'rejected' && $note === '') {
		$error = 'A reason is required when rejecting a document.';
	} else {
		try {
			$noteValue = $note === '' ? null : $note;
			$update = $conn->prepare('UPDATE student_documents SET status = ?, note = ? WHERE ID = ?');
			$update->bind_param('ssi', $newStatus, $noteValue, $documentId);
			$update->execute();
			$info = $conn->prepare('SELECT accountID, doc_type FROM student_documents WHERE ID = ? LIMIT 1');
			$info->bind_param('i', $documentId);
			$info->execute();
			$docInfo = $info->get_result()->fetch_assoc();
			app_audit($conn, 'document_' . $newStatus, 'document', (string)$documentId, 'Document #' . $documentId . ' set to ' . $newStatus . ($noteValue !== null ? '. Note: ' . $noteValue : '') . '.');
			if ($docInfo) {
				$typeLabel = isset($docTypes[$docInfo['doc_type']]) ? $docTypes[$docInfo['doc_type']] : $docInfo['doc_type'];
				if ($newStatus === 'verified') {
					app_notify($conn, (int)$docInfo['accountID'], 'document', 'Document verified', 'Your ' . $typeLabel . ' was verified.', 'documents.php');
				} elseif ($newStatus === 'rejected') {
					app_notify($conn, (int)$docInfo['accountID'], 'document', 'Document rejected', 'Your ' . $typeLabel . ' was rejected.' . ($noteValue !== null ? ' Reason: ' . $noteValue : '') . ' You can upload a corrected copy.', 'documents.php');
				}
			}
			$success = $newStatus === 'verified' ? 'Document verified.' : ($newStatus === 'rejected' ? 'Document rejected. The student can upload a corrected copy.' : 'Document marked as pending.');
		} catch (mysqli_sql_exception $exception) {
			$error = 'The document could not be updated. The documents table may not be installed yet — import database/migrate.sql.';
		}
	}
}

$filterStatus = isset($_GET['status']) && in_array($_GET['status'], array('pending', 'verified', 'rejected'), true) ? $_GET['status'] : '';
$filterType = isset($_GET['type']) && array_key_exists($_GET['type'], $docTypes) ? $_GET['type'] : '';
$filterQ = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$documents = array();
$counts = array('pending' => 0, 'verified' => 0, 'rejected' => 0);
try {
	$where = array();
	$types = '';
	$params = array();
	if ($filterStatus !== '') {
		$where[] = 'd.status = ?';
		$types .= 's';
		$params[] = $filterStatus;
	}
	if ($filterType !== '') {
		$where[] = 'd.doc_type = ?';
		$types .= 's';
		$params[] = $filterType;
	}
	if ($filterQ !== '') {
		$where[] = "(CONCAT_WS(' ', s.fname, s.minitial, s.lname) LIKE ? OR d.studnum LIKE ?)";
		$types .= 'ss';
		$like = '%' . $filterQ . '%';
		$params[] = $like;
		$params[] = $like;
	}
	$query = 'SELECT d.ID, d.accountID, d.studnum, d.doc_type, d.file_path, d.original_name, d.size_bytes, d.status, d.note, d.uploaded_at, s.fname, s.minitial, s.lname, s.course FROM student_documents d LEFT JOIN studenttbl s ON s.accountID = d.accountID' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY d.uploaded_at DESC, d.ID DESC LIMIT 500';
	$statement = $conn->prepare($query);
	if ($params) {
		app_bind_params($statement, $types, $params);
	}
	$statement->execute();
	$result = $statement->get_result();
	while ($row = $result->fetch_assoc()) {
		$counts[$row['status']]++;
		$documents[] = $row;
	}
} catch (mysqli_sql_exception $exception) {
	$error = $error === '' ? 'Documents are not available yet. Import database/migrate.sql first.' : $error;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Student Documents | John Paul College</title>
<link href="style.css" rel="stylesheet" type="text/css">
<link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
</head>
<body>
<div class="header"></div>
<nav class="container" aria-label="Administrator navigation"><?php include __DIR__ . '/includes/menu1.php'; ?></nav>
<main id="main">
	<div class="shell wide-shell">
		<header class="school-page-header">
			<img src="../assets/logo.jpg" width="82" height="82" alt="John Paul College">
			<div><p class="eyebrow">Registrar's Office</p><h1><svg width="28" height="28" aria-hidden="true"><use href="../assets/icons.svg#icon-doc"/></svg> Student Documents</h1><p>Verify or reject the documents students upload.</p></div>
		</header>
		<?php if ($error !== '') { ?><p class="form-error" role="alert"><?php echo app_h($error); ?></p><?php } ?>
		<?php if ($success !== '') { ?><p class="form-success" role="status"><?php echo app_h($success); ?></p><?php } ?>
		<section class="enrollment-stats" aria-label="Document summary">
			<div class="stat-card"><span>Pending review</span><strong><?php echo $counts['pending']; ?></strong></div>
			<div class="stat-card"><span>Verified</span><strong><?php echo $counts['verified']; ?></strong></div>
			<div class="stat-card"><span>Rejected</span><strong><?php echo $counts['rejected']; ?></strong></div>
		</section>
		<form class="catalog-filters" method="get">
			<label for="filter-status">Status
				<select id="filter-status" name="status"><option value="">All statuses</option><?php foreach (array('pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected') as $value => $label) { ?><option value="<?php echo app_h($value); ?>" <?php echo $filterStatus === $value ? 'selected' : ''; ?>><?php echo app_h($label); ?></option><?php } ?></select>
			</label>
			<label for="filter-type">Document type
				<select id="filter-type" name="type"><option value="">All types</option><?php foreach ($docTypes as $value => $label) { ?><option value="<?php echo app_h($value); ?>" <?php echo $filterType === $value ? 'selected' : ''; ?>><?php echo app_h($label); ?></option><?php } ?></select>
			</label>
			<label for="filter-q">Search student or number
				<input type="search" id="filter-q" name="q" value="<?php echo app_h($filterQ); ?>" placeholder="Start typing to filter">
			</label>
			<button type="submit">Apply filters</button>
			<a class="button" href="documents.php">Clear</a>
		</form>
		<div class="table-wrap">
			<table>
				<thead><tr><th>Uploaded</th><th>Student</th><th>Student number</th><th>Course</th><th>Document type</th><th>File</th><th>Status</th><th>Note</th><th>Action</th></tr></thead>
				<tbody>
				<?php if (!$documents) { ?><tr><td colspan="9" class="empty-state"><img src="../assets/empty-state.svg" width="160" height="90" alt=""><br>No documents match these filters.</td></tr><?php } ?>
				<?php foreach ($documents as $doc) {
					$studentName = trim(($doc['fname'] ?: '') . ' ' . ($doc['minitial'] ?: '') . ' ' . ($doc['lname'] ?: ''));
				?>
					<tr>
						<td><?php echo app_h($doc['uploaded_at'] ? date('M j, Y', strtotime($doc['uploaded_at'])) : '—'); ?></td>
						<td><?php echo app_h($studentName !== '' ? $studentName : 'Student profile unavailable'); ?></td>
						<td><?php echo app_h($doc['studnum'] !== '' ? $doc['studnum'] : '—'); ?></td>
						<td><?php echo app_h(isset($doc['course']) ? strtoupper($doc['course']) : ''); ?></td>
						<td><?php echo app_h($docTypes[$doc['doc_type']] ?? ucfirst($doc['doc_type'])); ?></td>
						<td><a href="../uploads/documents/<?php echo app_h($doc['file_path']); ?>" target="_blank" rel="noopener"><?php echo app_h($doc['original_name'] !== '' ? $doc['original_name'] : $doc['file_path']); ?></a></td>
						<td><span class="status-badge <?php echo app_h(app_status_class($doc['status'])); ?>"><?php echo app_h(ucfirst($doc['status'])); ?></span></td>
						<td class="review-note"><?php echo app_h($doc['note'] !== '' && $doc['note'] !== null ? $doc['note'] : '—'); ?></td>
						<td>
							<form method="post" class="inline-form">
								<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
								<input type="hidden" name="document_id" value="<?php echo (int)$doc['ID']; ?>">
								<input type="hidden" name="new_status" value="verified">
								<button type="submit" name="set_document_status" value="1">Verify</button>
							</form>
							<form method="post" class="inline-form" onsubmit="return confirm('Reject this document? The student will see your reason and can upload a corrected copy.');">
								<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
								<input type="hidden" name="document_id" value="<?php echo (int)$doc['ID']; ?>">
								<input type="hidden" name="new_status" value="rejected">
								<input type="text" name="note" maxlength="500" placeholder="Reason for rejection (required)" aria-label="Reason for rejection" required>
								<button type="submit" name="set_document_status" value="1">Reject</button>
							</form>
						</td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
	</div>
</main>
</body>
</html>
