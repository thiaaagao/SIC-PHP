<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/AuditLog.php';

session_start();

if (!Auth::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = Auth::getUser();
$msg = '';
$msgType = 'danger';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf()) {
        $msg = 'Token CSRF invalido.';
    } else {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $userData = $stmt->fetch();

        if (!$userData || !password_verify($currentPass, $userData['password'])) {
            $msg = 'Senha atual incorreta.';
        } elseif ($newPass !== $confirmPass) {
            $msg = 'As senhas nao conferem.';
        } else {
            $errors = Auth::validatePassword($newPass);
            if ($errors) {
                $msg = 'Senha fraca: ' . implode(', ', $errors);
            } else {
                Auth::changePassword($user['id'], $newPass);
                AuditLog::log('password_change', 'user', $user['id'], "Senha alterada pelo usuario");
                session_regenerate_id(true);
                header('Location: login.php?msg=password_changed');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alterar Senha - S.I.C.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/theme.css" rel="stylesheet">
    <link href="assets/toast.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center" style="min-height:100vh">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="text-center mb-4">
                    <h3 class="fw-bold">S.I.C.</h3>
                    <p class="text-warning">Voce deve alterar sua senha para continuar</p>
                </div>
                <div class="card shadow">
                    <div class="card-body p-4">
                        <?php if ($msg): ?>
                            <script>document.addEventListener('DOMContentLoaded', function(){ PS.toast('<?= addslashes(htmlspecialchars($msg)) ?>', '<?= $msgType ?>'); });</script>
                        <?php endif ?>
                        <form method="post">
                            <?= Auth::csrfField() ?>
                            <div class="mb-3">
                                <label class="form-label">Senha Atual</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nova Senha</label>
                                <input type="password" name="new_password" class="form-control" required>
                                <small class="text-muted">Min 6 caracteres, 1 maiuscula, 1 minuscula, 1 numero</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirmar Nova Senha</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Alterar Senha</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/theme.js"></script>
    <script src="assets/toast.js"></script>
</body>
</html>
