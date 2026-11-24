<?php
require_once __DIR__ . '/includes/conn.php';
require_once __DIR__ . '/logcheck.php';

$sections = $conn->query('SELECT section_ID, section_name, section_course, yr_level, min_num, max_num FROM sections_tbl ORDER BY section_course, yr_level, section_name');
$notice = isset($_SESSION['flash_success']) ? $_SESSION['flash_success'] : '';
$error = isset($_SESSION['flash_error']) ? $_SESSION['flash_error'] : '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Manage Sections | John Paul College</title><link href="style.css" rel="stylesheet"></head>
<body>
<div class="header"></div>
<nav class="container" aria-label="Administrator navigation"><?php include __DIR__ . '/includes/menu1.php'; ?></nav>
<main id="main"><div class="shell">
	<div class="page-heading"><h1>Sections</h1><a class="button" href="addnewsection.php">Add section</a></div>
	<?php if ($notice !== '') { ?><p class="form-success" role="status"><?php echo app_h($notice); ?></p><?php } ?>
	<?php if ($error !== '') { ?><p class="form-error" role="alert"><?php echo app_h($error); ?></p><?php } ?>
	<div class="table-wrap"><table>
		<thead><tr><th>Course</th><th>Section</th><th>Year level</th><th>Minimum students</th><th>Maximum students</th><th>Actions</th></tr></thead>
		<tbody>
		<?php if ($sections->num_rows === 0) { ?><tr><td colspan="6">No sections have been created.</td></tr><?php } ?>
		<?php while ($section = $sections->fetch_assoc()) { ?>
			<tr>
				<td><?php echo app_h(strtoupper($section['section_course'])); ?></td>
				<td><?php echo app_h($section['section_name']); ?></td>
				<td><?php echo (int)$section['yr_level']; ?></td>
				<td><?php echo (int)$section['min_num']; ?></td>
				<td><?php echo $section['max_num'] ? (int)$section['max_num'] : 'Unlimited'; ?></td>
				<td><form method="post" action="deletesection.php" class="inline-form" onsubmit="return confirm('Delete this section and its schedules?');">
					<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
					<input type="hidden" name="section_id" value="<?php echo (int)$section['section_ID']; ?>">
					<button class="button-danger" type="submit">Delete</button>
				</form></td>
			</tr>
		<?php } ?>
		</tbody>
	</table></div>
</div></main>
</body>
</html>
