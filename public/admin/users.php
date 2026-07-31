<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/AuditLog.php';

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
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $role = $_POST['role'] ?? '';

        if ($username && $password && $name && in_array($role, ['admin', 'suporte_ti', 'encarregado'])) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $st = $db->prepare("INSERT INTO users (username, password, name, role) VALUES (?, ?, ?, ?)");
            $st->execute([$username, $hash, $name, $role]);
            $msg = "Usuario {$username} criado.";
        } else { $msg = 'Preencha todos os campos.'; $msgType = 'danger'; }
    }

    if (isset($_POST['edit_user'])) {
        $id = (int)($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $role = $_POST['role'] ?? '';
        if ($id && $name && in_array($role, ['admin', 'suporte_ti', 'encarregado'])) {
            $st = $db->prepare("UPDATE users SET name = ?, role = ? WHERE id = ?");
            $st->execute([$name, $role, $id]);
            $msg = 'Usuario atualizado.';
        }
    }

    if (isset($_POST['delete_user'])) {
        $id = (int)($_POST['user_id'] ?? 0);
        if ($id && $id !== $user['id']) {
            $st = $db->prepare("SELECT name, role FROM users WHERE id = ?");
            $st->execute([$id]);
            $delUser = $st->fetch();
            if ($delUser) {
                if ($delUser['role'] === 'admin') {
                    $adminCount = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
                    if ($adminCount <= 1) {
                        $msg = 'Nao e possivel excluir o ultimo administrador.';
                        $msgType = 'danger';
                    } else {
                        $msg = 'Nao e possivel excluir outros administradores.';
                        $msgType = 'danger';
                    }
                } else {
                    $anonymizedName = 'Ex-Usuario-' . $id;
                    $db->prepare("UPDATE tickets SET requester_name = ? WHERE requester_name = ?")->execute([$anonymizedName, $delUser['name']]);
                    $db->prepare("UPDATE comments SET comment = '[REMOVIDO - LGPD]' WHERE user_id = ?")->execute([$id]);
                    $db->prepare("DELETE FROM ratings WHERE user_id = ?")->execute([$id]);
                    $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
                    AuditLog::log('user_delete', 'user', $id, "Usuario {$delUser['name']} anonimizado (LGPD)");
                    $msg = 'Usuario anonimizado e removido (LGPD).';
                }
            }
        } else { $msg = 'Nao pode excluir o proprio usuario.'; $msgType = 'danger'; }
    }

    if (isset($_POST['reset_password'])) {
        $id = (int)($_POST['user_id'] ?? 0);
        $newPass = $_POST['new_password'] ?? '';
        if ($id && $newPass) {
            $hash = password_hash($newPass, PASSWORD_BCRYPT);
            $st = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $st->execute([$hash, $id]);
            $msg = 'Senha redefinida.';
        }
    }
    }
}

$users = $db->query("SELECT * FROM users ORDER BY FIELD(role, 'admin', 'suporte_ti', 'encarregado'), name")->fetchAll();
$roles = ['admin' => 'Admin (Master)', 'suporte_ti' => 'Suporte TI', 'encarregado' => 'Encarregado'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Usuarios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/theme.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand bg-dark navbar-dark">
        <div class="container">
            <span class="navbar-brand fw-bold">Admin - Usuarios</span>
            <span class="navbar-text text-white-50 small"><?= htmlspecialchars($user['name']) ?></span>
            <div class="ms-auto d-flex gap-2 align-items-center">
                <a href="index.php" class="btn btn-outline-light btn-sm">Admin</a>
                <a href="tickets.php" class="btn btn-outline-light btn-sm">Tickets</a>
                    <button id="themeToggle" class="btn-theme-toggle" title="Alternar tema"></button>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <a href="index.php" class="btn btn-outline-secondary btn-sm mb-3">&larr; Voltar</a>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Gerenciar Usuarios</h4>
            <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addModal">+ Novo Usuario</button>
        </div>

        <?php if ($msg): ?><div class="alert alert-<?= $msgType ?> py-2"><?= $msg ?></div><?php endif ?>

        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr><th>ID</th><th>Usuario</th><th>Nome</th><th>Papel</th><th>Criado em</th><th>Acoes</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <td><?= htmlspecialchars($u['name']) ?></td>
                            <td><span class="badge bg-<?= $u['role'] === 'admin' ? 'dark' : ($u['role'] === 'suporte_ti' ? 'primary' : 'success') ?>"><?= $u['role'] ?></span></td>
                            <td><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                            <td class="text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $u['id'] ?>">Editar</button>
                                <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#resetModal<?= $u['id'] ?>">Senha</button>
                                <?php if ($u['id'] !== $user['id'] && $u['role'] !== 'admin'): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $u['id'] ?>">Excluir</button>
                                <?php endif ?>
                            </td>
                        </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog"><form method="post" class="modal-content">
            <?= Auth::csrfField() ?>
            <div class="modal-header"><h6 class="modal-title">Novo Usuario</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label">Usuario</label><input type="text" name="username" class="form-control" required></div>
                <div class="mb-2"><label class="form-label">Senha</label><input type="password" name="password" class="form-control" required></div>
                <div class="mb-2"><label class="form-label">Nome</label><input type="text" name="name" class="form-control" required></div>
                <div class="mb-2"><label class="form-label">Papel</label>
                    <select name="role" class="form-select" required>
                        <?php foreach ($roles as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <input type="hidden" name="add_user" value="1">
                <button type="submit" class="btn btn-dark">Criar</button>
            </div>
        </form></div>
    </div>

    <?php foreach ($users as $u): ?>
    <div class="modal fade" id="editModal<?= $u['id'] ?>" tabindex="-1">
        <div class="modal-dialog"><form method="post" class="modal-content">
            <?= Auth::csrfField() ?>
            <div class="modal-header"><h6 class="modal-title">Editar <?= htmlspecialchars($u['username']) ?></h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label">Nome</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($u['name']) ?>" required></div>
                <div class="mb-2"><label class="form-label">Papel</label>
                    <select name="role" class="form-select">
                        <?php foreach ($roles as $k => $v): ?><option value="<?= $k ?>" <?= $u['role'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <input type="hidden" name="edit_user" value="1">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form></div>
    </div>

    <div class="modal fade" id="resetModal<?= $u['id'] ?>" tabindex="-1">
        <div class="modal-dialog"><form method="post" class="modal-content">
            <?= Auth::csrfField() ?>
            <div class="modal-header"><h6 class="modal-title">Redefinir Senha - <?= htmlspecialchars($u['username']) ?></h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label">Nova Senha</label><input type="password" name="new_password" class="form-control" required></div>
            </div>
            <div class="modal-footer">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <input type="hidden" name="reset_password" value="1">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-warning">Redefinir</button>
            </div>
        </form></div>
    </div>

        <div class="modal fade" id="deleteModal<?= $u['id'] ?>" tabindex="-1">
        <div class="modal-dialog"><form method="post" class="modal-content">
            <?= Auth::csrfField() ?>
            <div class="modal-header"><h6 class="modal-title">Excluir <?= htmlspecialchars($u['username']) ?>?</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir <strong><?= htmlspecialchars($u['name']) ?></strong>?</p>
            </div>
            <div class="modal-footer">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <input type="hidden" name="delete_user" value="1">
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
