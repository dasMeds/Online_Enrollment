<?php
/* Audit log: who did what, when. */
require_once __DIR__ . '/includes/conn.php';
require_once __DIR__ . '/logcheck.php';

$actionFilter = isset($_GET['action']) ? trim((string)$_GET['action']) : '';
$perPage = 50;
$page = isset($_GET['page']) && ctype_digit((string)$_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$where = '';
$params = array();
$types = '';
if ($actionFilter !== '') {
	$where = ' WHERE a.action = ?';
	$params[] = $actionFilter;
	$types = 's';
}
try {
	$countStatement = $conn->prepare('SELECT COUNT(*) AS n FROM audit_log a' . $where);
	if ($types !== '') { app_bind_params($countStatement, $types, $params); }
	$countStatement->execute();
	$total = (int)$countStatement->get_result()->fetch_assoc()['n'];
} catch (mysqli_sql_exception $exception) {
	$total = 0;
}
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

$query = 'SELECT a.action, a.target_type, a.target_id, a.details, a.created_at, u.username FROM audit_log a LEFT JOIN usertbl u ON u.ID = a.actor_userID' . $where . ' ORDER BY a.created_at DESC, a.ID DESC LIMIT ? OFFSET ?';
try {
	$statement = $conn->prepare($query);
	app_bind_params($statement, $types . 'ii', array_merge($params, array($perPage, $offset)));
	$statement->execute();
	$rows = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
	$actions = $conn->query('SELECT DISTINCT action FROM audit_log ORDER BY action')->fetch_all(MYSQLI_ASSOC);
} catch (mysqli_sql_exception $exception) {
	$rows = array();
	$actions = array();
}

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Audit Log | John Paul College</title>
<link href="style.css" rel="stylesheet" type="text/css">
<link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
</head>
<body>
<div class="header"></div>
<nav class="container" aria-label="Administrator navigation"><?php include __DIR__ . '/includes/menu1.php'; ?></nav>
<main id="main">
	<div class="shell wide-shell">
?>
<h1>Audit Log</h1>
<p><?php echo $total; ?> recorded action<?php echo $total === 1 ? '' : 's'; ?>. Approvals, rejections, payment and document verification, user create/delete, and term changes are all logged here.</p>
<form class="catalog-filters" method="get">
	<label for="action">Action<select id="action" name="action"><option value="">All actions</option><?php foreach ($actions as $a) { ?><option value="<?php echo app_h($a['action']); ?>" <?php echo $actionFilter === $a['action'] ? 'selected' : ''; ?>><?php echo app_h($a['action']); ?></option><?php } ?></select></label>
	<button type="submit">Filter</button>
</form>
<?php if (!$rows) { ?><p>No audit entries yet.</p><?php } else { ?>
<div class="table-wrap"><table>
	<thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Target</th><th>Details</th></tr></thead>
	<tbody>
	<?php foreach ($rows as $row) { ?>
	<tr>
		<td><?php echo app_h($row['created_at']); ?></td>
		<td><?php echo app_h($row['username'] !== '' && $row['username'] !== null ? $row['username'] : 'system'); ?></td>
		<td><?php echo app_h($row['action']); ?></td>
		<td><?php echo app_h($row['target_type'] . ' #' . $row['target_id']); ?></td>
		<td><?php echo app_h($row['details'] !== null ? $row['details'] : ''); ?></td>
	</tr>
	<?php } ?>
	</tbody>
</table></div>
<?php if ($totalPages > 1) { ?>
<nav class="pagination" aria-label="Audit pages">
	<?php if ($page > 1) { ?><a href="audit.php?action=<?php echo app_h(urlencode($actionFilter)); ?>&amp;page=<?php echo $page - 1; ?>">&laquo; Previous</a><?php } ?>
	<span>Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
	<?php if ($page < $totalPages) { ?><a href="audit.php?action=<?php echo app_h(urlencode($actionFilter)); ?>&amp;page=<?php echo $page + 1; ?>">Next &raquo;</a><?php } ?>
</nav>
<?php } ?>
<?php } ?>
	</div>
</main>
</body>
</html>
