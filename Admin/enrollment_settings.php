<?php
require_once __DIR__ . '/includes/conn.php';
require_once __DIR__ . '/logcheck.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
	app_verify_csrf();
	$open = isset($_POST['enrollment_open']) ? '1' : '0';
	$start = trim(app_input('enrollment_start'));
	$end = trim(app_input('enrollment_end'));
	$maxUnits = app_int_input('max_units', -1);
	$feeFields = array('fee_per_unit', 'fee_entrance', 'fee_internet', 'fee_misc', 'fee_lab', 'fee_tutorial');
	$feeValues = array();
	foreach ($feeFields as $field) {
		$feeValues[$field] = app_input($field);
	}
	if (($start !== '' && !app_valid_date($start)) || ($end !== '' && !app_valid_date($end))) {
		$error = 'Enter valid enrollment start and end dates.';
	} elseif ($start !== '' && $end !== '' && $end < $start) {
		$error = 'The enrollment end date must be on or after the start date.';
	} elseif ($maxUnits < 1 || $maxUnits > 60) {
		$error = 'Enter a maximum unit load between 1 and 60.';
	} else {
		foreach ($feeValues as $value) {
			if (!is_numeric($value) || (float)$value < 0) {
				$error = 'All fees must be zero or more.';
				break;
			}
		}
	}
	if ($error === '') {
		try {
			app_setting_set($conn, 'enrollment_open', $open);
			app_setting_set($conn, 'enrollment_start', $start);
			app_setting_set($conn, 'enrollment_end', $end);
			app_setting_set($conn, 'max_units', (string)$maxUnits);
			foreach ($feeValues as $field => $value) {
				app_setting_set($conn, $field, (string)(float)$value);
			}
			$success = 'Enrollment settings saved.';
			unset($_SESSION['csrf_token']);
		} catch (mysqli_sql_exception $exception) {
			$error = 'The settings could not be saved. The settings table may not be installed yet — import database/migrate.sql.';
		}
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_term'])) {
	app_verify_csrf();
	$termId = app_int_input('term_id');
	$term = null;
	try {
		$lookup = $conn->prepare('SELECT ID, school_year, semester FROM terms WHERE ID = ?');
		$lookup->bind_param('i', $termId);
		$lookup->execute();
		$term = $lookup->get_result()->fetch_assoc();
	} catch (mysqli_sql_exception $exception) {
		$term = null;
	}
	if (!$term) {
		$error = 'Choose a valid term. If the terms table is missing, import database/migrate.sql first.';
	} else {
		try {
			$conn->begin_transaction();
			$conn->query('UPDATE terms SET is_current = 0');
			$update = $conn->prepare('UPDATE terms SET is_current = 1 WHERE ID = ?');
			$update->bind_param('i', $termId);
			$update->execute();
			$conn->commit();
			app_audit($conn, 'term_change', 'term', (string)$termId, 'Current term set to ' . $term['school_year'] . ' ' . $term['semester'] . '.');
			$success = 'Current term set to ' . $term['school_year'] . ' ' . $term['semester'] . '. New enrollments now use this term.';
			unset($_SESSION['csrf_token']);
		} catch (mysqli_sql_exception $exception) {
			$conn->rollback();
			$error = 'The current term could not be changed.';
		}
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_announcement'])) {
	app_verify_csrf();
	$title = trim(app_input('title'));
	$body = trim(app_input('body'));
	$startsAt = str_replace('T', ' ', trim(app_input('starts_at')));
	$endsAt = str_replace('T', ' ', trim(app_input('ends_at')));
	$published = isset($_POST['is_published']) ? 1 : 0;
	if ($title === '' || $body === '') {
		$error = 'An announcement needs a title and a message.';
	} elseif (strlen($title) > 200) {
		$error = 'The announcement title is too long.';
	} elseif (($startsAt !== '' && strtotime($startsAt) === false) || ($endsAt !== '' && strtotime($endsAt) === false)) {
		$error = 'Enter valid announcement start and end dates.';
	} else {
		try {
			$startValue = $startsAt === '' ? null : $startsAt;
			$endValue = $endsAt === '' ? null : $endsAt;
			$insert = $conn->prepare('INSERT INTO announcements (title, body, is_published, starts_at, ends_at) VALUES (?, ?, ?, ?, ?)');
			$insert->bind_param('ssiss', $title, $body, $published, $startValue, $endValue);
			$insert->execute();
			$success = 'Announcement added.';
			unset($_SESSION['csrf_token']);
		} catch (mysqli_sql_exception $exception) {
			$error = 'The announcement could not be saved. The announcements table may not be installed yet — import database/migrate.sql.';
		}
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_announcement'])) {
	app_verify_csrf();
	$announcementId = app_int_input('announcement_id');
	$action = app_input('announcement_action');
	if ($announcementId < 1) {
		$error = 'Choose a valid announcement.';
	} elseif ($action === 'delete') {
		$delete = $conn->prepare('DELETE FROM announcements WHERE ID = ?');
		$delete->bind_param('i', $announcementId);
		$delete->execute();
		$success = 'Announcement deleted.';
	} elseif (in_array($action, array('publish', 'unpublish'), true)) {
		$published = $action === 'publish' ? 1 : 0;
		$update = $conn->prepare('UPDATE announcements SET is_published = ? WHERE ID = ?');
		$update->bind_param('ii', $published, $announcementId);
		$update->execute();
		$success = $published ? 'Announcement published.' : 'Announcement unpublished.';
	} else {
		$error = 'Choose a valid announcement action.';
	}
}

$settings = array(
	'enrollment_open' => app_setting_get($conn, 'enrollment_open', '1'),
	'enrollment_start' => app_setting_get($conn, 'enrollment_start', ''),
	'enrollment_end' => app_setting_get($conn, 'enrollment_end', ''),
	'max_units' => app_setting_get($conn, 'max_units', '24'),
	'fee_per_unit' => app_setting_get($conn, 'fee_per_unit', '296'),
	'fee_entrance' => app_setting_get($conn, 'fee_entrance', '2050'),
	'fee_internet' => app_setting_get($conn, 'fee_internet', '150'),
	'fee_misc' => app_setting_get($conn, 'fee_misc', '50'),
	'fee_lab' => app_setting_get($conn, 'fee_lab', '1500'),
	'fee_tutorial' => app_setting_get($conn, 'fee_tutorial', '1500')
);
$terms = array();
try {
	$result = $conn->query('SELECT ID, school_year, semester, start_date, end_date, is_current, enrollment_open FROM terms ORDER BY start_date DESC, ID DESC');
	while ($row = $result->fetch_assoc()) {
		$terms[] = $row;
	}
} catch (mysqli_sql_exception $exception) {
	$terms = array();
}
$announcements = array();
try {
	$result = $conn->query('SELECT ID, title, body, is_published, starts_at, ends_at, created_at FROM announcements ORDER BY created_at DESC, ID DESC LIMIT 50');
	while ($row = $result->fetch_assoc()) {
		$announcements[] = $row;
	}
} catch (mysqli_sql_exception $exception) {
	$announcements = array();
	if ($error === '') {
		$error = 'Announcements are not available yet. Import database/migrate.sql first.';
	}
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Enrollment Settings | John Paul College</title>
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
			<div><p class="eyebrow">Registrar's Office</p><h1><svg width="28" height="28" aria-hidden="true"><use href="../assets/icons.svg#icon-calendar"/></svg> Enrollment Settings</h1><p>Control the enrollment period, fees, unit limit, and announcements students see.</p></div>
		</header>
		<?php if ($error !== '') { ?><p class="form-error" role="alert"><?php echo app_h($error); ?></p><?php } ?>
		<?php if ($success !== '') { ?><p class="form-success" role="status"><?php echo app_h($success); ?></p><?php } ?>
		<h2>Enrollment period &amp; fees</h2>
		<form class="cmxform" method="post">
			<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
			<fieldset>
				<legend>Enrollment period</legend>
				<label class="check-label"><input type="checkbox" name="enrollment_open" value="1" <?php echo $settings['enrollment_open'] === '1' ? 'checked' : ''; ?>> Enrollment is open</label>
				<div class="field-grid">
					<label for="enrollment_start">Opens on<input id="enrollment_start" name="enrollment_start" type="date" value="<?php echo app_h($settings['enrollment_start']); ?>"></label>
					<label for="enrollment_end">Closes on<input id="enrollment_end" name="enrollment_end" type="date" value="<?php echo app_h($settings['enrollment_end']); ?>"></label>
					<label for="max_units">Maximum units per term<input id="max_units" name="max_units" type="number" min="1" max="60" value="<?php echo (int)$settings['max_units']; ?>" required></label>
				</div>
				<p>Leave the dates empty for no date limit. When enrollment is closed, students see a message and cannot submit new forms; the admin can still view everything.</p>
			</fieldset>
			<fieldset>
				<legend>Fees (&#8369;)</legend>
				<div class="field-grid">
					<label for="fee_per_unit">Per unit<input id="fee_per_unit" name="fee_per_unit" type="number" min="0" step="0.01" value="<?php echo app_h($settings['fee_per_unit']); ?>" required></label>
					<label for="fee_entrance">Entrance fee<input id="fee_entrance" name="fee_entrance" type="number" min="0" step="0.01" value="<?php echo app_h($settings['fee_entrance']); ?>" required></label>
					<label for="fee_internet">Internet<input id="fee_internet" name="fee_internet" type="number" min="0" step="0.01" value="<?php echo app_h($settings['fee_internet']); ?>" required></label>
					<label for="fee_misc">Miscellaneous<input id="fee_misc" name="fee_misc" type="number" min="0" step="0.01" value="<?php echo app_h($settings['fee_misc']); ?>" required></label>
					<label for="fee_lab">Lab fee (per lab subject)<input id="fee_lab" name="fee_lab" type="number" min="0" step="0.01" value="<?php echo app_h($settings['fee_lab']); ?>" required></label>
					<label for="fee_tutorial">Tutorial fee (per tutorial subject)<input id="fee_tutorial" name="fee_tutorial" type="number" min="0" step="0.01" value="<?php echo app_h($settings['fee_tutorial']); ?>" required></label>
				</div>
				<p>New enrollments use these fees. Already-submitted assessments keep the amounts stored when they were submitted.</p>
			</fieldset>
			<button type="submit" name="save_settings" value="1">Save settings</button>
		</form>
		<h2>Current term</h2>
		<p>New enrollments use the current term for their school year and semester. This fixes the January bug: a 2nd-semester enrollment in January 2027 stays in school year 2026-2027 instead of jumping to 2027-2028.</p>
		<?php if (!$terms) { ?><p class="form-error" role="alert">No terms found. Import database/migrate.sql (or database/schema.sql on a fresh install) to create the terms table and its seed term.</p><?php } ?>
		<?php if ($terms) { ?>
		<form class="cmxform" method="post">
			<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
			<fieldset>
				<legend>Set the current term</legend>
				<label for="term_id">Current term
					<select id="term_id" name="term_id">
					<?php foreach ($terms as $termRow) { ?><option value="<?php echo (int)$termRow['ID']; ?>"<?php echo (int)$termRow['is_current'] === 1 ? ' selected' : ''; ?>><?php echo app_h($termRow['school_year'] . ' — ' . $termRow['semester']); ?><?php echo $termRow['start_date'] ? ' (' . app_h($termRow['start_date']) . ' to ' . app_h($termRow['end_date']) . ')' : ''; ?></option><?php } ?>
					</select>
				</label>
			</fieldset>
			<button type="submit" name="set_term" value="1">Set current term</button>
		</form>
		<?php } ?>
		<h2>Add an announcement</h2>
		<form class="cmxform" method="post">
			<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
			<fieldset>
				<legend>New announcement</legend>
				<div class="field-grid">
					<label for="title">Title<input id="title" name="title" maxlength="200" required></label>
					<label for="starts_at">Show from<input id="starts_at" name="starts_at" type="datetime-local"></label>
					<label for="ends_at">Show until<input id="ends_at" name="ends_at" type="datetime-local"></label>
				</div>
				<label for="body">Message</label><textarea id="body" name="body" required></textarea>
				<label class="check-label"><input type="checkbox" name="is_published" value="1" checked> Publish immediately</label>
			</fieldset>
			<button type="submit" name="save_announcement" value="1">Add announcement</button>
		</form>
		<h2>Announcements</h2>
		<div class="table-wrap">
			<table>
				<thead><tr><th>Created</th><th>Title</th><th>Message</th><th>Visible</th><th>Status</th><th>Action</th></tr></thead>
				<tbody>
				<?php if (!$announcements) { ?><tr><td colspan="6" class="empty-state">No announcements yet.</td></tr><?php } ?>
				<?php foreach ($announcements as $row) { ?>
					<tr>
						<td><?php echo app_h($row['created_at'] ? date('M j, Y', strtotime($row['created_at'])) : '—'); ?></td>
						<td><?php echo app_h($row['title']); ?></td>
						<td><?php echo app_h($row['body']); ?></td>
						<td><?php echo app_h(($row['starts_at'] ?: '—') . ' to ' . ($row['ends_at'] ?: '—')); ?></td>
						<td><span class="status-badge <?php echo (int)$row['is_published'] === 1 ? 'status-approved' : 'status-pending'; ?>"><?php echo (int)$row['is_published'] === 1 ? 'Published' : 'Draft'; ?></span></td>
						<td>
							<form method="post" class="inline-form">
								<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
								<input type="hidden" name="announcement_id" value="<?php echo (int)$row['ID']; ?>">
								<input type="hidden" name="announcement_action" value="<?php echo (int)$row['is_published'] === 1 ? 'unpublish' : 'publish'; ?>">
								<button type="submit" name="set_announcement" value="1"><?php echo (int)$row['is_published'] === 1 ? 'Unpublish' : 'Publish'; ?></button>
							</form>
							<form method="post" class="inline-form" onsubmit="return confirm('Delete this announcement?');">
								<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
								<input type="hidden" name="announcement_id" value="<?php echo (int)$row['ID']; ?>">
								<input type="hidden" name="announcement_action" value="delete">
								<button type="submit" name="set_announcement" value="1">Delete</button>
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
