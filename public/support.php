<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/TeamsNotification.php';
require_once __DIR__ . '/../src/EmailNotification.php';
require_once __DIR__ . '/../src/AuditLog.php';
require_once __DIR__ . '/../src/Pagination.php';
require_once __DIR__ . '/../src/NavHelper.php';

session_start();
logAccess();
Auth::requireMinLevel('suporte_ti');

$db = Database::getInstance();
$user = Auth::getUser();
$isAdmin = Auth::isAdmin();

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$priorityFilter = $_GET['priority'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$assignedFilter = $_GET['assigned'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf()) {
        $success = 'Token CSRF invalido.';
    } elseif (isset($_POST['close_ticket'])) {
        $ticketId = (int) ($_POST['ticket_id'] ?? 0);
        $resolvedBy = $user['name'];

        $st = $db->prepare("UPDATE tickets SET status = 'resolved', resolved_at = NOW(), resolved_by = ? WHERE id = ? AND status IN ('open', 'in_progress')");
        $st->execute([$resolvedBy, $ticketId]);

        if ($st->rowCount() > 0) {
            $st = $db->prepare("SELECT * FROM tickets WHERE id = ?");
            $st->execute([$ticketId]);
            $ticket = $st->fetch();
            if ($ticket) {
                $ticket['user_name'] = $ticket['requester_name'];
                TeamsNotification::sendResolved($ticket);
                EmailNotification::notifyResolved($ticket, $user['name']);

                $comment = trim($_POST['resolution_comment'] ?? '');
                if ($comment) {
                    $st = $db->prepare("INSERT INTO comments (ticket_id, user_id, comment) VALUES (?, ?, ?)");
                    $st->execute([$ticketId, $user['id'], $comment]);
                }
                AuditLog::log('ticket_resolve', 'ticket', $ticketId, "Ticket {$ticket['code']} resolvido por " . $user['name']);
            }
            $success = "P.S. {$ticket['code']} resolvido.";
        }
    } elseif (isset($_POST['assign_ticket'])) {
        $assignId = (int) $_POST['assign_to'];
        $ticketId = (int) $_POST['ticket_id'];
        $st = $db->prepare("UPDATE tickets SET assigned_to = ? WHERE id = ?");
        $st->execute([$assignId ?: null, $ticketId]);
        $assignName = $assignId ? "ID $assignId" : "Ninguem";
        AuditLog::log('ticket_assign', 'ticket', $ticketId, "Atribuido para: $assignName por " . $user['name']);
        $success = 'Atribuicao atualizada.';
    }
    header('Location: support.php' . ($statusFilter ? "?status=$statusFilter" : ''));
    exit;
}

$supportUsers = $db->query("SELECT id, name FROM users WHERE role IN ('admin', 'suporte_ti') ORDER BY name")->fetchAll();

$where = "1=1";
$params = [];

if ($search) {
    $where .= " AND (t.requester_name LIKE ? OR t.hostname LIKE ? OR t.setor LIKE ? OR t.ip LIKE ? OR t.description LIKE ? OR t.code LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
}

if ($statusFilter) {
    $where .= " AND t.status = ?";
    $params[] = $statusFilter;
}

if ($priorityFilter) {
    $where .= " AND t.priority = ?";
    $params[] = $priorityFilter;
}

if ($categoryFilter) {
    $where .= " AND t.subcategory = ?";
    $params[] = $categoryFilter;
}

if ($assignedFilter) {
    if ($assignedFilter === '0') {
        $where .= " AND t.assigned_to IS NULL";
    } else {
        $where .= " AND t.assigned_to = ?";
        $params[] = $assignedFilter;
    }
}

if ($dateFrom) {
    $where .= " AND t.created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}

if ($dateTo) {
    $where .= " AND t.created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

$pager = Pagination::getParams(25);
$total = Pagination::getTotal($db, "SELECT t.* FROM tickets t WHERE $where", $params);
$totalPages = (int) ceil($total / $pager['perPage']);

$sql = "SELECT t.*, (SELECT COUNT(*) FROM comments WHERE ticket_id = t.id) as comment_count, u.name as assigned_name FROM tickets t LEFT JOIN users u ON t.assigned_to = u.id WHERE $where ORDER BY FIELD(t.status, 'open','in_progress','resolved','closed'), t.created_at DESC LIMIT {$pager['perPage']} OFFSET {$pager['offset']}";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$allTickets = $stmt->fetchAll();

$baseUrl = 'support.php';
$filterParams = [];
if ($search) $filterParams['search'] = $search;
if ($statusFilter) $filterParams['status'] = $statusFilter;
if ($priorityFilter) $filterParams['priority'] = $priorityFilter;
if ($categoryFilter) $filterParams['category'] = $categoryFilter;
if ($assignedFilter) $filterParams['assigned'] = $assignedFilter;
if ($dateFrom) $filterParams['date_from'] = $dateFrom;
if ($dateTo) $filterParams['date_to'] = $dateTo;
if ($filterParams) $baseUrl .= '?' . http_build_query($filterParams);

$badgeMap = ['open' => 'danger', 'in_progress' => 'warning text-dark', 'resolved' => 'success', 'closed' => 'secondary'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suporte - P.S. Profarma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/theme.css" rel="stylesheet">
    <link href="assets/toast.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand <?= Auth::navbarBg() ?> navbar-dark">
        <div class="container">
            <span class="navbar-brand fw-bold">P.S. Profarma - <?= $isAdmin ? 'Admin' : 'Suporte' ?><?= NavHelper::badge() ?></span>
            <div class="ms-auto d-flex gap-2 align-items-center">
                <a href="index.php" class="btn btn-outline-light btn-sm">Dashboard</a>
                <a href="analytics.php" class="btn btn-outline-light btn-sm">ITIL</a>
                <?php if ($isAdmin): ?>
                    <a href="admin/index.php" class="btn btn-outline-light btn-sm">Admin</a>
                <?php endif ?>
                <span class="navbar-text text-white-50 small"><?= htmlspecialchars($user['name']) ?></span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Sair</a>
                <button id="themeToggle" class="btn-theme-toggle" title="Alternar tema"></button>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <a href="index.php" class="btn btn-outline-secondary btn-sm mb-3">&larr; Voltar</a>
        <h4 class="mb-3">Todos os P.S.</h4>

        <?php if (isset($success)): ?>
            <script>document.addEventListener('DOMContentLoaded', function(){ PS.toast('<?= addslashes(htmlspecialchars($success)) ?>', 'success'); });</script>
        <?php endif ?>

        <form method="get" class="mb-3">
            <div class="row g-2">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Buscar..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Status</option>
                        <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Aberto</option>
                        <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>Andamento</option>
                        <option value="resolved" <?= $statusFilter === 'resolved' ? 'selected' : '' ?>>Resolvido</option>
                        <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Fechado</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="priority" class="form-select form-select-sm">
                        <option value="">Prioridade</option>
                        <option value="low" <?= $priorityFilter === 'low' ? 'selected' : '' ?>>Baixa</option>
                        <option value="medium" <?= $priorityFilter === 'medium' ? 'selected' : '' ?>>Media</option>
                        <option value="high" <?= $priorityFilter === 'high' ? 'selected' : '' ?>>Alta</option>
                        <option value="critical" <?= $priorityFilter === 'critical' ? 'selected' : '' ?>>Critica</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="category" class="form-select form-select-sm">
                        <option value="">Categoria</option>
                        <?php foreach (['Hardware','Software','Rede','Coletor','Outros'] as $cat): ?>
                            <option value="<?= $cat ?>" <?= $categoryFilter === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="assigned" class="form-select form-select-sm">
                        <option value="">Atribuido a</option>
                        <option value="0" <?= $assignedFilter === '0' ? 'selected' : '' ?>>Ninguem</option>
                        <?php foreach ($supportUsers as $su): ?>
                            <option value="<?= $su['id'] ?>" <?= $assignedFilter == $su['id'] ? 'selected' : '' ?>><?= htmlspecialchars($su['name']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-md-2">
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>" title="Data inicio">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>" title="Data fim">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-dark btn-sm w-100">Filtrar</button>
                </div>
                <div class="col-md-2">
                    <a href="support.php" class="btn btn-outline-secondary btn-sm w-100">Limpar</a>
                </div>
            </div>
        </form>

        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Codigo</th>
                            <th>Solicitante</th>
                            <th>Categoria</th>
                            <th>Prioridade</th>
                            <th>Atribuido</th>
                            <th>Hostname / IP</th>
                            <th>Setor / Conf</th>
                            <th>Status</th>
                            <th>SLA</th>
                            <th>Coment.</th>
                            <th>Abertura</th>
                            <th>Acao</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allTickets)): ?>
                            <tr><td colspan="12" class="text-center py-5">
                                <div class="text-muted mb-3" style="font-size:2.5rem">&#128269;</div>
                                <h5 class="text-muted">Nenhum P.S. encontrado</h5>
                                <p class="text-muted small">Tente ajustar os filtros de busca.</p>
                            </td></tr>
                        <?php else: ?>
                        <?php foreach ($allTickets as $t):
                        $priorityMap = ['low' => 'info', 'medium' => 'secondary', 'high' => 'warning text-dark', 'critical' => 'danger'];
                        $priorityLabel = ['low' => 'Baixa', 'medium' => 'Media', 'high' => 'Alta', 'critical' => 'Critica'];
                        ?>
                        <tr class="<?= $t['status'] === 'open' ? 'table-warning' : '' ?>">
                            <td class="fw-bold"><?= htmlspecialchars($t['code']) ?></td>
                            <td><?= htmlspecialchars($t['requester_name']) ?></td>
                            <td><?= htmlspecialchars($t['subcategory']) ?></td>
                            <td><span class="badge bg-<?= $priorityMap[$t['priority'] ?? 'medium'] ?>"><?= $priorityLabel[$t['priority'] ?? 'medium'] ?></span></td>
                            <td>
                                <form method="post" class="d-flex gap-1">
                                    <?= Auth::csrfField() ?>
                                    <select name="assign_to" class="form-select form-select-sm" style="width:100px" onchange="this.form.submit()">
                                        <option value="">Ninguem</option>
                                        <?php foreach ($supportUsers as $su): ?>
                                            <option value="<?= $su['id'] ?>" <?= ($t['assigned_to'] ?? '') == $su['id'] ? 'selected' : '' ?>><?= htmlspecialchars($su['name']) ?></option>
                                        <?php endforeach ?>
                                    </select>
                                    <input type="hidden" name="assign_ticket" value="1">
                                    <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                                </form>
                            </td>
                            <td><?= htmlspecialchars($t['hostname'] ?: '-') ?> / <?= htmlspecialchars($t['ip'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($t['setor'] ?: '-') ?> / <?= htmlspecialchars($t['conf'] ?: '-') ?></td>
                            <td><span class="badge bg-<?= $badgeMap[$t['status']] ?>"><?= $t['status'] ?></span></td>
                            <td class="text-nowrap <?php $s = getSlaStatus($t['created_at'], $t['resolved_at'], $t['priority'] ?? 'medium'); echo $s === 'breached' ? 'text-danger fw-bold' : ($s === 'warning' ? 'text-warning' : 'text-success'); ?>"><?= formatSlaElapsed($t['created_at'], $t['resolved_at']) ?></td>
                            <td><?= $t['comment_count'] ?></td>
                            <td class="text-nowrap"><?= date('d/m H:i', strtotime($t['created_at'])) ?></td>
                            <td class="text-nowrap">
                                <a href="ticket.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-secondary">Ver</a>
                                <?php if ($t['status'] === 'open' || $t['status'] === 'in_progress'): ?>
                                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#resolveModal<?= $t['id'] ?>">Resolver</button>

                                    <div class="modal fade" id="resolveModal<?= $t['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <form method="post" class="modal-content">
                                                <?= Auth::csrfField() ?>
                                                <div class="modal-header">
                                                    <h6 class="modal-title">Resolver <?= htmlspecialchars($t['code']) ?></h6>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p><strong>Solicitante:</strong> <?= htmlspecialchars($t['requester_name']) ?><br>
                                                    <strong>Problema:</strong> <?= htmlspecialchars($t['description']) ?></p>
                                                    <label class="form-label">Comentario da resolucao:</label>
                                                    <textarea name="resolution_comment" class="form-control" rows="3" placeholder="Descreva o que foi feito..."></textarea>
                                                </div>
                                                <div class="modal-footer">
                                                    <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                                                    <input type="hidden" name="close_ticket" value="1">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-success">Confirmar Resolucao</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                <?php endif ?>
                            </td>
                        </tr>
                        <?php endforeach ?>
                        <?php endif ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center mt-3">
            <small class="text-muted"><?= Pagination::info($total, $pager['offset'], $pager['perPage']) ?></small>
            <?= Pagination::render($pager['page'], $totalPages, $baseUrl) ?>
        </div>
        <?php endif ?>
    </div>
    <script src="assets/toast.js"></script>
    <script src="assets/shortcuts.js"></script>
    <script src="assets/app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
