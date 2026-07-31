<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';

session_start();
logAccess();
Auth::requireMinLevel('admin');

$db = Database::getInstance();
$user = Auth::getUser();
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf()) {
        $msg = 'Token CSRF invalido.';
        $msgType = 'danger';
    } else {
    if (isset($_POST['delete_ticket'])) {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $st = $db->prepare("DELETE FROM tickets WHERE id = ?");
        $st->execute([$ticketId]);
        $msg = "Ticket #{$ticketId} excluido.";
    }

    if (isset($_POST['update_status'])) {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (in_array($status, ['open', 'in_progress', 'resolved', 'closed'])) {
            $st = $db->prepare("UPDATE tickets SET status = ? WHERE id = ?");
            $st->execute([$status, $ticketId]);
            $msg = "Ticket #{$ticketId} atualizado para {$status}.";
        }
    }

    if (isset($_POST['edit_ticket'])) {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $requesterName = trim($_POST['requester_name'] ?? '');
        $subcategory = $_POST['subcategory'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $ip = trim($_POST['ip'] ?? '');
        $hostname = trim($_POST['hostname'] ?? '');
        $setor = trim($_POST['setor'] ?? '');
        $conf = trim($_POST['conf'] ?? '');

        $st = $db->prepare("UPDATE tickets SET requester_name = ?, subcategory = ?, description = ?, ip = ?, hostname = ?, setor = ?, conf = ? WHERE id = ?");
        $st->execute([$requesterName, $subcategory, $description, $ip, $hostname, $setor, $conf, $ticketId]);
        $msg = "Ticket #{$ticketId} atualizado.";
    }
    }
}

$search = trim($_GET['search'] ?? '');
$sql = "SELECT t.*, (SELECT COUNT(*) FROM comments WHERE ticket_id = t.id) as comment_count FROM tickets t";
$params = [];
if ($search) {
    $sql .= " WHERE t.code LIKE ? OR t.requester_name LIKE ? OR t.hostname LIKE ?";
    $like = "%{$search}%";
    $params = [$like, $like, $like];
}
$sql .= " ORDER BY t.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$subcategories = ['Hardware', 'Software', 'Rede', 'Coletor', 'Outros'];
$statuses = ['open', 'in_progress', 'resolved', 'closed'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/theme.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand bg-dark navbar-dark">
        <div class="container">
            <span class="navbar-brand fw-bold">Admin - Tickets</span>
            <span class="navbar-text text-white-50 small"><?= htmlspecialchars($user['name']) ?></span>
            <div class="ms-auto d-flex gap-2 align-items-center">
                <a href="index.php" class="btn btn-outline-light btn-sm">Admin</a>
                <a href="users.php" class="btn btn-outline-light btn-sm">Usuarios</a>
                    <button id="themeToggle" class="btn-theme-toggle" title="Alternar tema"></button>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <a href="index.php" class="btn btn-outline-secondary btn-sm mb-3">&larr; Voltar</a>
        <h4 class="mb-3">Gerenciar Tickets</h4>

        <?php if ($msg): ?><div class="alert alert-<?= $msgType ?> py-2"><?= $msg ?></div><?php endif ?>

        <form method="get" class="row g-2 mb-3">
            <div class="col-md-6">
                <input type="text" name="search" class="form-control" placeholder="Buscar por codigo, nome, hostname..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-dark w-100">Buscar</button>
            </div>
        </form>

        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Codigo</th>
                            <th>Solicitante</th>
                            <th>Categ.</th>
                            <th>Hostname / IP</th>
                            <th>Setor / Conf</th>
                            <th>Status</th>
                            <th>Coment.</th>
                            <th>Data</th>
                            <th>Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($t['code']) ?></td>
                            <td><?= htmlspecialchars($t['requester_name']) ?></td>
                            <td><?= htmlspecialchars($t['subcategory']) ?></td>
                            <td><?= htmlspecialchars($t['hostname'] ?: '-') ?> / <?= htmlspecialchars($t['ip'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($t['setor'] ?: '-') ?> / <?= htmlspecialchars($t['conf'] ?: '-') ?></td>
                            <td><span class="badge bg-<?= $t['status'] === 'open' ? 'danger' : ($t['status'] === 'in_progress' ? 'warning text-dark' : ($t['status'] === 'resolved' ? 'success' : 'secondary')) ?>"><?= $t['status'] ?></span></td>
                            <td><?= $t['comment_count'] ?></td>
                            <td class="text-nowrap"><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
                            <td class="text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $t['id'] ?>">Editar</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $t['id'] ?>">Excluir</button>
                            </td>
                        </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php foreach ($tickets as $t): ?>
    <div class="modal fade" id="editModal<?= $t['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg"><form method="post" class="modal-content">
            <?= Auth::csrfField() ?>
            <div class="modal-header"><h6 class="modal-title">Editar <?= htmlspecialchars($t['code']) ?></h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label">Solicitante</label>
                        <input type="text" name="requester_name" class="form-control" value="<?= htmlspecialchars($t['requester_name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Subcategoria</label>
                        <select name="subcategory" class="form-select">
                            <?php foreach ($subcategories as $s): ?>
                                <option value="<?= $s ?>" <?= $t['subcategory'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Hostname</label>
                        <input type="text" name="hostname" class="form-control" value="<?= htmlspecialchars($t['hostname']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">IP</label>
                        <input type="text" name="ip" class="form-control" value="<?= htmlspecialchars($t['ip']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" form="statusForm<?= $t['id'] ?>">
                            <?php foreach ($statuses as $s): ?>
                                <option value="<?= $s ?>" <?= $t['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descricao</label>
                        <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($t['description']) ?></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                <input type="hidden" name="edit_ticket" value="1">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form></div>
    </div>

    <div class="modal fade" id="deleteModal<?= $t['id'] ?>" tabindex="-1">
        <div class="modal-dialog"><form method="post" class="modal-content">
            <?= Auth::csrfField() ?>
            <div class="modal-header"><h6 class="modal-title">Excluir <?= htmlspecialchars($t['code']) ?>?</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p>Excluir permanentemente o ticket <strong><?= htmlspecialchars($t['code']) ?></strong>?</p>
                <p class="small text-muted">Comentarios e avaliacoes vinculadas serao removidos.</p>
            </div>
            <div class="modal-footer">
                <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                <input type="hidden" name="delete_ticket" value="1">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-danger">Excluir</button>
            </div>
        </form></div>
    </div>
    <?php endforeach ?>

    <script src="../assets/toast.js"></script>
    <script src="../assets/app.js"></script>
    <script src="../assets/shortcuts.js"></script>
    <script src="../assets/theme.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
