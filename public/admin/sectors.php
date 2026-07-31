<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/AuditLog.php';
require_once __DIR__ . '/../../src/Sector.php';

session_start();
logAccess();
Auth::requireMinLevel('admin');

$db = Database::getInstance();
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf()) {
        $msg = 'Token CSRF invalido.';
        $msgType = 'danger';
    } elseif (isset($_POST['create_sector'])) {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $stmt = $db->prepare("SELECT id FROM sectors WHERE name = ?");
            $stmt->execute([$name]);
            $existing = $stmt->fetch();
            if ($existing) {
                $msg = 'Setor ja existe.';
                $msgType = 'warning';
            } else {
                Sector::create($name);
                AuditLog::log('category_create', 'sector', null, "Setor criado: $name");
                $msg = "Setor '$name' criado.";
            }
        } else {
            $msg = 'Nome e obrigatorio.';
            $msgType = 'danger';
        }
    } elseif (isset($_POST['update_sector'])) {
        $id = (int) ($_POST['sector_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $active = isset($_POST['active']);
        if ($id && $name) {
            Sector::update($id, $name, $active);
            AuditLog::log('category_update', 'sector', $id, "Setor atualizado: $name");
            $msg = 'Setor atualizado.';
        }
    } elseif (isset($_POST['delete_sector'])) {
        $id = (int) ($_POST['sector_id'] ?? 0);
        if ($id) {
            $sector = Sector::getById($id);
            if ($sector) {
                Sector::delete($id);
                AuditLog::log('category_delete', 'sector', $id, "Setor excluido: {$sector['name']}");
                $msg = "Setor '{$sector['name']}' excluido.";
            }
        }
    }
    header('Location: sectors.php');
    exit;
}

$sectors = Sector::getAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setores - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/theme.css" rel="stylesheet">
    <link href="../assets/toast.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand bg-dark navbar-dark">
        <div class="container">
            <span class="navbar-brand fw-bold">Gerenciar Setores</span>
            <div class="ms-auto d-flex gap-2 align-items-center">
                <a href="../support.php" class="btn btn-outline-light btn-sm">Suporte</a>
                <a href="index.php" class="btn btn-outline-light btn-sm">Admin</a>
                <a href="categories.php" class="btn btn-outline-light btn-sm">Categorias</a>
                <button id="themeToggle" class="btn-theme-toggle" title="Alternar tema"></button>
            </div>
        </div>
    </nav>
    <div class="container my-4">
        <a href="index.php" class="btn btn-outline-secondary btn-sm mb-3">&larr; Voltar</a>

        <?php if ($msg): ?>
            <script>document.addEventListener('DOMContentLoaded', function(){ PS.toast('<?= addslashes(htmlspecialchars($msg)) ?>', '<?= $msgType ?>'); });</script>
        <?php endif ?>

        <div class="row">
            <div class="col-md-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white"><h6 class="mb-0">Novo Setor</h6></div>
                    <div class="card-body">
                        <form method="post">
                            <?= Auth::csrfField() ?>
                            <div class="input-group">
                                <input type="text" name="name" class="form-control" placeholder="Nome do setor" required>
                                <input type="hidden" name="create_sector" value="1">
                                <button type="submit" class="btn btn-primary">Adicionar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Setores (<?= count($sectors) ?>)</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nome</th>
                                        <th>Status</th>
                                        <th>Criado em</th>
                                        <th>Acoes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($sectors)): ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">Nenhum setor cadastrado.</td></tr>
                                    <?php else: ?>
                                    <?php foreach ($sectors as $s): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($s['name']) ?></td>
                                        <td>
                                            <?php if ($s['active']): ?>
                                                <span class="badge bg-success">Ativo</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inativo</span>
                                            <?php endif ?>
                                        </td>
                                        <td class="text-muted small"><?= date('d/m/Y', strtotime($s['created_at'])) ?></td>
                                        <td class="text-nowrap">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $s['id'] ?>">Editar</button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $s['id'] ?>">Excluir</button>
                                        </td>
                                    </tr>

                                    <div class="modal fade" id="editModal<?= $s['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <form method="post" class="modal-content">
                                                <?= Auth::csrfField() ?>
                                                <div class="modal-header">
                                                    <h6 class="modal-title">Editar <?= htmlspecialchars($s['name']) ?></h6>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="sector_id" value="<?= $s['id'] ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Nome</label>
                                                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($s['name']) ?>" required>
                                                    </div>
                                                    <div class="form-check">
                                                        <input type="checkbox" name="active" class="form-check-input" <?= $s['active'] ? 'checked' : '' ?>>
                                                        <label class="form-check-label">Ativo</label>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                                    <input type="hidden" name="update_sector" value="1">
                                                    <button type="submit" class="btn btn-primary">Salvar</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    <div class="modal fade" id="deleteModal<?= $s['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <form method="post" class="modal-content">
                                                <?= Auth::csrfField() ?>
                                                <div class="modal-header">
                                                    <h6 class="modal-title">Excluir <?= htmlspecialchars($s['name']) ?>?</h6>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Tem certeza que deseja excluir este setor?</p>
                                                    <input type="hidden" name="sector_id" value="<?= $s['id'] ?>">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                                    <input type="hidden" name="delete_sector" value="1">
                                                    <button type="submit" class="btn btn-danger">Excluir</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    <?php endforeach ?>
                                    <?php endif ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../assets/toast.js"></script>
    <script src="../assets/app.js"></script>
    <script src="../assets/shortcuts.js"></script>
    <script src="../assets/theme.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
