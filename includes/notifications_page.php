<?php
/* Shared notification list rendering. Include inside the v2 page
   shell (after the header/nav), from Admin/notifications.php or
   student/notifications.php.
   Requires notifications_actions.php to have run first (before output). */
$statement = $conn->prepare('SELECT ID, type, title, body, link, is_read, created_at FROM notifications WHERE accountID = ? ORDER BY created_at DESC, ID DESC LIMIT 100');
$statement->bind_param('i', $_SESSION['userID']);
$statement->execute();
$notifications = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
$unreadCount = 0;
foreach ($notifications as $n) { if (!(int)$n['is_read']) { $unreadCount++; } }
?>
<h1>Notifications</h1>
<?php if ($unreadCount > 0) { ?>
<form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?php echo app_h(app_csrf_token()); ?>"><button type="submit" name="mark_all_read" value="1">Mark all as read (<?php echo $unreadCount; ?> unread)</button></form>
<?php } ?>
<?php if (!$notifications) { ?><p>You have no notifications yet.</p><?php } else { ?>
<ul class="notification-list">
	<?php foreach ($notifications as $n) { ?>
	<li class="<?php echo (int)$n['is_read'] ? '' : 'unread'; ?>">
		<?php if (!(int)$n['is_read']) { ?><span class="unread-dot" aria-label="Unread"></span><?php } ?>
		<strong><?php echo app_h($n['title']); ?></strong>
		<span class="badge badge-blue"><?php echo app_h($n['type']); ?></span>
		<small> <?php echo app_h($n['created_at']); ?></small>
		<p><?php echo app_h($n['body']); ?></p>
		<?php if ($n['link'] !== '') { ?><p><a href="notifications.php?read=<?php echo (int)$n['ID']; ?>">Open</a></p><?php } ?>
	</li>
	<?php } ?>
</ul>
<?php } ?>
