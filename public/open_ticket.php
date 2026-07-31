<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/TeamsNotification.php';
require_once __DIR__ . '/../src/EmailNotification.php';
require_once __DIR__ . '/../src/Sector.php';
require_once __DIR__ . '/../src/GLPILookup.php';
require_once __DIR__ . '/../src/Network.php';
require_once __DIR__ . '/../src/AuditLog.php';
require_once __DIR__ . '/../src/Category.php';

session_start();
logAccess();
Auth::requireAccess();

$user = Auth::getUser();
$clientIp = Network::getClientIp();
$clientHostname = Network::getHostnameByIp($clientIp);

$error = '';
$success = '';

$db = Database::getInstance();
$categoriesWithSubs = Category::getAllWithSubs();
$categoryMap = [];
foreach ($categoriesWithSubs as $cat) {
    $categoryMap[$cat['name']] = array_column($cat['subs'], 'name');
}

$sectors = Sector::getActiveList();

$isVisitor = !$user;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf()) {
        $error = 'Token CSRF invalido.';
    } else {
    $requesterName = trim($_POST['requester_name'] ?? '');
    $ip = trim($_POST['ip'] ?? '');
    $hostname = trim($_POST['hostname'] ?? '');
    $conf = trim($_POST['conf'] ?? '');

    if ($isVisitor) {
        $subcategory = 'Outros';
        $description = 'Alerta via visitante';
        $setor = '';
        $honeypot = $_POST['website'] ?? '';

        if ($honeypot !== '') {
            $error = 'Erro de seguranca.';
        } elseif (empty($requesterName)) {
            $error = 'Informe seu nome.';
        } elseif (!preg_match('/^(0[1-9]|1[0-9]|2[0-5])$/', $conf)) {
            $error = 'Conf invalido. Digite um numero entre 01 e 25.';
        } elseif (!isset($_POST['consent'])) {
            $error = 'Voce deve aceitar a Politica de Privacidade.';
        } else {
            $db = Database::getInstance();
            $stmt = $db->prepare("INSERT INTO tickets (requester_name, subcategory, description, ip, hostname, setor, conf, priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$requesterName, $subcategory, $description, $ip, $hostname, $setor, $conf, 'medium']);
            $ticketId = $db->lastInsertId();
            AuditLog::log('ticket_create', 'ticket', $ticketId, "Ticket aberto por visitante: $requesterName");
        }
    } else {
        $subcategory = $_POST['subcategory'] ?? '';
        $problem = $_POST['problem'] ?? '';
        $customDescription = trim($_POST['custom_description'] ?? '');
        $setor = $_POST['setor'] ?? '';

        $validSubs = array_keys($categoryMap);

        if (empty($requesterName)) {
            $error = 'Informe seu nome.';
        } elseif (!in_array($subcategory, $validSubs)) {
            $error = 'Selecione uma categoria.';
        } elseif (empty($problem)) {
            $error = 'Selecione uma subcategoria.';
        } elseif ($problem === 'Outros' && empty($customDescription)) {
            $error = 'Descreva o problema.';
        } elseif (empty($hostname)) {
            $error = 'Informe o hostname.';
        } elseif (!isset($_POST['consent'])) {
            $error = 'Voce deve aceitar a Politica de Privacidade.';
        } else {
            $description = $problem === 'Outros' ? $customDescription : $problem;
            $priority = $_POST['priority'] ?? 'medium';
            if (!in_array($priority, ['low', 'medium', 'high', 'critical'])) $priority = 'medium';

            $db = Database::getInstance();
            $stmt = $db->prepare("INSERT INTO tickets (requester_name, subcategory, description, ip, hostname, setor, conf, priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$requesterName, $subcategory, $description, $ip, $hostname, $setor, $conf, $priority]);
            $ticketId = $db->lastInsertId();
        }
    }

    if (empty($error) && isset($ticketId)) {

        $code = 'PS-' . str_pad($ticketId, 4, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("UPDATE tickets SET code = ? WHERE id = ?");
        $stmt->execute([$code, $ticketId]);
        AuditLog::log('ticket_create', 'ticket', $ticketId, "Ticket $code aberto por: $requesterName");

        TeamsNotification::sendNewTicket([
            'id' => $ticketId,
            'code' => $code,
            'subcategory' => $subcategory,
            'description' => $description,
            'ip' => $ip,
            'hostname' => $hostname,
            'setor' => $setor,
            'conf' => $conf,
            'user_name' => $requesterName,
        ]);

        EmailNotification::notifyNewTicket([
            'id' => $ticketId,
            'code' => $code,
            'requester_name' => $requesterName,
            'subcategory' => $subcategory,
            'priority' => $priority,
            'description' => $description,
        ]);

        $success = "P.S. {$code} aberto com sucesso!";
    }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isVisitor ? 'Alerta Rapido' : 'Abrir P.S.' ?> - S.I.C.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/toast.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand <?= $isVisitor ? 'bg-secondary' : Auth::navbarBg() ?> navbar-dark">
        <div class="container">
            <span class="navbar-brand fw-bold"><?= $isVisitor ? 'Alerta Rapido' : 'Abrir P.S.' ?></span>
            <div class="ms-auto d-flex gap-2">
                <a href="index.php" class="btn btn-outline-light btn-sm">Dashboard</a>
                <?php if (Auth::canViewAnalytics()): ?>
                    <a href="analytics.php" class="btn btn-outline-light btn-sm">ITIL</a>
                <?php endif ?>
                <?php if (Auth::isAdmin()): ?>
                    <a href="admin/index.php" class="btn btn-outline-light btn-sm">Admin</a>
                <?php endif ?>
                <?php if ($user): ?>
                    <a href="logout.php" class="btn btn-outline-light btn-sm">Sair</a>
                <?php endif ?>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <a href="index.php" class="btn btn-outline-secondary btn-sm mb-3">&larr; Voltar</a>

        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">

                <?php if ($error): ?>
                    <script>document.addEventListener('DOMContentLoaded', function(){ PS.toast('<?= addslashes(htmlspecialchars($error)) ?>', 'error'); });</script>
                <?php endif ?>

                <?php if ($success): ?>
                    <script>document.addEventListener('DOMContentLoaded', function(){ PS.toast('<?= addslashes(htmlspecialchars($success)) ?> A equipe de suporte ja foi notificada.', 'success', 6000); });</script>
                <?php endif ?>

                <?php if ($isVisitor): ?>
                <div class="card shadow-sm border-primary">
                    <div class="card-body text-center py-4">
                        <p class="text-muted mb-3">Seu IP e hostname serao capturados automaticamente.</p>
                        <form method="post">
                            <?= Auth::csrfField() ?>
                            <div class="mb-3">
                                <input type="text" name="requester_name" class="form-control form-control-lg text-center" required placeholder="Seu nome"
                                    value="<?= htmlspecialchars($_POST['requester_name'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text">Conf</span>
                                    <input type="text" name="conf" class="form-control text-center" required placeholder="01 a 25" maxlength="2"
                                        value="<?= htmlspecialchars($_POST['conf'] ?? '') ?>"
                                        oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,2)">
                                </div>
                            </div>
                            <div style="position:absolute;left:-9999px" aria-hidden="true">
                                <input type="text" name="website" tabindex="-1" autocomplete="off" value="">
                            </div>
                            <input type="hidden" name="hostname" value="<?= htmlspecialchars($clientHostname) ?>">
                            <input type="hidden" name="ip" value="<?= htmlspecialchars($clientIp) ?>">
                            <div class="mb-3 form-check text-start">
                                <input type="checkbox" name="consent" class="form-check-input" id="consentVisitor" checked required>
                                <label class="form-check-label" for="consentVisitor">Li e aceito a <a href="privacy.php" target="_blank">Politica de Privacidade</a></label>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg w-100">Enviar Alerta</button>
                        </form>
                        <div class="mt-3 small text-muted">
                            Hostname: <?= htmlspecialchars($clientHostname ?: 'nao detectado') ?> &bull; IP: <?= htmlspecialchars($clientIp) ?>
                        </div>
                    </div>
                </div>

                <?php else: ?>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <form method="post">
                            <?= Auth::csrfField() ?>
                            <div class="mb-3">
                                <label class="form-label">Seu nome *</label>
                                <input type="text" name="requester_name" class="form-control" required
                                    value="<?= htmlspecialchars($_POST['requester_name'] ?? $user['name']) ?>" readonly>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Categoria *</label>
                                <select name="subcategory" id="subcategory" class="form-select" required onchange="updateProblems()">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($categoriesWithSubs as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat['name']) ?>" <?= ($_POST['subcategory'] ?? '') === $cat['name'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Subcategoria *</label>
                                <select name="problem" id="problem" class="form-select" required onchange="toggleCustom()">
                                    <option value="">Selecione a categoria primeiro...</option>
                                </select>
                                <div id="customGroup" class="mt-2 d-none">
                                    <textarea name="custom_description" class="form-control" rows="3" placeholder="Descreva o problema..."><?= htmlspecialchars($_POST['custom_description'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Setor</label>
                                    <select name="setor" class="form-select">
                                        <option value="">Selecione...</option>
                                        <?php foreach ($sectors as $s): ?>
                                            <option value="<?= htmlspecialchars($s) ?>" <?= ($_POST['setor'] ?? '') === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                                        <?php endforeach ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Conf (01-25)</label>
                                    <input type="text" name="conf" class="form-control" placeholder="Ex: 05" maxlength="2" value="<?= htmlspecialchars($_POST['conf'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Prioridade</label>
                                <select name="priority" class="form-select">
                                    <option value="low">Baixa</option>
                                    <option value="medium" selected>Media</option>
                                    <option value="high">Alta</option>
                                    <option value="critical">Critica</option>
                                </select>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Hostname *</label>
                                    <input type="text" name="hostname" class="form-control" required placeholder="Ex: COL-001" value="<?= htmlspecialchars($_POST['hostname'] ?? $clientHostname) ?>">
                                    <div class="form-text">Detectado: <?= htmlspecialchars($clientHostname ?: 'nao detectado') ?></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">IP</label>
                                    <input type="text" name="ip" class="form-control" placeholder="Ex: 10.0.0.100" value="<?= htmlspecialchars($_POST['ip'] ?? $clientIp) ?>">
                                    <div class="form-text">Seu IP: <?= htmlspecialchars($clientIp) ?></div>
                                </div>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" name="consent" class="form-check-input" id="consent" required>
                                <label class="form-check-label" for="consent">Li e aceito a <a href="privacy.php" target="_blank">Politica de Privacidade</a></label>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Abrir P.S.</button>
                        </form>
                    </div>
                </div>
                <?php endif ?>
            </div>
        </div>
    </div>

    <?php if (!$isVisitor): ?>
    <script>
        const categories = <?= json_encode($categoryMap) ?>;
        function updateProblems() {
            const cat = document.getElementById('subcategory').value;
            const sel = document.getElementById('problem');
            sel.innerHTML = '<option value="">Selecione...</option>';
            document.getElementById('customGroup').classList.add('d-none');
            if (cat && categories[cat]) {
                categories[cat].forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s; opt.textContent = s;
                    sel.appendChild(opt);
                });
            }
        }
        function toggleCustom() {
            document.getElementById('customGroup').classList.toggle('d-none', document.getElementById('problem').value !== 'Outros');
        }
        <?php if (!empty($_POST['subcategory'])): ?>
        document.getElementById('subcategory').value = '<?= addslashes(htmlspecialchars($_POST['subcategory'] ?? '')) ?>';
        updateProblems();
        document.getElementById('problem').value = '<?= addslashes(htmlspecialchars($_POST['problem'] ?? '')) ?>';
        toggleCustom();
        <?php endif ?>
    </script>
    <?php endif ?>
    <div class="text-center mt-4 mb-3">
        <a href="privacy.php" class="text-muted small">Politica de Privacidade</a>
    </div>
    <script src="assets/toast.js"></script>
    <script src="assets/app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
