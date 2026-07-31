<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/AuditLog.php';
require_once __DIR__ . '/../../src/Pagination.php';

session_start();
logAccess();
Auth::requireMinLevel('admin');

$db = Database::getInstance();

$pager = Pagination::getParams(50);
$total = Pagination::getTotal($db, "SELECT * FROM audit_logs");
$totalPages = (int) ceil($total / $pager['perPage']);

$stmt = $db->prepare("SELECT a.*, u.name as user_name FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT {$pager['perPage']} OFFSET {$pager['offset']}");
$stmt->execute();
$logs = $stmt->fetchAll();

$actionLabels = [
    'login' => 'Login',
    'login_failed' => 'Falha no Login',
    'ticket_create' => 'Criar Ticket',
    'ticket_resolve' => 'Resolver Ticket',
    'ticket_assign' => 'Atribuir Ticket',
    'ticket_priority' => 'Alterar Prioridade',
    'ticket_update' => 'Atualizar Ticket',
    'ticket_rate' => 'Avaliar Ticket',
    'comment_add' => 'Adicionar Comentario',
    'user_delete' => 'Excluir Usuario',
    'category_create' => 'Criar Categoria',
    'category_update' => 'Atualizar Categoria',
    'category_delete' => 'Excluir Categoria',
    'subcategory_create' => 'Criar Subcategoria',
    'subcategory_update' => 'Atualizar Subcategoria',
    'subcategory_delete' => 'Excluir Subcategoria',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoria - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/theme.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand bg-danger navbar-dark">
        <div class="container">
            <span class="navbar-brand fw-bold">Auditoria do Sistema</span>
            <div class="ms-auto d-flex gap-2 align-items-center">
                <a href="../support.php" class="btn btn-outline-light btn-sm">Suporte</a>
                <a href="index.php" class="btn btn-outline-light btn-sm">Admin</a>
                <a href="users.php" class="btn btn-outline-light btn-sm">Usuarios</a>
                <a href="tickets.php" class="btn btn-outline-light btn-sm">Tickets</a>
                <button id="themeToggle" class="btn-theme-toggle" title="Alternar tema"></button>
            </div>
        </div>
    </nav>
    <div class="container my-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Registro de Auditoria</h5>
                <small class="text-muted"><?= number_format($total) ?> registros</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Data/Hora</th>
                                <th>Usuario</th>
                                <th>Acao</th>
                                <th>Entidade</th>
                                <th>Detalhes</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                            <tr><td colspan="6" class="text-center py-5">
                                <div class="text-muted mb-3" style="font-size:2.5rem">&#128220;</div>
                                <h5 class="text-muted">Nenhum registro de auditoria</h5>
                            </td></tr>
                            <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="text-nowrap"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                                <td><?= htmlspecialchars($log['user_name'] ?? 'Sistema') ?></td>
                                <td><span class="badge bg-secondary"><?= $actionLabels[$log['action']] ?? $log['action'] ?></span></td>
                                <td><?= htmlspecialchars($log['entity_type']) ?> <?= $log['entity_id'] ? '#' . $log['entity_id'] : '' ?></td>
                                <td><?= htmlspecialchars($log['details'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($log['ip']) ?></td>
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
                <?= Pagination::render($pager['page'], $totalPages, 'audit.php') ?>
            </div>
            <?php endif ?>
        </div>
    </div>
    <script src="../assets/theme.js"></script>
    <script src="../assets/shortcuts.js"></script>
</body>
</html>
