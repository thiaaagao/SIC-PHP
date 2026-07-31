<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/AuditLog.php';

session_start();
logAccess();
Auth::requireMinLevel('admin');

$db = Database::getInstance();
$userId = (int) ($_GET['id'] ?? 0);
$confirm = $_GET['confirm'] ?? '';

if (!$userId || $confirm !== 'yes') {
    header('Location: users.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userToDelete = $stmt->fetch();

if (!$userToDelete) {
    header('Location: users.php');
    exit;
}

if ($userToDelete['role'] === 'admin') {
    header('Location: users.php?msg=cannot_delete_admin');
    exit;
}

$db->beginTransaction();

try {
    $anonymizedName = 'Ex-Usuario-' . $userId;
    $stmt = $db->prepare("UPDATE tickets SET requester_name = ? WHERE requester_name = ?");
    $stmt->execute([$anonymizedName, $userToDelete['name']]);

    $stmt = $db->prepare("UPDATE comments SET comment = '[REMOVIDO - LGPD]' WHERE user_id = ?");
    $stmt->execute([$userId]);

    $stmt = $db->prepare("DELETE FROM ratings WHERE user_id = ?");
    $stmt->execute([$userId]);

    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);

    $db->commit();

    AuditLog::log('user_delete', 'user', $userId, "Usuario {$userToDelete['name']} anonimizado (LGPD)");

    header('Location: users.php?msg=deleted');
    exit;
} catch (Exception $e) {
    $db->rollBack();
    header('Location: users.php?msg=error');
    exit;
}
