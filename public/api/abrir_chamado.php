<?php
date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/RateLimit.php';
require_once __DIR__ . '/../../src/Category.php';
require_once __DIR__ . '/../../src/TeamsNotification.php';
require_once __DIR__ . '/../../src/EmailNotification.php';

// Rate limit na API
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!RateLimit::check($ip, 5, 60)) {
    http_response_code(429);
    echo json_encode(['erro' => 'Muitas requisicoes. Tente novamente em 1 minuto.']);
    exit;
}

// Soh aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Metodo nao permitido. Use POST.']);
    exit;
}

// Le o body JSON
$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    http_response_code(400);
    echo json_encode(['erro' => 'Body JSON invalido ou vazio.']);
    exit;
}

// Valida campos obrigatorios
$requeridos = ['requester_name', 'subcategory', 'description'];
foreach ($requeridos as $campo) {
    if (empty($body[$campo])) {
        http_response_code(400);
        echo json_encode(['erro' => "Campo obrigatorio: $campo"]);
        exit;
    }
}

// Valida subcategoria
$db = Database::getInstance();
$validSubs = array_column($db->query("SELECT name FROM subcategories WHERE active = 1")->fetchAll(), 'name');
if (!in_array($body['subcategory'], $validSubs)) {
    http_response_code(400);
    echo json_encode(['erro' => 'Subcategoria invalida. Use: ' . implode(', ', $validSubs)]);
    exit;
}

$ip = $body['ip'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
$hostname = $body['hostname'] ?? '';
$setor = $body['setor'] ?? '';
$conf = $body['conf'] ?? '';

// Insere ticket
$stmt = $db->prepare("
    INSERT INTO tickets (requester_name, subcategory, description, ip, hostname, setor, conf, status, priority, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'open', 'medium', NOW())
");
$stmt->execute([
    $body['requester_name'],
    $body['subcategory'],
    $body['description'],
    $ip,
    $hostname,
    $setor,
    $conf,
]);
$ticketId = $db->lastInsertId();
$code = 'PS-' . str_pad($ticketId, 4, '0', STR_PAD_LEFT);

// Atualiza codigo
$db->prepare("UPDATE tickets SET code = ? WHERE id = ?")->execute([$code, $ticketId]);

$ticket = [
    'id' => $ticketId,
    'code' => $code,
    'requester_name' => $body['requester_name'],
    'user_name' => $body['requester_name'],
    'subcategory' => $body['subcategory'],
    'description' => $body['description'],
    'ip' => $ip,
    'hostname' => $hostname,
    'setor' => $setor,
    'conf' => $conf,
    'priority' => 'medium',
];

// Notifica Teams
try {
    TeamsNotification::sendNewTicket($ticket);
} catch (Exception $e) {
    // Notificacao nao eh critica
}

// Notifica email
try {
    EmailNotification::notifyNewTicket($ticket);
} catch (Exception $e) {
    // Notificacao nao eh critica
}

RateLimit::record($ip);

echo json_encode([
    'ok' => true,
    'id' => $ticketId,
    'code' => $code,
    'url' => BASE_URL . "/ticket.php?id=" . $ticketId,
]);
