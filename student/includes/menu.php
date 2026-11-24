	<ul>
		<li class="nav-item"><a class="nav-link" href="studentindex.php">Home</a></li>
		<li class="nav-item"><a class="nav-link" href="studentprofile.php">Profile</a></li>
		<li class="nav-item"><a class="nav-link" href="enroll.php">Enroll Now</a></li>
		<li class="nav-item"><a class="nav-link" href="enrollmenthistory.php">Enrollment History</a></li>
		<li class="nav-item"><form method="post" action="../logout.php" class="logout-form"><input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>"><button type="submit" class="nav-link">Log Out</button></form></li>
	</ul>