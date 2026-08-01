<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/RateLimit.php';
require_once __DIR__ . '/../src/AuditLog.php';

session_start();
logAccess();

if (Auth::isLoggedIn()) {
    $role = Auth::getRole();
    header('Location: ' . ($role === 'encarregado' ? 'index.php' : 'support.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf()) {
        $error = 'Token CSRF invalido.';
    } else {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!RateLimit::check($clientIp, 5, 60)) {
            $error = 'Muitas tentativas. Aguarde 1 minuto.';
        } else {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            // Verificar status antes de tentar login
            $db = Database::getInstance();
            $checkStmt = $db->prepare("SELECT status, locked_until FROM users WHERE username = ?");
            $checkStmt->execute([$username]);
            $checkUser = $checkStmt->fetch();

            if ($checkUser) {
                if ($checkUser['status'] === 'inactive') {
                    $error = 'Conta desativada. Contate o administrador.';
                } elseif ($checkUser['status'] === 'locked') {
                    if ($checkUser['locked_until'] && strtotime($checkUser['locked_until']) > time()) {
                        $until = date('H:i', strtotime($checkUser['locked_until']));
                        $error = "Conta bloqueada ate $until. Aguarde ou contate o administrador.";
                    }
                }
            }

            if (!$error) {
                if (Auth::login($username, $password)) {
                    RateLimit::clear($clientIp);
                    AuditLog::log('login', 'user', $_SESSION['user']['id'] ?? null, "Login bem-sucedido: $username");
                    $role = Auth::getRole();
                    header('Location: ' . ($role === 'encarregado' ? 'index.php' : 'support.php'));
                    exit;
                }
                RateLimit::record($clientIp);
                AuditLog::log('login_failed', 'user', null, "Tentativa de login: $username");
                $error = 'Usuario ou senha invalidos.';
            }
        }
    }
}

$roleHint = $_GET['role'] ?? '';
$roleLabels = ['encarregado' => 'Encarregado', 'suporte_ti' => 'Suporte TI', 'admin' => 'Admin'];
$roleUsers = ['encarregado' => 'encarregado', 'suporte_ti' => 'suporte', 'admin' => 'admin'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - S.I.C.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/theme.css" rel="stylesheet">
    <link href="assets/toast.css" rel="stylesheet">
    <style>
        .role-btn { border: 2px solid transparent; transition: all 0.2s ease; }
        .role-btn:hover { transform: translateY(-1px); }
        .role-btn.active { border-color: currentColor; font-weight: 600; }
    </style>
</head>
<body class="d-flex align-items-center" style="min-height:100vh">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="text-center mb-4">
                    <h3 class="fw-bold">S.I.C.</h3>
                    <p class="text-muted">Acesse o sistema</p>
                </div>

                <div class="card shadow">
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <script>document.addEventListener('DOMContentLoaded', function(){ PS.toast('<?= addslashes(htmlspecialchars($error)) ?>', 'error'); });</script>
                        <?php endif ?>
                        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'password_changed'): ?>
                            <script>document.addEventListener('DOMContentLoaded', function(){ PS.toast('Senha alterada com sucesso! Faca login com a nova senha.', 'success', 5000); });</script>
                        <?php endif ?>

                        <?php if ($roleHint && isset($roleLabels[$roleHint])): ?>
                            <div class="d-flex align-items-center justify-content-between mb-3 p-2 rounded" style="background: var(--bg-secondary, #f8f9fa);">
                                <span class="badge bg-<?php
                                    echo $roleHint === 'encarregado' ? 'success' : ($roleHint === 'suporte_ti' ? 'primary' : 'dark');
                                ?> me-2 fs-6"><?= $roleLabels[$roleHint] ?></span>
                                <a href="login.php" class="btn btn-sm btn-outline-secondary">Trocar papel</a>
                            </div>
                        <?php else: ?>
                            <p class="text-muted small mb-2 text-center">Selecione seu papel:</p>
                            <div class="d-flex flex-wrap gap-2 mb-4">
                                <a href="?role=encarregado" class="btn btn-outline-success flex-fill role-btn">
                                    Encarregado
                                </a>
                                <a href="?role=suporte_ti" class="btn btn-outline-primary flex-fill role-btn">
                                    Suporte TI
                                </a>
                                <a href="?role=admin" class="btn btn-outline-dark flex-fill role-btn">
                                    Admin
                                </a>
                            </div>
                        <?php endif ?>

                        <?php if ($roleHint): ?>
                        <form method="post">
                            <?= Auth::csrfField() ?>
                            <div class="mb-3">
                                <label class="form-label">Usuario</label>
                                <input type="text" name="username" class="form-control" required autofocus
                                    value="<?= htmlspecialchars($roleUsers[$roleHint] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Senha</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Entrar</button>
                        </form>
                        <?php endif ?>
                    </div>
                </div>

                <div class="text-center mt-3">
                    <a href="open_ticket.php" class="text-decoration-none small">Abrir P.S. sem login</a>
                </div>
                <div class="text-center mt-3">
                    <button id="themeToggle" class="btn-theme-toggle" title="Alternar tema"></button>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/toast.js"></script>
    <script src="assets/app.js"></script>
    <script src="assets/theme.js"></script>
</body>
</html>
