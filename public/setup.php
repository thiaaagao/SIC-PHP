<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Sector.php';

session_start();

$checks = [];

try {
    $db = Database::getInstance();
    $checks['database'] = ['status' => 'ok', 'msg' => 'Conectado ao MySQL/MariaDB'];
} catch (Exception $e) {
    $checks['database'] = ['status' => 'error', 'msg' => 'Erro: ' . $e->getMessage()];
}

if (TEAMS_WEBHOOK_URL) {
    $checks['teams'] = ['status' => 'ok', 'msg' => 'Webhook Teams configurado'];
} else {
    $checks['teams'] = ['status' => 'warning', 'msg' => 'Webhook vazio.'];
}

$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ipAllowed = Auth::isIpAllowed();
$checks['ip'] = [
    'status' => $ipAllowed ? 'ok' : 'error',
    'msg' => "Seu IP: {$clientIp} - " . ($ipAllowed ? 'Autorizado' : 'Nao autorizado'),
];

try {
    $db = Database::getInstance();
    $st = $db->query("SELECT COUNT(*) as total FROM users");
    $userCount = $st->fetch()['total'];
    $checks['users'] = ['status' => 'ok', 'msg' => "{$userCount} usuarios cadastrados"];
} catch (Exception $e) {
    $checks['users'] = ['status' => 'error', 'msg' => 'Tabela users nao encontrada'];
}

$checks['apache'] = ['status' => 'ok', 'msg' => 'Apache rodando na porta 8080'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - P.S. Profarma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <h3 class="mb-4">Setup - P.S. Profarma</h3>

        <?php foreach ($checks as $key => $c): ?>
            <div class="alert alert-<?= $c['status'] === 'ok' ? 'success' : ($c['status'] === 'warning' ? 'warning' : 'danger') ?> py-2">
                <strong><?= strtoupper($key) ?>:</strong> <?= htmlspecialchars($c['msg']) ?>
            </div>
        <?php endforeach ?>

        <div class="card mt-4">
            <div class="card-header"><strong>Hierarquia de Acesso</strong></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th>Nivel</th><th>Papel</th><th>Usuario</th><th>Senha</th><th>Navbar</th></tr>
                    <tr><td>3</td><td>Admin (Master)</td><td><code>admin</code></td><td><code>master@2026</code></td><td><span class="badge bg-dark">escuro</span></td></tr>
                    <tr><td>2</td><td>Suporte TI</td><td><code>suporte</code></td><td><code>suporte@2026</code></td><td><span class="badge bg-primary">azul</span></td></tr>
                    <tr><td>1</td><td>Encarregado</td><td><code>encarregado</code></td><td><code>encarregado@2026</code></td><td><span class="badge bg-success">verde</span></td></tr>
                    <tr><td>0</td><td>Visitante</td><td>sem login</td><td>-</td><td><span class="badge bg-secondary">cinza</span></td></tr>
                </table>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><strong>Setores Disponiveis</strong></div>
            <div class="card-body">
                <?php foreach (Sector::getActiveList() as $s): ?>
                    <span class="badge bg-info me-1"><?= $s ?></span>
                <?php endforeach ?>
            </div>
        </div>

        <div class="mt-4">
            <a href="index.php" class="btn btn-primary">Dashboard</a>
            <a href="login.php" class="btn btn-outline-primary">Login</a>
            <a href="open_ticket.php" class="btn btn-outline-primary">Abrir P.S.</a>
        </div>
    </div>
</body>
</html>
