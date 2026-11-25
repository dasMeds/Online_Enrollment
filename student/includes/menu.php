<?php $appCurrentPage = basename($_SERVER['SCRIPT_NAME'] ?? ''); ?>
	<ul>
		<li class="nav-item"><a class="nav-link<?php echo $appCurrentPage === 'studentindex.php' ? ' active' : ''; ?>" href="studentindex.php">Home</a></li>
		<li class="nav-item"><a class="nav-link<?php echo $appCurrentPage === 'studentprofile.php' ? ' active' : ''; ?>" href="studentprofile.php">Profile</a></li>
		<li class="nav-item"><a class="nav-link<?php echo $appCurrentPage === 'enroll.php' ? ' active' : ''; ?>" href="enroll.php">Enroll Now</a></li>
		<li class="nav-item"><a class="nav-link<?php echo $appCurrentPage === 'enrollmenthistory.php' ? ' active' : ''; ?>" href="enrollmenthistory.php">Enrollment History</a></li>
		<li class="nav-item"><a class="nav-link<?php echo $appCurrentPage === 'assessment.php' ? ' active' : ''; ?>" href="assessment.php">Assessment &amp; Payments</a></li>
		<li class="nav-item"><a class="nav-link<?php echo $appCurrentPage === 'documents.php' ? ' active' : ''; ?>" href="documents.php">My Documents</a></li>
		<li class="nav-item"><a class="nav-link<?php echo $appCurrentPage === 'notifications.php' ? ' active' : ''; ?>" href="notifications.php">Notifications<?php $appUnread = app_unread_notifications($conn, (int)$_SESSION['userID']); if ($appUnread > 0) { echo ' <span class="badge badge-blue">' . $appUnread . '</span>'; } ?></a></li>
		<li class="nav-item"><form method="post" action="../logout.php" class="logout-form"><input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>"><button type="submit" class="nav-link">Log Out</button></form></li>
	</ul>
