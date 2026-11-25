<?php
include('logcheck.php');
$flash = isset($_SESSION['flash_success']) ? $_SESSION['flash_success'] : '';
unset($_SESSION['flash_success']);

$latestEnrollment = null;
$latestBalance = 0.0;
$latestPaid = 0.0;
try {
	$statement = $conn->prepare('SELECT ID, status, total_amount, semester, school_year, review_note FROM student_subject s JOIN studenttbl t ON t.studnum = s.studentID WHERE t.accountID = ? ORDER BY s.created_at DESC, s.ID DESC LIMIT 1');
	$accountId = (int)$_SESSION['userID'];
	$statement->bind_param('i', $accountId);
	$statement->execute();
	$latestEnrollment = $statement->get_result()->fetch_assoc();
} catch (mysqli_sql_exception $exception) {
	try {
		$statement = $conn->prepare('SELECT ID, approved, total_amount, semester, school_year FROM student_subject s JOIN studenttbl t ON t.studnum = s.studentID WHERE t.accountID = ? ORDER BY s.created_at DESC, s.ID DESC LIMIT 1');
		$accountId = (int)$_SESSION['userID'];
		$statement->bind_param('i', $accountId);
		$statement->execute();
		$row = $statement->get_result()->fetch_assoc();
		if ($row) {
			$row['status'] = (int)$row['approved'] === 1 ? 'approved' : 'pending';
			$row['review_note'] = '';
			$latestEnrollment = $row;
		}
	} catch (mysqli_sql_exception $inner) {
		$latestEnrollment = null;
	}
}
if ($latestEnrollment) {
	$latestEnrollment['total_amount'] = isset($latestEnrollment['total_amount']) ? (float)$latestEnrollment['total_amount'] : 0.0;
	$latestPaid = app_verified_paid($conn, (int)$latestEnrollment['ID']);
	$latestBalance = max(0.0, $latestEnrollment['total_amount'] - $latestPaid);
}
$enrollmentState = app_enrollment_open($conn);
$announcements = app_published_announcements($conn);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Student Dashboard | John Paul College</title>
<link href="style.css" rel="stylesheet" type="text/css">
</head>

<body>

<div class='header'>
</div>

<nav class='container'>
<?php
include('includes/menu.php');
?>
</nav>

<div id="main">
	<div class="shell">
		<?php if ($flash !== '') { ?><p class="form-success" role="status"><?php echo app_h($flash); ?></p><?php } ?>
		<h1>Student Dashboard</h1>
		<?php if ($enrollmentState['open']) { ?><p class="form-success" role="status">Enrollment is open. <a href="enroll.php">Enroll now</a>.</p><?php } else { ?><p class="enrollment-closed" role="alert"><?php echo app_h($enrollmentState['reason']); ?></p><?php } ?>
		<?php if ($latestEnrollment) { ?>
		<section class="enrollment-stats" aria-label="My latest enrollment">
			<div class="stat-card"><span>Latest enrollment</span><strong><?php echo app_h(app_status_label($latestEnrollment['status'])); ?></strong></div>
			<div class="stat-card"><span>Term</span><strong><?php echo app_h($latestEnrollment['semester'] . ' ' . $latestEnrollment['school_year']); ?></strong></div>
			<div class="stat-card"><span>Total amount</span><strong>&#8369;<?php echo number_format($latestEnrollment['total_amount'], 2); ?></strong></div>
			<div class="stat-card"><span>Balance</span><strong>&#8369;<?php echo number_format($latestBalance, 2); ?></strong></div>
		</section>
		<?php if (!empty($latestEnrollment['review_note'])) { ?><p class="form-error" role="alert"><strong>Registrar note:</strong> <?php echo app_h($latestEnrollment['review_note']); ?></p><?php } ?>
		<p><a class="button" href="enrollmenthistory.php">View enrollment history</a> <a class="button" href="assessment.php?id=<?php echo (int)$latestEnrollment['ID']; ?>">View assessment</a>
		<?php if ($latestEnrollment['status'] === 'approved') { ?><a class="button" href="cor.php?id=<?php echo (int)$latestEnrollment['ID']; ?>">Print Certificate of Registration</a><?php } ?>
		<?php if ($latestEnrollment['status'] === 'rejected') { ?><a class="button" href="enroll.php?semester=<?php echo app_h(urlencode($latestEnrollment['semester'])); ?>">Submit a corrected form</a><?php } ?></p>
		<?php } else { ?>
		<p>You have not submitted an enrollment form yet.</p>
		<p><a class="button" href="enroll.php">Start enrollment</a></p>
		<?php } ?>
		<?php if ($announcements) { ?>
		<h2>Announcements</h2>
		<ul class="announcement-list">
			<?php foreach ($announcements as $row) { ?>
			<li><strong><?php echo app_h($row['title']); ?></strong><small><?php echo app_h($row['created_at'] ? date('M j, Y', strtotime($row['created_at'])) : ''); ?></small><p><?php echo app_h($row['body']); ?></p></li>
			<?php } ?>
		</ul>
		<?php } ?>
		<!-- Box -->
		<div class="box">
			<h2>Welcome Student</h2>
			<div class="entry">
				<p><strong>John Paul College</strong></p>
				<p><strong>Mission:</strong> A center of learning and academic excellence in the Province of Oriental Mindoro.</p>
				<p><strong>Vision:</strong> John Paul College is committed to improving education and the lives of people by preparing students to think critically, create, lead, compete, and contribute to their communities.</p>
			</div>
		</div>
		<!-- /Box -->
		<!-- Box -->
		<div class="box">
			<h2>Latest Events</h2>
			<div class="entry">
				<!-- News -->
				<div class="news">
					<ul>
						<li>
							<div class="post-image">
								<img src="../assets/news1.jpg" width="90" height="70" alt="College campus event">
							</div>
							<div class="post-data">
								<h5>Academic Year 2026-2027</h5>
								<p>Check with the registrar for current academic announcements and enrollment dates.</p>
							</div>
							<div class="cl">&nbsp;</div>
						</li>
						<li class="last">
							<div class="post-image">
								<img src="../assets/news2.jpg" width="90" height="70" alt="Student activities">
							</div>
							<div class="post-data">
								<h5>Enrollment Updates | 2026</h5>
								<p>Review your profile, choose available subjects, and check the status of your enrollment forms.</p>
							</div>
							<div class="cl">&nbsp;</div>
						</li>
					</ul>
				</div>
				<!-- /News -->
			</div>
			<div class="buttons">
				<a href="enroll.php" class="button"><span>View enrollment options</span></a>
			</div>
		</div>
		<!-- /Box -->
		<!-- Box -->
		<div class="box last-box">
			<h2>Programs</h2>
			<div class="entry bullet-list">
				<ul>
					  <li>CHED programs</li>
	                    <li>TESDA programs</li>
				</ul>
				<div class="post-image">
								<img src="../assets/news3.jpg" width="100" height="100" alt="Academic program information">
								<img src="../assets/news4.jpg" width="100" height="100" alt="College campus">
							</div>
			</div>
		</div>
		<!-- /Box -->
		<div class="cl">&nbsp;</div>
	</div>
  <div><br></div>
</div>
<!-- /Main -->

<!-- Footer -->
<div id="footer">
	<div class="shell">
		<!-- Mini Nav -->
		<div class="footer-navigation">
			<?php
include('includes/menu2.php');
?>
		</div>
		<!-- /Mini Nav -->
		<!-- Copyrights -->
		<p class="right">
			Copyright 2026 | Online Enrollment
		</p>
		<!-- /Copyrights -->
		<div class="cl">&nbsp;</div>
	</div>
</div>

</body>
</html>
