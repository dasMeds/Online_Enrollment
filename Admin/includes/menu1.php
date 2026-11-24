<ul>
	<li class="nav-item"><a href="adminindex.php" class="nav-link">Home</a></li>
	<li class="nav-item"><a href="userreg.php" class="nav-link">Manage Students</a></li>
	<li class="nav-item"><a href="subjectreg.php" class="nav-link">Manage Subjects</a></li>
	<li class="nav-item"><a href="sectionreg.php" class="nav-link">Sections</a></li>
	<li class="nav-item"><a href="schedreg.php" class="nav-link">Schedules</a></li>
	<li class="nav-item"><a href="report.php" class="nav-link">Enrollment Report</a></li>
	<li class="nav-item"><form method="post" action="../logout.php" class="logout-form"><input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>"><button type="submit" class="nav-link">Log Out</button></form></li>
</ul>
