<?php
require_once __DIR__ . '/includes/conn.php';
require_once __DIR__ . '/logcheck.php';

$users = $conn->query('SELECT ID, studnum, fname, minitial, lname, yearlvl, course FROM studenttbl ORDER BY lname, fname');
$notice = isset($_SESSION['flash_success']) ? $_SESSION['flash_success'] : '';
$error = isset($_SESSION['flash_error']) ? $_SESSION['flash_error'] : '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Users | John Paul College</title>
<link href="style.css" rel="stylesheet" type="text/css">
</head>
<body>
<div class="header"></div>
<nav class="container" aria-label="Administrator navigation"><?php include __DIR__ . '/includes/menu1.php'; ?></nav>
<main id="main">
	<div class="shell">
		<div class="page-heading">
			<h1>Registered Students</h1>
			<div class="actions">
				<a class="button" href="adminedit.php" title="Edit administrator account">Administrator account</a>
				<a class="button" href="addnewuser.php"><svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18"><path d="M12 5v14M5 12h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg> Add student</a>
			</div>
		</div>
		<?php if ($notice !== '') { ?><p class="form-success" role="status"><?php echo app_h($notice); ?></p><?php } ?>
		<?php if ($error !== '') { ?><p class="form-error" role="alert"><?php echo app_h($error); ?></p><?php } ?>
		<div class="table-wrap">
			<table>
				<thead><tr><th scope="col">Student ID</th><th scope="col">Student Name</th><th scope="col">Course</th><th scope="col">Year Level</th><th scope="col">Actions</th></tr></thead>
				<tbody>
				<?php if ($users->num_rows === 0) { ?><tr><td colspan="5">No students are registered yet.</td></tr><?php } ?>
				<?php while ($user = $users->fetch_assoc()) { ?>
					<tr>
						<td><?php echo app_h($user['studnum']); ?></td>
						<td><?php echo app_h(trim($user['fname'] . ' ' . $user['minitial'] . ' ' . $user['lname'])); ?></td>
						<td><?php echo app_h(strtoupper($user['course'])); ?></td>
						<td><?php echo app_h($user['yearlvl']); ?></td>
						<td>
							<form method="post" action="deletecurruser.php" class="inline-form" onsubmit="return confirm('Delete this student account? All of their enrollment forms, payments and documents are deleted with it (the database cascade). This cannot be undone.');">
								<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
								<input type="hidden" name="student_id" value="<?php echo (int)$user['ID']; ?>">
								<button class="button button-danger" type="submit">Delete</button>
							</form>
						</td>
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
