<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Pagination.php';

session_start();
logAccess();
Auth::requireMinLevel('admin');

$db = Database::getInstance();

$pager = Pagination::getParams(50);
$total = Pagination::getTotal($db, "SELECT * FROM access_logs");
$totalPages = (int) ceil($total / $pager['perPage']);

$stmt = $db->prepare("SELECT a.*, u.name as user_name FROM access_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT {$pager['perPage']} OFFSET {$pager['offset']}");
$stmt->execute();
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs de Acesso - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/theme.css" rel="stylesheet">
    <link href="../assets/toast.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand bg-dark navbar-dark">
        <div class="container">
            <span class="navbar-brand fw-bold">Logs de Acesso</span>
            <div class="ms-auto d-flex gap-2 align-items-center">
                <a href="../support.php" class="btn btn-outline-light btn-sm">Suporte</a>
                <a href="index.php" class="btn btn-outline-light btn-sm">Admin</a>
                <button id="themeToggle" class="btn-theme-toggle" title="Alternar tema"></button>
            </div>
        </div>
    </nav>
    <div class="container my-4">
        <a href="index.php" class="btn btn-outline-secondary btn-sm mb-3">&larr; Voltar</a>
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Acessos ao Sistema</h5>
                <small class="text-muted"><?= number_format($total) ?> registros</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Data/Hora</th>
                                <th>Usuario</th>
                                <th>Pagina</th>
                                <th>IP</th>
                                <th>User Agent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">Nenhum registro de acesso.</td></tr>
                            <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="text-nowrap"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                                <td><?= htmlspecialchars($log['user_name'] ?? 'Visitante') ?></td>
                                <td><?= htmlspecialchars($log['page']) ?></td>
                                <td><?= htmlspecialchars($log['ip']) ?></td>
                                <td><small><?= htmlspecialchars($log['user_agent']) ?></small></td>
                            </tr>
                            <?php endforeach ?>
                            <?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                <small class="text-muted"><?= Pagination::info($total, $pager['offset'], $pager['perPage']) ?></small>
                <?= Pagination::render($pager['page'], $totalPages, 'access_logs.php') ?>
            </div>
            <?php endif ?>
        </div>
    </div>
    <script src="../assets/toast.js"></script>
    <script src="../assets/theme.js"></script>
    <script src="../assets/shortcuts.js"></script>
</body>
</html>
