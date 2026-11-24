<?php
require_once __DIR__ . '/includes/conn.php';
require_once __DIR__ . '/logcheck.php';

$courseFilter = isset($_GET['course']) && is_string($_GET['course']) && array_key_exists($_GET['course'], app_course_options()) ? $_GET['course'] : '';
$semesterFilter = isset($_GET['semester']) && in_array($_GET['semester'], array('1st Semester', '2nd Semester', 'Summer', 'Legacy'), true) ? $_GET['semester'] : '';
$search = isset($_GET['search']) && is_string($_GET['search']) ? trim($_GET['search']) : '';
$search = substr($search, 0, 100);
$page = isset($_GET['page']) && ctype_digit((string)$_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$pageSize = 50;
$searchPattern = '%' . $search . '%';
$countStatement = $conn->prepare("SELECT COUNT(*) AS subject_count FROM subjecttbl WHERE (? = '' OR course_con = ?) AND ((? = '' AND semester <> 'Legacy') OR semester = ?) AND (? = '' OR subjectcode LIKE ? OR subjecttitle LIKE ?)");
$countStatement->bind_param('sssssss', $courseFilter, $courseFilter, $semesterFilter, $semesterFilter, $search, $searchPattern, $searchPattern);
$countStatement->execute();
$subjectCount = (int)$countStatement->get_result()->fetch_assoc()['subject_count'];
$pageCount = max(1, (int)ceil($subjectCount / $pageSize));
$page = min($page, $pageCount);
$offset = ($page - 1) * $pageSize;
$statement = $conn->prepare("SELECT ID, subjectcode, subjecttitle, numofunits, course_con, semester FROM subjecttbl WHERE (? = '' OR course_con = ?) AND ((? = '' AND semester <> 'Legacy') OR semester = ?) AND (? = '' OR subjectcode LIKE ? OR subjecttitle LIKE ?) ORDER BY course_con, semester, subjectcode LIMIT ? OFFSET ?");
$statement->bind_param('sssssssii', $courseFilter, $courseFilter, $semesterFilter, $semesterFilter, $search, $searchPattern, $searchPattern, $pageSize, $offset);
$statement->execute();
$subjects = $statement->get_result();
$notice = isset($_SESSION['flash_success']) ? $_SESSION['flash_success'] : '';
$error = isset($_SESSION['flash_error']) ? $_SESSION['flash_error'] : '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$pageParameters = array('course' => $courseFilter, 'semester' => $semesterFilter, 'search' => $search);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Subjects | John Paul College</title>
<link href="style.css" rel="stylesheet" type="text/css">
</head>
<body>
<div class="header"></div>
<nav class="container" aria-label="Administrator navigation"><?php include __DIR__ . '/includes/menu1.php'; ?></nav>
<main id="main">
	<div class="shell">
		<div class="page-heading">
			<div><h1>Subjects</h1><p>Manage the course catalog by department and semester.</p></div>
			<a class="button" href="addnewsubj.php"><svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18"><path d="M12 5v14M5 12h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg> Add subject</a>
		</div>
		<?php if ($notice !== '') { ?><p class="form-success" role="status"><?php echo app_h($notice); ?></p><?php } ?>
		<?php if ($error !== '') { ?><p class="form-error" role="alert"><?php echo app_h($error); ?></p><?php } ?>
		<form class="catalog-filters" method="get">
			<label for="course-filter">Course
				<select id="course-filter" name="course"><option value="">All courses</option><?php foreach (app_course_options() as $courseCode => $courseTitle) { ?><option value="<?php echo app_h($courseCode); ?>" <?php echo $courseFilter === $courseCode ? 'selected' : ''; ?>><?php echo app_h(strtoupper($courseCode) . ' - ' . $courseTitle); ?></option><?php } ?></select>
			</label>
			<label for="semester-filter">Semester
				<select id="semester-filter" name="semester"><option value="">All semesters</option><option value="1st Semester" <?php echo $semesterFilter === '1st Semester' ? 'selected' : ''; ?>>1st Semester</option><option value="2nd Semester" <?php echo $semesterFilter === '2nd Semester' ? 'selected' : ''; ?>>2nd Semester</option><option value="Summer" <?php echo $semesterFilter === 'Summer' ? 'selected' : ''; ?>>Summer</option><option value="Legacy" <?php echo $semesterFilter === 'Legacy' ? 'selected' : ''; ?>>Legacy / unassigned</option></select>
			</label>
			<label for="subject-search">Search
				<input id="subject-search" name="search" type="search" maxlength="100" value="<?php echo app_h($search); ?>" placeholder="Code or subject name">
			</label>
			<button type="submit">Apply filters</button>
			<a href="subjectreg.php">Clear</a>
		</form>
		<p class="catalog-result-count">Showing <?php echo $subjectCount ? $offset + 1 : 0; ?>–<?php echo min($offset + $pageSize, $subjectCount); ?> of <?php echo $subjectCount; ?> subjects</p>
		<div class="table-wrap">
			<table>
				<thead><tr><th scope="col">Course</th><th scope="col">Subject Code</th><th scope="col">Subject Name</th><th scope="col">Semester</th><th scope="col">Units</th><th scope="col">Actions</th></tr></thead>
				<tbody>
				<?php if ($subjects->num_rows === 0) { ?><tr><td colspan="6">No subjects match these filters.</td></tr><?php } ?>
				<?php while ($subject = $subjects->fetch_assoc()) { ?>
					<tr>
						<td><?php echo app_h(strtoupper($subject['course_con'])); ?></td>
						<td><?php echo app_h($subject['subjectcode']); ?></td>
						<td><?php echo app_h($subject['subjecttitle']); ?></td>
						<td><?php echo app_h($subject['semester']); ?></td>
						<td><?php echo (int)$subject['numofunits']; ?></td>
						<td>
							<form method="post" action="deletecurrsubj.php" class="inline-form" onsubmit="return confirm('Delete this subject? Existing enrollment records that reference it will prevent deletion.');">
								<input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
								<input type="hidden" name="subject_id" value="<?php echo (int)$subject['ID']; ?>">
								<button class="button button-danger" type="submit">Delete</button>
							</form>
						</td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
		<?php if ($pageCount > 1) { ?><nav class="catalog-pagination" aria-label="Subject catalog pages">
			<?php if ($page > 1) { ?><a href="subjectreg.php?<?php echo app_h(http_build_query(array_merge($pageParameters, array('page' => $page - 1)))); ?>">Previous</a><?php } ?>
			<span>Page <?php echo $page; ?> of <?php echo $pageCount; ?></span>
			<?php if ($page < $pageCount) { ?><a href="subjectreg.php?<?php echo app_h(http_build_query(array_merge($pageParameters, array('page' => $page + 1)))); ?>">Next</a><?php } ?>
		</nav><?php } ?>
	</div>
</main>
<footer id="footer"><div class="shell"><?php include __DIR__ . '/includes/menu2.php'; ?></div></footer>
</body>
</html>
