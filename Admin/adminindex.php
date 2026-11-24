<?php
include('logcheck.php');
$flash = isset($_SESSION['flash_success']) ? $_SESSION['flash_success'] : '';
unset($_SESSION['flash_success']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Administrator Dashboard | John Paul College</title>
<link href="style.css" rel="stylesheet" type="text/css">
</head>

<body>

<div class='header'>
</div>

<nav class='container'>
<?php
include('includes/menu1.php');
?>
</nav>





<div id="main">
	<div class="shell">
		<?php if ($flash !== '') { ?><p class="form-success" role="status"><?php echo app_h($flash); ?></p><?php } ?>
		<!-- Box -->
		<div class="box">
			<h2>Welcome to Admin</h2>
			
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
								<img src="news1.jpg" width="90" height="70" alt="College campus event">
							</div>
							<div class="post-data">
								<h5>Academic Year 2026-2027</h5>
								<p>Check with the registrar for current academic announcements and enrollment dates.</p>
							</div>
							<div class="cl">&nbsp;</div>
						</li>
						
						<li class="last">
							<div class="post-image">
								<img src="news2.jpg" width="90" height="70" alt="Student activities">
							</div>
							<div class="post-data">
								<h5>Enrollment Updates | 2026</h5>
								<p>Use the administrator tools to review student accounts, schedules, and submitted enrollment forms.</p>
							</div>
							<div class="cl">&nbsp;</div>
						</li>
					</ul>
				</div>
				<!-- /News -->
			</div>
			
			<div class="buttons">
				<a href="report.php" class="button"><span>View enrollment report</span></a>
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
								<img src="news3.jpg" width="100" height="100" alt="Academic program information">
								<img src="news4.jpg" width="100" height="100" alt="College campus">
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