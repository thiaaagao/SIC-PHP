<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';

session_start();
Auth::requireAccess();
logAccess();

$db = Database::getInstance();
$id = (int) ($_GET['id'] ?? 0);

if (!$id) {
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM ticket_attachments WHERE id = ?");
$stmt->execute([$id]);
$att = $stmt->fetch();

if (!$att) {
    header('Location: index.php');
    exit;
}

$user = Auth::getUser();
if (!$user || ($att['uploaded_by'] != $user['id'] && !Auth::canResolve())) {
    http_response_code(403);
    echo 'Acesso negado.';
    exit;
}

$filename = basename($att['filename']);
$filepath = __DIR__ . '/../storage/uploads/' . $filename;

if ($filename !== $att['filename'] || !file_exists($filepath)) {
    header('Location: index.php');
    exit;
}

$realPath = realpath($filepath);
$uploadsDir = realpath(__DIR__ . '/../storage/uploads');
if (!$realPath || !str_starts_with($realPath, $uploadsDir)) {
    http_response_code(403);
    echo 'Acesso negado.';
    exit;
}

header('Content-Type: ' . $att['mime_type']);
header('Content-Length: ' . filesize($filepath));

$isImage = str_starts_with($att['mime_type'], 'image/');
if ($isImage) {
    header('Content-Disposition: inline; filename="' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $att['original_name']) . '"');
} else {
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $att['original_name']) . '"');
}

header('Cache-Control: private, max-age=0, must-revalidate');

readfile($filepath);
exit;
