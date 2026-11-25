<?php
require_once __DIR__ . '/includes/conn.php';
require_once __DIR__ . '/logcheck.php';

/* Tell the student what happened to their payment. */
function payments_notify($conn, $paymentId, $newStatus, $note)
{
	$lookup = $conn->prepare('SELECT t.accountID, p.amount, e.semester, e.school_year FROM enrollment_payments p JOIN student_subject e ON e.ID = p.enrollment_ID JOIN studenttbl t ON t.studnum = e.studentID WHERE p.ID = ? LIMIT 1');
	$lookup->bind_param('i', $paymentId);
	$lookup->execute();
	$info = $lookup->get_result()->fetch_assoc();
	if (!$info) { return; }
	if ($newStatus === 'verified') {
		app_notify($conn, (int)$info['accountID'], 'payment', 'Payment verified', 'Your payment of \u20b1' . number_format((float)$info['amount'], 2) . ' for ' . $info['semester'] . ' ' . $info['school_year'] . ' was verified.', 'assessment.php');
	} elseif ($newStatus === 'rejected') {
		app_notify($conn, (int)$info['accountID'], 'payment', 'Payment rejected', 'Your payment of \u20b1' . number_format((float)$info['amount'], 2) . ' for ' . $info['semester'] . ' ' . $info['school_year'] . ' was rejected.' . ($note !== '' ? ' Reason: ' . $note : ''), 'assessment.php');
	}
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_payment_status'])) {
	app_verify_csrf();
	$paymentId = app_int_input('payment_id');
	$newStatus = app_input('new_status');
	$note = trim(app_input('note'));
	if ($paymentId < 1) {
		$error = 'Choose a valid payment.';
	} elseif (!in_array($newStatus, array('verified', 'rejected', 'pending'), true)) {
		$error = 'Choose a valid status.';
	} elseif ($newStatus === 'rejected' && $note === '') {
		$error = 'A reason is required when rejecting a payment.';
	} else {
		try {
			$noteValue = $note === '' ? null : $note;
			$update = $conn->prepare('UPDATE enrollment_payments SET status = ?, note = ? WHERE ID = ?');
			$update->bind_param('ssi', $newStatus, $noteValue, $paymentId);
			$update->execute();
			app_audit($conn, 'payment_' . $newStatus, 'payment', (string)$paymentId, 'Payment #' . $paymentId . ' set to ' . $newStatus . ($noteValue !== null ? '. Note: ' . $noteValue : '') . '.');
			payments_notify($conn, $paymentId, $newStatus, $note);
			$success = $newStatus === 'verified' ? 'Payment verified. It now counts toward the student balance.' : ($newStatus === 'rejected' ? 'Payment rejected.' : 'Payment marked as pending.');
		} catch (mysqli_sql_exception $exception) {
			$error = 'The payment could not be updated. The payments table may not be installed yet — import database/migrate.sql.';
		}
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
	app_verify_csrf();
	$enrollmentId = app_int_input('enrollment_id');
	$amount = app_input('amount');
	$method = app_input('method');
	$reference = trim(app_input('reference'));
	$paidAt = trim(app_input('paid_at'));
	if ($enrollmentId < 1) {
		$error = 'Choose an enrollment to record the payment against.';
	} elseif (!is_numeric($amount) || (float)$amount <= 0) {
		$error = 'Enter a payment amount greater than zero.';
	} elseif (!in_array($method, array('cash', 'gcash', 'bank', 'maya', 'other'), true)) {
		$error = 'Choose a valid payment method.';
	} elseif ($paidAt !== '' && !app_valid_date($paidAt)) {
		$error = 'Enter a valid payment date.';
	} elseif (strlen($reference) > 100) {
		$error = 'The payment reference is too long.';
	} else {
		try {
			$lookup = $conn->prepare('SELECT ID FROM student_subject WHERE ID = ?');
			$lookup->bind_param('i', $enrollmentId);
			$lookup->execute();
			if (!$lookup->get_result()->fetch_assoc()) {
				$error = 'The enrollment was not found.';
			} else {
				$amountValue = (float)$amount;
				$paidDate = $paidAt === '' ? null : $paidAt;
				$receiptPath = '';
				$status = 'verified';
				$recordedBy = (int)$_SESSION['userID'];
				$insert = $conn->prepare('INSERT INTO enrollment_payments (enrollment_ID, amount, method, reference, paid_at, receipt_path, status, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
				$insert->bind_param('idsssssi', $enrollmentId, $amountValue, $method, $reference, $paidDate, $receiptPath, $status, $recordedBy);
				$insert->execute();
				app_audit($conn, 'payment_record', 'payment', (string)$conn->insert_id, 'Recorded and verified a ' . $method . ' payment of \u20b1' . number_format($amountValue, 2) . ' for enrollment #' . $enrollmentId . '.');
				$success = 'Cash payment recorded and verified.';
			}
		} catch (mysqli_sql_exception $exception) {
			error_log('Manual payment failed: ' . $exception->getMessage());
			$error = 'The payment could not be recorded. The payments table may not be installed yet — import database/migrate.sql.';
		}
	}
}

$filterStatus = isset($_GET['status']) && in_array($_GET['status'], array('pending', 'verified', 'rejected'), true) ? $_GET['status'] : '';
$filterMethod = isset($_GET['method']) && in_array($_GET['method'], array('cash', 'gcash', 'bank', 'maya', 'other'), true) ? $_GET['method'] : '';
$filterQ = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$payments = array();
$totals = array('pending' => 0.0, 'verified' => 0.0, 'rejected' => 0.0);
try {
	$where = array();
	$types = '';
	$params = array();
	if ($filterStatus !== '') {
		$where[] = 'p.status = ?';
		$types .= 's';
		$params[] = $filterStatus;
	}
	if ($filterMethod !== '') {
		$where[] = 'p.method = ?';
		$types .= 's';
		$params[] = $filterMethod;
	}
	if ($filterQ !== '') {
		$where[] = "(CONCAT_WS(' ', s.fname, s.minitial, s.lname) LIKE ? OR e.studentID LIKE ? OR p.reference LIKE ?)";
		$types .= 'sss';
		$like = '%' . $filterQ . '%';
		$params[] = $like;
		$params[] = $like;
		$params[] = $like;
	}
	$query = 'SELECT p.ID, p.enrollment_ID, p.amount, p.method, p.reference, p.paid_at, p.receipt_path, p.status, p.note, p.created_at, e.studentID, e.semester, e.school_year, s.fname, s.minitial, s.lname, s.course FROM enrollment_payments p JOIN student_subject e ON e.ID = p.enrollment_ID LEFT JOIN studenttbl s ON s.studnum = e.studentID' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY p.created_at DESC, p.ID DESC LIMIT 500';
	$statement = $conn->prepare($query);
	if ($params) {
		app_bind_params($statement, $types, $params);
	}
	$statement->execute();
	$result = $statement->get_result();
	while ($row = $result->fetch_assoc()) {
		$totals[$row['status']] += (float)$row['amount'];
		$payments[] = $row;
	}
} catch (mysqli_sql_exception $exception) {
	$error = $error === '' ? 'Payments are not available yet. Import database/migrate.sql first.' : $error;
}

/* Enrollments an admin can record a manual payment against. */
$openEnrollments = array();
try {
	$result = $conn->query("SELECT e.ID, e.studentID, e.semester, e.school_year, e.total_amount, s.fname, s.minitial, s.lname FROM student_subject e LEFT JOIN studenttbl s ON s.studnum = e.studentID WHERE e.status IN ('pending', 'approved') ORDER BY e.created_at DESC, e.ID DESC LIMIT 200");
	while ($row = $result->fetch_assoc()) {
		$openEnrollments[] = $row;
	}
} catch (mysqli_sql_exception $exception) {
	$openEnrollments = array();
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Payments | John Paul College</title>
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
			<div><p class="eyebrow">Registrar's Office</p><h1><svg width="28" height="28" aria-hidden="true"><use href="../assets/icons.svg#icon-money"/></svg> Payments</h1><p>Verify student payments and record cash payments received at the cashier.</p></div>
		</header>
		<?php if ($error !== '') { ?><p class="form-error" role="alert"><?php echo app_h($error); ?></p><?php } ?>
		<?php if ($success !== '') { ?><p class="form-success" role="status"><?php echo app_h($success); ?></p><?php } ?>
		<section class="enrollment-stats" aria-label="Payment summary">
			<div class="stat-card"><span>Pending verification</span><strong>&#8369;<?php echo number_format($totals['pending'], 2); ?></strong></div>
			<div class="stat-card"><span>Verified collections</span><strong>&#8369;<?php echo number_format($totals['verified'], 2); ?></strong></div>
			<div class="stat-card"><span>Rejected</span><strong>&#8369;<?php echo number_format($totals['rejected'], 2); ?></strong></div>
		</section>
		<h2>Record a payment</h2>
		<form class="cmxform" method="post">
			<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
			<fieldset>
				<legend>Cashier payment (recorded as verified)</legend>
				<div class="field-grid">
					<label for="enrollment_id">Enrollment<select id="enrollment_id" name="enrollment_id" required><option value="">Choose an enrollment</option><?php foreach ($openEnrollments as $row) { $name = trim(($row['fname'] ?: '') . ' ' . ($row['minitial'] ?: '') . ' ' . ($row['lname'] ?: '')); ?><option value="<?php echo (int)$row['ID']; ?>"><?php echo app_h(($name !== '' ? $name : $row['studentID']) . ' — ' . $row['semester'] . ' ' . $row['school_year'] . ' (&#8369;' . number_format((float)$row['total_amount'], 2) . ')'); ?></option><?php } ?></select></label>
					<label for="amount">Amount (&#8369;)<input id="amount" name="amount" type="number" min="0.01" step="0.01" required></label>
					<label for="method">Method<select id="method" name="method" required><option value="cash">Cash</option><option value="gcash">GCash</option><option value="bank">Bank transfer</option><option value="maya">Maya</option><option value="other">Other</option></select></label>
					<label for="reference">Reference / OR number<input id="reference" name="reference" maxlength="100"></label>
					<label for="paid_at">Date paid<input id="paid_at" name="paid_at" type="date"></label>
				</div>
			</fieldset>
			<button type="submit" name="record_payment" value="1">Record payment</button>
		</form>
		<h2>Submitted payments</h2>
		<form class="catalog-filters" method="get">
			<label for="filter-status">Status
				<select id="filter-status" name="status"><option value="">All statuses</option><?php foreach (array('pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected') as $value => $label) { ?><option value="<?php echo app_h($value); ?>" <?php echo $filterStatus === $value ? 'selected' : ''; ?>><?php echo app_h($label); ?></option><?php } ?></select>
			</label>
			<label for="filter-method">Method
				<select id="filter-method" name="method"><option value="">All methods</option><?php foreach (array('cash' => 'Cash', 'gcash' => 'GCash', 'bank' => 'Bank transfer', 'maya' => 'Maya', 'other' => 'Other') as $value => $label) { ?><option value="<?php echo app_h($value); ?>" <?php echo $filterMethod === $value ? 'selected' : ''; ?>><?php echo app_h($label); ?></option><?php } ?></select>
			</label>
			<label for="filter-q">Search student, number, or reference
				<input type="search" id="filter-q" name="q" value="<?php echo app_h($filterQ); ?>" placeholder="Start typing to filter">
			</label>
			<button type="submit">Apply filters</button>
			<a class="button" href="payments.php">Clear</a>
		</form>
		<div class="table-wrap">
			<table>
				<thead><tr><th>Submitted</th><th>Student</th><th>Term</th><th>Amount</th><th>Method</th><th>Reference</th><th>Paid on</th><th>Status</th><th>Note</th><th>Receipt</th><th>Action</th></tr></thead>
				<tbody>
				<?php if (!$payments) { ?><tr><td colspan="11" class="empty-state"><img src="../assets/empty-state.svg" width="160" height="90" alt=""><br>No payments match these filters.</td></tr><?php } ?>
				<?php foreach ($payments as $payment) {
					$studentName = trim(($payment['fname'] ?: '') . ' ' . ($payment['minitial'] ?: '') . ' ' . ($payment['lname'] ?: ''));
				?>
					<tr>
						<td><?php echo app_h($payment['created_at'] ? date('M j, Y', strtotime($payment['created_at'])) : '—'); ?></td>
						<td><?php echo app_h($studentName !== '' ? $studentName : $payment['studentID']); ?></td>
						<td><?php echo app_h($payment['semester'] . ' ' . $payment['school_year']); ?></td>
						<td class="money"><?php echo number_format((float)$payment['amount'], 2); ?></td>
						<td><?php echo app_h(ucfirst($payment['method'])); ?></td>
						<td><?php echo app_h($payment['reference'] !== '' ? $payment['reference'] : '—'); ?></td>
						<td><?php echo app_h($payment['paid_at'] ? date('M j, Y', strtotime($payment['paid_at'])) : '—'); ?></td>
						<td><span class="status-badge <?php echo app_h(app_status_class($payment['status'])); ?>"><?php echo app_h(ucfirst($payment['status'])); ?></span></td>
						<td class="review-note"><?php echo app_h($payment['note'] !== '' && $payment['note'] !== null ? $payment['note'] : '—'); ?></td>
						<td><?php if ($payment['receipt_path'] !== '') { ?><a href="../uploads/payments/<?php echo app_h($payment['receipt_path']); ?>" target="_blank" rel="noopener">View</a><?php } else { ?>—<?php } ?></td>
						<td>
							<form method="post" class="inline-form">
								<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
								<input type="hidden" name="payment_id" value="<?php echo (int)$payment['ID']; ?>">
								<input type="hidden" name="new_status" value="verified">
								<button type="submit" name="set_payment_status" value="1">Verify</button>
							</form>
							<form method="post" class="inline-form" onsubmit="return confirm('Reject this payment? The student will see your reason.');">
								<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
								<input type="hidden" name="payment_id" value="<?php echo (int)$payment['ID']; ?>">
								<input type="hidden" name="new_status" value="rejected">
								<input type="text" name="note" maxlength="500" placeholder="Reason for rejection (required)" aria-label="Reason for rejection" required>
								<button type="submit" name="set_payment_status" value="1">Reject</button>
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
