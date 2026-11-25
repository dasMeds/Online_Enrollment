<?php
require_once __DIR__ . '/includes/conn.php';
require_once __DIR__ . '/logcheck.php';
require __DIR__ . '/../includes/notifications_actions.php';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Notifications | John Paul College</title>
<link href="style.css" rel="stylesheet" type="text/css">
<link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
</head>
<body>
<div class="header"></div>
<nav class="container" aria-label="Student navigation"><?php include __DIR__ . '/includes/menu.php'; ?></nav>
<main id="main">
	<div class="shell">
		<?php require __DIR__ . '/../includes/notifications_page.php'; ?>
	</div>
</main>
<footer id="footer"><div class="shell"><?php include __DIR__ . '/includes/menu2.php'; ?></div></footer>
</body>
</html>
