<?php $appCurrentPage = basename($_SERVER['SCRIPT_NAME'] ?? ''); ?>
<ul>
	<li class="nav-item"><a href="adminindex.php" class="nav-link<?php echo $appCurrentPage === 'adminindex.php' ? ' active' : ''; ?>">Home</a></li>
	<li class="nav-item"><a href="userreg.php" class="nav-link<?php echo $appCurrentPage === 'userreg.php' ? ' active' : ''; ?>">Manage Students</a></li>
	<li class="nav-item"><a href="subjectreg.php" class="nav-link<?php echo $appCurrentPage === 'subjectreg.php' ? ' active' : ''; ?>">Manage Subjects</a></li>
	<li class="nav-item"><a href="sectionreg.php" class="nav-link<?php echo $appCurrentPage === 'sectionreg.php' ? ' active' : ''; ?>">Sections</a></li>
	<li class="nav-item"><a href="schedreg.php" class="nav-link<?php echo $appCurrentPage === 'schedreg.php' ? ' active' : ''; ?>">Schedules</a></li>
	<li class="nav-item"><a href="report.php" class="nav-link<?php echo $appCurrentPage === 'report.php' ? ' active' : ''; ?>">Enrollment Report</a></li>
	<li class="nav-item"><a href="payments.php" class="nav-link<?php echo $appCurrentPage === 'payments.php' ? ' active' : ''; ?>">Payments</a></li>
	<li class="nav-item"><a href="documents.php" class="nav-link<?php echo $appCurrentPage === 'documents.php' ? ' active' : ''; ?>">Documents</a></li>
	<li class="nav-item"><a href="enrollment_settings.php" class="nav-link<?php echo $appCurrentPage === 'enrollment_settings.php' ? ' active' : ''; ?>">Enrollment Settings</a></li>
	<li class="nav-item"><a href="notifications.php" class="nav-link<?php echo $appCurrentPage === 'notifications.php' ? ' active' : ''; ?>">Notifications<?php $appUnread = app_unread_notifications($conn, (int)$_SESSION['userID']); if ($appUnread > 0) { echo ' <span class="badge badge-blue">' . $appUnread . '</span>'; } ?></a></li>
	<li class="nav-item"><a href="audit.php" class="nav-link<?php echo $appCurrentPage === 'audit.php' ? ' active' : ''; ?>">Audit Log</a></li>
	<li class="nav-item"><form method="post" action="../logout.php" class="logout-form"><input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>"><button type="submit" class="nav-link">Log Out</button></form></li>
</ul>
