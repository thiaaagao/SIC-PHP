<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Category.php';
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
        if (isset($_POST['add_category'])) {
            $name = trim($_POST['name'] ?? '');
            if (empty($name)) {
                $msg = 'Nome e obrigatorio.';
                $msgType = 'danger';
            } else {
                try {
                    Category::create($name);
                    AuditLog::log('category_create', 'category', null, "Categoria criada: $name");
                    $msg = "Categoria '$name' criada.";
                } catch (Exception $e) {
                    $msg = 'Erro: categoria ja existe.';
                    $msgType = 'danger';
                }
            }
        }

        if (isset($_POST['edit_category'])) {
            $id = (int) $_POST['category_id'];
            $name = trim($_POST['name'] ?? '');
            $active = isset($_POST['active']);
            if (empty($name)) {
                $msg = 'Nome e obrigatorio.';
                $msgType = 'danger';
            } else {
                Category::update($id, $name, $active);
                AuditLog::log('category_update', 'category', $id, "Categoria atualizada: $name");
                $msg = "Categoria atualizada.";
            }
        }

        if (isset($_POST['delete_category'])) {
            $id = (int) $_POST['category_id'];
            $cat = Category::getById($id);
            Category::delete($id);
            AuditLog::log('category_delete', 'category', $id, "Categoria excluida: " . ($cat['name'] ?? ''));
            $msg = "Categoria excluida.";
        }

        if (isset($_POST['add_sub'])) {
            $categoryId = (int) $_POST['category_id'];
            $name = trim($_POST['name'] ?? '');
            if (empty($name)) {
                $msg = 'Nome e obrigatorio.';
                $msgType = 'danger';
            } else {
                try {
                    Category::createSub($categoryId, $name);
                    AuditLog::log('subcategory_create', 'subcategory', null, "Subcategoria criada: $name");
                    $msg = "Subcategoria '$name' criada.";
                } catch (Exception $e) {
                    $msg = 'Erro: subcategoria ja existe.';
                    $msgType = 'danger';
                }
            }
        }

        if (isset($_POST['edit_sub'])) {
            $id = (int) $_POST['sub_id'];
            $name = trim($_POST['name'] ?? '');
            $active = isset($_POST['active']);
            if (empty($name)) {
                $msg = 'Nome e obrigatorio.';
                $msgType = 'danger';
            } else {
                Category::updateSub($id, $name, $active);
                AuditLog::log('subcategory_update', 'subcategory', $id, "Subcategoria atualizada: $name");
                $msg = "Subcategoria atualizada.";
            }
        }

        if (isset($_POST['delete_sub'])) {
            $id = (int) $_POST['sub_id'];
            Category::deleteSub($id);
            AuditLog::log('subcategory_delete', 'subcategory', $id, "Subcategoria excluida");
            $msg = "Subcategoria excluida.";
        }
    }
    header("Location: categories.php");
    exit;
}

$categories = Category::getAll();
$categoriesWithSubs = [];
foreach ($categories as $cat) {
    $categoriesWithSubs[$cat['id']] = $cat;
    $categoriesWithSubs[$cat['id']]['subs'] = Category::getSubcategories($cat['id']);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorias - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/theme.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand bg-dark navbar-dark">
        <div class="container">
            <span class="navbar-brand fw-bold">Gerenciar Categorias</span>
            <span class="navbar-text text-white-50 small"><?= htmlspecialchars($user['name']) ?></span>
            <div class="ms-auto d-flex gap-2 align-items-center">
                <a href="../support.php" class="btn btn-outline-light btn-sm">Suporte</a>
                <a href="index.php" class="btn btn-outline-light btn-sm">Admin</a>
                    <button id="themeToggle" class="btn-theme-toggle" title="Alternar tema"></button>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <a href="index.php" class="btn btn-outline-secondary btn-sm mb-3">&larr; Voltar</a>

        <?php if ($msg): ?><div class="alert alert-<?= $msgType ?> py-2"><?= htmlspecialchars($msg) ?></div><?php endif ?>

        <div class="row g-4">
            <div class="col-md-5">
                <div class="card shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Categorias</h5>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCatModal">+ Nova</button>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($categoriesWithSubs as $cat): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($cat['name']) ?></strong>
                                        <?php if (!$cat['active']): ?><span class="badge bg-secondary ms-1">Inativa</span><?php endif ?>
                                        <br><small class="text-muted"><?= count($cat['subs']) ?> subcategorias</small>
                                    </div>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editCat<?= $cat['id'] ?>">Editar</button>
                                        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delCat<?= $cat['id'] ?>">Excluir</button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-7">
                <div class="card shadow-sm">
                    <div class="card-header bg-white"><h5 class="mb-0">Subcategorias</h5></div>
                    <div class="card-body">
                        <?php foreach ($categoriesWithSubs as $cat): ?>
                        <div class="mb-3">
                            <h6 class="text-primary"><?= htmlspecialchars($cat['name']) ?></h6>
                            <?php if (empty($cat['subs'])): ?>
                                <small class="text-muted">Nenhuma subcategoria</small>
                            <?php else: ?>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($cat['subs'] as $sub): ?>
                                    <div class="btn-group btn-group-sm">
                                        <span class="btn btn-outline-secondary disabled <?= $sub['active'] ? '' : 'opacity-50' ?>"><?= htmlspecialchars($sub['name']) ?></span>
                                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editSub<?= $sub['id'] ?>">Editar</button>
                                        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delSub<?= $sub['id'] ?>">X</button>
                                    </div>
                                    <?php endforeach ?>
                                </div>
                            <?php endif ?>
                            <button class="btn btn-sm btn-outline-success mt-1" data-bs-toggle="modal" data-bs-target="#addSub<?= $cat['id'] ?>">+ Adicionar</button>
                        </div>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addCatModal" tabindex="-1">
        <div class="modal-dialog"><form method="post" class="modal-content">
            <?= Auth::csrfField() ?>
            <div class="modal-header"><h6 class="modal-title">Nova Categoria</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="text" name="name" class="form-control" required placeholder="Nome da categoria">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <input type="hidden" name="add_category" value="1">
                <button type="submit" class="btn btn-primary">Criar</button>
            </div>
        </form></div>
    </div>

    <?php foreach ($categoriesWithSubs as $cat): ?>
    <div class="modal fade" id="editCat<?= $cat['id'] ?>" tabindex="-1">
        <div class="modal-dialog"><form method="post" class="modal-content">
            <?= Auth::csrfField() ?>
            <div class="modal-header"><h6 class="modal-title">Editar <?= htmlspecialchars($cat['name']) ?></h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($cat['name']) ?>">
                <div class="form-check mt-2">
                    <input type="checkbox" name="active" class="form-check-input" id="activeCat<?= $cat['id'] ?>" <?= $cat['active'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="activeCat<?= $cat['id'] ?>">Ativa</label>
                </div>
            </div>
            <div class="modal-footer">
                <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                <input type="hidden" name="edit_category" value="1">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form></div>
    </div>

    <div class="modal fade" id="delCat<?= $cat['id'] ?>" tabindex="-1">
        <div class="modal-dialog"><form method="post" class="modal-content">
            <?= Auth::csrfField() ?>
            <div class="modal-header"><h6 class="modal-title">Excluir <?= htmlspecialchars($cat['name']) ?>?</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p>Tem certeza? Todas as subcategorias tambem serao excluidas.</p>
            </div>
            <div class="modal-footer">
                <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                <input type="hidden" name="delete_category" value="1">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-danger">Excluir</button>
            </div>
        </form></div>
    </div>

    <div class="modal fade" id="addSub<?= $cat['id'] ?>" tabindex="-1">
        <div class="modal-dialog"><form method="post" class="modal-content">
            <?= Auth::csrfField() ?>
            <div class="modal-header"><h6 class="modal-title">Nova Subcategoria em <?= htmlspecialchars($cat['name']) ?></h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="text" name="name" class="form-control" required placeholder="Nome da subcategoria">
            </div>
            <div class="modal-footer">
                <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                <input type="hidden" name="add_sub" value="1">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Criar</button>
            </div>
        </form></div>
    </div>

    <?php foreach ($cat['subs'] as $sub): ?>
    <div class="modal fade" id="editSub<?= $sub['id'] ?>" tabindex="-1">
        <div class="modal-dialog"><form method="post" class="modal-content">
            <?= Auth::csrfField() ?>
            <div class="modal-header"><h6 class="modal-title">Editar <?= htmlspecialchars($sub['name']) ?></h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($sub['name']) ?>">
                <div class="form-check mt-2">
                    <input type="checkbox" name="active" class="form-check-input" id="activeSub<?= $sub['id'] ?>" <?= $sub['active'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="activeSub<?= $sub['id'] ?>">Ativa</label>
                </div>
            </div>
            <div class="modal-footer">
                <input type="hidden" name="sub_id" value="<?= $sub['id'] ?>">
                <input type="hidden" name="edit_sub" value="1">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form></div>
    </div>

    <div class="modal fade" id="delSub<?= $sub['id'] ?>" tabindex="-1">
        <div class="modal-dialog"><form method="post" class="modal-content">
            <?= Auth::csrfField() ?>
            <div class="modal-header"><h6 class="modal-title">Excluir subcategoria?</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><p>Tem certeza?</p></div>
            <div class="modal-footer">
                <input type="hidden" name="sub_id" value="<?= $sub['id'] ?>">
                <input type="hidden" name="delete_sub" value="1">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-danger">Excluir</button>
            </div>
        </form></div>
    </div>
    <?php endforeach ?>
    <?php endforeach ?>

    <script src="../assets/toast.js"></script>
    <script src="../assets/app.js"></script>
    <script src="../assets/shortcuts.js"></script>
    <script src="../assets/theme.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
