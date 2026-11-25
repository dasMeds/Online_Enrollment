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

$error = '';
$success = '';
$requestedId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* The student's enrollments, newest first; ?id= picks one, defaulting to
   the latest. A student can only ever see their own enrollments. */
try {
	$statement = $conn->prepare('SELECT ID, subjects, totalunits, approved, status, review_note, tuition_amount, entrance_fee, internet_fee, misc_fee, lab_fee, tutorial_fee, total_amount, semester, school_year, created_at FROM student_subject WHERE studentID = ? ORDER BY created_at DESC, ID DESC');
	$statement->bind_param('s', $student['studnum']);
	$statement->execute();
	$all = $statement->get_result();
} catch (mysqli_sql_exception $exception) {
	$statement = $conn->prepare('SELECT ID, subjects, totalunits, approved, semester, school_year, created_at FROM student_subject WHERE studentID = ? ORDER BY created_at DESC, ID DESC');
	$statement->bind_param('s', $student['studnum']);
	$statement->execute();
	$all = $statement->get_result();
}
$enrollments = array();
while ($row = $all->fetch_assoc()) {
	$row['status'] = isset($row['status']) ? strtolower(trim((string)$row['status'])) : (in_array(strtolower(trim((string)$row['approved'])), array('1', 'yes', 'approved', 'true'), true) ? 'approved' : 'pending');
	foreach (array('tuition_amount', 'entrance_fee', 'internet_fee', 'misc_fee', 'lab_fee', 'tutorial_fee', 'total_amount') as $field) {
		$row[$field] = isset($row[$field]) ? (float)$row[$field] : 0.0;
	}
	$enrollments[] = $row;
}
$enrollment = null;
foreach ($enrollments as $row) {
	if ($requestedId > 0 && (int)$row['ID'] === $requestedId) {
		$enrollment = $row;
		break;
	}
}
if (!$enrollment && $enrollments) {
	$enrollment = $enrollments[0];
}

/* Submit a payment (proof of payment) against the shown enrollment. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment']) && $enrollment) {
	app_verify_csrf();
	$amount = app_input('amount');
	$method = app_input('method');
	$reference = trim(app_input('reference'));
	$paidAt = trim(app_input('paid_at'));
	if (!is_numeric($amount) || (float)$amount <= 0) {
		$error = 'Enter a payment amount greater than zero.';
	} elseif (!in_array($method, array('cash', 'gcash', 'bank', 'maya', 'other'), true)) {
		$error = 'Choose a valid payment method.';
	} elseif ($paidAt !== '' && !app_valid_date($paidAt)) {
		$error = 'Enter a valid payment date.';
	} elseif (strlen($reference) > 100) {
		$error = 'The payment reference is too long.';
	} else {
		$receiptPath = '';
		if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] !== UPLOAD_ERR_NO_FILE) {
			$upload = app_upload_file($_FILES['receipt'], array('jpg', 'jpeg', 'png', 'pdf'), array('image/jpeg', 'image/png', 'application/pdf'), 5 * 1024 * 1024, __DIR__ . '/../uploads/payments', 'receipt');
			if (!$upload['ok']) {
				$error = $upload['error'];
			} else {
				$receiptPath = $upload['path'];
			}
		}
		if ($error === '') {
			try {
				$enrollmentId = (int)$enrollment['ID'];
				$amountValue = (float)$amount;
				$paidDate = $paidAt === '' ? null : $paidAt;
				$status = 'pending';
				$insert = $conn->prepare('INSERT INTO enrollment_payments (enrollment_ID, amount, method, reference, paid_at, receipt_path, status, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
				$insert->bind_param('idsssssi', $enrollmentId, $amountValue, $method, $reference, $paidDate, $receiptPath, $status, $accountId);
				$insert->execute();
				$success = 'Your payment was submitted for verification. The registrar will confirm it here.';
				unset($_SESSION['csrf_token']);
			} catch (mysqli_sql_exception $exception) {
				error_log('Payment submission failed: ' . $exception->getMessage());
				$error = 'Your payment could not be submitted. The payments table may not be installed yet — ask the administrator to import database/migrate.sql.';
			}
		}
	}
}

$payments = array();
$verifiedPaid = 0.0;
if ($enrollment) {
	try {
		$statement = $conn->prepare('SELECT ID, amount, method, reference, paid_at, receipt_path, status, note, created_at FROM enrollment_payments WHERE enrollment_ID = ? ORDER BY created_at DESC, ID DESC');
		$enrollmentId = (int)$enrollment['ID'];
		$statement->bind_param('i', $enrollmentId);
		$statement->execute();
		$result = $statement->get_result();
		while ($row = $result->fetch_assoc()) {
			$payments[] = $row;
			if ($row['status'] === 'verified') {
				$verifiedPaid += (float)$row['amount'];
			}
		}
	} catch (mysqli_sql_exception $exception) {
		$payments = array();
	}
	/* If the stored assessment is empty (a pre-v1 enrollment), show the
	   standard formula so the page is still useful. */
	if ($enrollment['total_amount'] <= 0) {
		$fees = app_fee_settings($conn);
		$labCount = 0;
		$tutorialCount = 0;
		$statement = $conn->prepare('SELECT COALESCE(SUM(s.islab), 0) AS labs, COALESCE(SUM(s.istutorial), 0) AS tutorials FROM student_subject_items i JOIN subjecttbl s ON s.ID = i.subject_ID WHERE i.enrollment_ID = ?');
		$enrollmentId = (int)$enrollment['ID'];
		$statement->bind_param('i', $enrollmentId);
		$statement->execute();
		$counts = $statement->get_result()->fetch_assoc();
		if ($counts) {
			$labCount = (int)$counts['labs'];
			$tutorialCount = (int)$counts['tutorials'];
		}
		$calc = app_assessment_calc($fees, (int)$enrollment['totalunits'], $labCount, $tutorialCount);
		$enrollment['tuition_amount'] = $calc['tuition'];
		$enrollment['entrance_fee'] = $calc['entrance'];
		$enrollment['internet_fee'] = $calc['internet'];
		$enrollment['misc_fee'] = $calc['misc'];
		$enrollment['lab_fee'] = $calc['lab'];
		$enrollment['tutorial_fee'] = $calc['tutorial'];
		$enrollment['total_amount'] = $calc['total'];
	}
}
$balance = $enrollment ? max(0.0, $enrollment['total_amount'] - $verifiedPaid) : 0.0;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Assessment &amp; Payments | John Paul College</title>
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
			<div><p class="eyebrow">Student Records · <?php echo app_h(strtoupper($student['course'])); ?></p><h1><svg width="28" height="28" aria-hidden="true"><use href="../assets/icons.svg#icon-money"/></svg> Assessment &amp; Payments</h1><p>Your fee breakdown, verified payments, and remaining balance.</p></div>
		</header>
		<?php if ($error !== '') { ?><p class="form-error" role="alert"><?php echo app_h($error); ?></p><?php } ?>
		<?php if ($success !== '') { ?><p class="form-success" role="status"><?php echo app_h($success); ?></p><?php } ?>
		<?php if (!$enrollment) { ?>
			<p class="empty-state"><img src="../assets/empty-state.svg" width="160" height="90" alt=""><br><strong>No enrollment yet.</strong><br>Submit an enrollment form first, and your assessment will appear here.<br><a class="button" href="enroll.php">Start enrollment</a></p>
		<?php } else { ?>
		<form class="catalog-filters" method="get">
			<label for="enrollment-select">Enrollment
				<select id="enrollment-select" name="id"><?php foreach ($enrollments as $row) { ?><option value="<?php echo (int)$row['ID']; ?>" <?php echo (int)$row['ID'] === (int)$enrollment['ID'] ? 'selected' : ''; ?>><?php echo app_h($row['semester'] . ' ' . $row['school_year'] . ' — ' . app_status_label($row['status'])); ?></option><?php } ?></select>
			</label>
			<button type="submit">Show assessment</button>
		</form>
		<section class="enrollment-stats" aria-label="Assessment summary">
			<div class="stat-card"><span>Status</span><strong><?php echo app_h(app_status_label($enrollment['status'])); ?></strong></div>
			<div class="stat-card"><span>Total amount</span><strong>&#8369;<?php echo number_format($enrollment['total_amount'], 2); ?></strong></div>
			<div class="stat-card"><span>Verified paid</span><strong>&#8369;<?php echo number_format($verifiedPaid, 2); ?></strong></div>
			<div class="stat-card"><span>Balance</span><strong>&#8369;<?php echo number_format($balance, 2); ?></strong></div>
		</section>
		<h2>Fee breakdown</h2>
		<div class="table-wrap">
			<table>
				<thead><tr><th>Item</th><th class="money">Amount</th></tr></thead>
				<tbody>
					<tr><td>Tuition (<?php echo (int)$enrollment['totalunits']; ?> units)</td><td class="money"><?php echo number_format($enrollment['tuition_amount'], 2); ?></td></tr>
					<tr><td>Entrance fee</td><td class="money"><?php echo number_format($enrollment['entrance_fee'], 2); ?></td></tr>
					<tr><td>Internet</td><td class="money"><?php echo number_format($enrollment['internet_fee'], 2); ?></td></tr>
					<tr><td>Miscellaneous</td><td class="money"><?php echo number_format($enrollment['misc_fee'], 2); ?></td></tr>
					<tr><td>Lab fees</td><td class="money"><?php echo number_format($enrollment['lab_fee'], 2); ?></td></tr>
					<tr><td>Tutorial fees</td><td class="money"><?php echo number_format($enrollment['tutorial_fee'], 2); ?></td></tr>
					<tr><th>Total</th><th class="money"><?php echo number_format($enrollment['total_amount'], 2); ?></th></tr>
				</tbody>
			</table>
		</div>
		<h2>Payments</h2>
		<div class="table-wrap">
			<table>
				<thead><tr><th>Submitted</th><th>Amount</th><th>Method</th><th>Reference</th><th>Paid on</th><th>Status</th><th>Registrar note</th><th>Receipt</th></tr></thead>
				<tbody>
				<?php if (!$payments) { ?><tr><td colspan="8" class="empty-state">No payments submitted yet for this enrollment.</td></tr><?php } ?>
				<?php foreach ($payments as $payment) { ?>
					<tr>
						<td><?php echo app_h($payment['created_at'] ? date('M j, Y', strtotime($payment['created_at'])) : '—'); ?></td>
						<td class="money"><?php echo number_format((float)$payment['amount'], 2); ?></td>
						<td><?php echo app_h(ucfirst($payment['method'])); ?></td>
						<td><?php echo app_h($payment['reference'] !== '' ? $payment['reference'] : '—'); ?></td>
						<td><?php echo app_h($payment['paid_at'] ? date('M j, Y', strtotime($payment['paid_at'])) : '—'); ?></td>
						<td><span class="status-badge <?php echo app_h(app_status_class($payment['status'])); ?>"><?php echo app_h(ucfirst($payment['status'])); ?></span></td>
						<td class="review-note"><?php echo app_h($payment['note'] !== '' && $payment['note'] !== null ? $payment['note'] : '—'); ?></td>
						<td><?php if ($payment['receipt_path'] !== '') { ?><a href="../uploads/payments/<?php echo app_h($payment['receipt_path']); ?>" target="_blank" rel="noopener">View receipt</a><?php } else { ?>—<?php } ?></td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
		<h2>Submit a payment</h2>
		<p>Pay at the cashier or through GCash / bank / Maya, then submit the details here with your proof of payment (JPG, PNG, or PDF, max 5 MB). It counts toward your balance only after the registrar verifies it.</p>
		<form class="cmxform" method="post" enctype="multipart/form-data">
			<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
			<fieldset>
				<legend>Payment details</legend>
				<div class="field-grid">
					<label for="amount">Amount (&#8369;)<input id="amount" name="amount" type="number" min="0.01" step="0.01" required></label>
					<label for="method">Method<select id="method" name="method" required><option value="gcash">GCash</option><option value="maya">Maya</option><option value="bank">Bank transfer</option><option value="cash">Cash (paid at the cashier)</option><option value="other">Other</option></select></label>
					<label for="reference">Reference number<input id="reference" name="reference" maxlength="100" placeholder="e.g. GCash reference no."></label>
					<label for="paid_at">Date paid<input id="paid_at" name="paid_at" type="date"></label>
					<label for="receipt">Proof of payment<input id="receipt" name="receipt" type="file" accept=".jpg,.jpeg,.png,.pdf"></label>
				</div>
			</fieldset>
			<button type="submit" name="submit_payment" value="1">Submit payment</button>
		</form>
		<p><a class="button" href="enrollmenthistory.php">Back to enrollment history</a>
		<?php if ($enrollment['status'] === 'approved') { ?><a class="button" href="cor.php?id=<?php echo (int)$enrollment['ID']; ?>">Print Certificate of Registration</a><?php } ?></p>
		<?php } ?>
	</div>
</main>
<footer id="footer"><div class="shell"><?php include __DIR__ . '/includes/menu2.php'; ?></div></footer>
</body>
</html>
