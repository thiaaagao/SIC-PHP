<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';

session_start();
logAccess();
Auth::requireMinLevel('suporte_ti');

$db = Database::getInstance();
$action = $_GET['action'] ?? '';

if ($action === 'csv') {
    $status = $_GET['status'] ?? '';
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';

    $sql = "SELECT t.*, u.name as assigned_name FROM tickets t LEFT JOIN users u ON t.assigned_to = u.id WHERE 1=1";
    $params = [];

    if ($status) {
        $sql .= " AND t.status = ?";
        $params[] = $status;
    }
    if ($dateFrom) {
        $sql .= " AND t.created_at >= ?";
        $params[] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo) {
        $sql .= " AND t.created_at <= ?";
        $params[] = $dateTo . ' 23:59:59';
    }

    $sql .= " ORDER BY t.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="ps_tickets_' . date('Y-m-d_H-i') . '.csv"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, ['Codigo', 'Solicitante', 'Subcategoria', 'Prioridade', 'Setor', 'Conf', 'Hostname', 'IP', 'Status', 'SLA (h)', 'Atribuido', 'Criado em', 'Resolvido em'], ';');

    foreach ($tickets as $t) {
        $sla = round((strtotime($t['resolved_at'] ?: 'now') - strtotime($t['created_at'])) / 3600, 1);
        fputcsv($output, [
            $t['code'],
            $t['requester_name'],
            $t['subcategory'],
            $t['priority'] ?? 'medium',
            $t['setor'],
            $t['conf'],
            $t['hostname'],
            $t['ip'],
            $t['status'],
            $sla,
            $t['assigned_name'] ?? '',
            $t['created_at'],
            $t['resolved_at'] ?? ''
        ], ';');
    }

    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exportar Relatorios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/theme.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand bg-primary navbar-dark">
        <div class="container">
            <span class="navbar-brand fw-bold">Exportar Relatorios</span>
            <div class="ms-auto d-flex gap-2 align-items-center">
                <a href="../support.php" class="btn btn-outline-light btn-sm">Suporte</a>
                <a href="index.php" class="btn btn-outline-light btn-sm">Admin</a>
                <button id="themeToggle" class="btn-theme-toggle" title="Alternar tema"></button>
            </div>
        </div>
    </nav>
    <div class="container my-4">
        <a href="index.php" class="btn btn-outline-secondary btn-sm mb-3">&larr; Voltar</a>
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5>Exportar Tickets (CSV)</h5>
                        <form method="get" action="export.php">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">Todos</option>
                                    <option value="open">Abertos</option>
                                    <option value="in_progress">Em Andamento</option>
                                    <option value="resolved">Resolvidos</option>
                                    <option value="closed">Fechados</option>
                                </select>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label">De</label>
                                    <input type="date" name="date_from" class="form-control">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Ate</label>
                                    <input type="date" name="date_to" class="form-control">
                                </div>
                            </div>
                            <input type="hidden" name="action" value="csv">
                            <button type="submit" class="btn btn-primary w-100">Baixar CSV</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../assets/theme.js"></script>
    <script src="../assets/shortcuts.js"></script>
</body>
</html>
