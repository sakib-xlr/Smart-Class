<?php
// Notifications API
require_once __DIR__ . '/../config/db.php';
requireLogin();

$user = currentUser();
$uid  = $user['id'];

// Mark one
if (isset($_GET['mark']) && is_numeric($_GET['mark'])) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([(int)$_GET['mark'], $uid]);
    jsonResponse(['success' => true]);
}

// Mark all
if (isset($_GET['mark_all']) || ($_POST['action'] ?? '') === 'mark_all') {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$uid]);
    jsonResponse(['success' => true]);
}

// Delete one
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $pdo->prepare("DELETE FROM notifications WHERE id=? AND user_id=?")->execute([(int)$_GET['delete'], $uid]);
    jsonResponse(['success' => true]);
}

// GET — return notification list + unread count
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 30");
$stmt->execute([$uid]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$unread = (int)$pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0")->execute([$uid])
    ?: 0;
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$countStmt->execute([$uid]);
$unread = (int)$countStmt->fetchColumn();

jsonResponse(['notifications' => $notifications, 'unread' => $unread]);
?>
