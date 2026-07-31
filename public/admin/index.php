<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/NavHelper.php';

session_start();
logAccess();
Auth::requireMinLevel('admin');

$db = Database::getInstance();
$user = Auth::getUser();

$totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalTickets = $db->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
$totalComments = $db->query("SELECT COUNT(*) FROM comments")->fetchColumn();
$totalRatings = $db->query("SELECT COUNT(*) FROM ratings")->fetchColumn();
$openTickets = $db->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn();
$resolvedTickets = $db->query("SELECT COUNT(*) FROM tickets WHERE status = 'resolved'")->fetchColumn();
$avgRating = $db->query("SELECT COALESCE(AVG(rating), 0) FROM ratings")->fetchColumn();

$usersByRole = $db->query("SELECT role, COUNT(*) as total FROM users GROUP BY role")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - S.I.C.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/theme.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand bg-dark navbar-dark">
        <div class="container">
            <span class="navbar-brand fw-bold">Admin - S.I.C.<?= NavHelper::badge() ?></span>
            <div class="ms-auto d-flex gap-2 align-items-center">
                <a href="../index.php" class="btn btn-outline-light btn-sm">Dashboard</a>
                <a href="../support.php" class="btn btn-outline-light btn-sm">Suporte</a>
                <a href="../analytics.php" class="btn btn-outline-light btn-sm">ITIL</a>
                <span class="navbar-text text-white-50 small"><?= htmlspecialchars($user['name']) ?></span>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">Sair</a>
                <button id="themeToggle" class="btn-theme-toggle" title="Alternar tema"></button>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <a href="../index.php" class="btn btn-outline-secondary btn-sm mb-3">&larr; Voltar</a>
        <h4 class="mb-4">Painel Administrativo</h4>

        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-dark fw-bold fs-3"><?= $totalUsers ?></div>
                        <div class="small text-muted">Usuarios</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-primary fw-bold fs-3"><?= $totalTickets ?></div>
                        <div class="small text-muted">Total Tickets</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-danger fw-bold fs-3"><?= $openTickets ?></div>
                        <div class="small text-muted">Abertos</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-success fw-bold fs-3"><?= $resolvedTickets ?></div>
                        <div class="small text-muted">Resolvidos</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-info fw-bold fs-3"><?= $totalComments ?></div>
                        <div class="small text-muted">Comentarios</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-warning fw-bold fs-3"><?= $totalRatings ?></div>
                        <div class="small text-muted">Avaliacoes</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-warning fw-bold fs-3"><?= number_format($avgRating, 1) ?></div>
                        <div class="small text-muted">Media Avaliacao</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <span>Usuarios por Papel</span>
                        <a href="users.php" class="btn btn-sm btn-outline-light">Gerenciar</a>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <?php foreach ($usersByRole as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['role']) ?></td>
                                <td class="fw-bold"><?= $r['total'] ?></td>
                            </tr>
                            <?php endforeach ?>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white">Acoes Rápidas</div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="users.php" class="btn btn-outline-dark">Gerenciar Usuarios</a>
                            <a href="sectors.php" class="btn btn-outline-dark">Gerenciar Setores</a>
                            <a href="tickets.php" class="btn btn-outline-dark">Gerenciar Tickets</a>
                            <a href="categories.php" class="btn btn-outline-primary">Categorias e Subcategorias</a>
                            <a href="export.php" class="btn btn-outline-primary">Exportar Relatorios (CSV)</a>
                            <a href="audit.php" class="btn btn-outline-danger">Auditoria</a>
                            <a href="access_logs.php" class="btn btn-outline-info">Logs de Acesso</a>
                            <a href="../setup.php" class="btn btn-outline-dark">Diagnostico do Sistema</a>
                            <a href="../support.php" class="btn btn-outline-dark">Painel de Suporte</a>
                            <a href="../analytics.php" class="btn btn-outline-dark">ITIL Analytics</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/theme.js"></script>
    <script src="../assets/shortcuts.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
