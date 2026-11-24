<?php
require_once __DIR__ . '/includes/conn.php';
require_once __DIR__ . '/logcheck.php';

$schedules = $conn->query('SELECT sc.sched_ID, sc.Instructor_ID, sc.room_ID, sc.time_from, sc.time_to, sc.semester, sc.day, sec.section_name, sec.section_course, sec.yr_level, sub.subjectcode, sub.subjecttitle FROM sched_tbl sc JOIN sections_tbl sec ON sec.section_ID = sc.section_ID JOIN subjecttbl sub ON sub.ID = sc.subject_ID ORDER BY sec.section_course, sec.section_name, sc.semester, sc.day, sc.time_from LIMIT 500');
$notice = isset($_SESSION['flash_success']) ? $_SESSION['flash_success'] : '';
$error = isset($_SESSION['flash_error']) ? $_SESSION['flash_error'] : '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Manage Schedules | John Paul College</title><link href="style.css" rel="stylesheet"></head>
<body>
<div class="header"></div>
<nav class="container" aria-label="Administrator navigation"><?php include __DIR__ . '/includes/menu1.php'; ?></nav>
<main id="main"><div class="shell">
	<div class="page-heading"><h1>Schedules</h1><a class="button" href="addnewsched.php">Add schedule</a></div>
	<?php if ($notice !== '') { ?><p class="form-success" role="status"><?php echo app_h($notice); ?></p><?php } ?>
	<?php if ($error !== '') { ?><p class="form-error" role="alert"><?php echo app_h($error); ?></p><?php } ?>
	<div class="table-wrap"><table>
		<thead><tr><th>Course / section</th><th>Year</th><th>Semester</th><th>Day</th><th>Subject</th><th>Time</th><th>Instructor ID</th><th>Room ID</th><th>Actions</th></tr></thead>
		<tbody>
		<?php if ($schedules->num_rows === 0) { ?><tr><td colspan="9">No schedules have been created.</td></tr><?php } ?>
		<?php while ($schedule = $schedules->fetch_assoc()) { ?>
			<tr>
				<td><?php echo app_h(strtoupper($schedule['section_course']) . ' ' . $schedule['section_name']); ?></td>
				<td><?php echo (int)$schedule['yr_level']; ?></td>
				<td><?php echo app_h($schedule['semester']); ?></td>
				<td><?php echo app_h($schedule['day']); ?></td>
				<td><?php echo app_h($schedule['subjectcode'] . ' - ' . $schedule['subjecttitle']); ?></td>
				<td><?php echo app_h(substr($schedule['time_from'], 0, 5) . ' - ' . substr($schedule['time_to'], 0, 5)); ?></td>
				<td><?php echo (int)$schedule['Instructor_ID']; ?></td>
				<td><?php echo (int)$schedule['room_ID']; ?></td>
				<td><form method="post" action="deletesched.php" class="inline-form" onsubmit="return confirm('Delete this schedule?');">
					<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
					<input type="hidden" name="schedule_id" value="<?php echo (int)$schedule['sched_ID']; ?>">
					<button class="button-danger" type="submit">Delete</button>
				</form></td>
			</tr>
		<?php } ?>
		</tbody>
	</table></div>
</div></main>
</body>
</html>
