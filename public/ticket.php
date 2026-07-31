<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/AuditLog.php';
require_once __DIR__ . '/../src/NavHelper.php';

session_start();
logAccess();
Auth::requireAccess();

$db = Database::getInstance();
$user = Auth::getUser();

$ticketId = (int) ($_GET['id'] ?? 0);
if (!$ticketId) { header('Location: index.php'); exit; }

$stmt = $db->prepare("SELECT * FROM tickets WHERE id = ?");
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) { header('Location: index.php'); exit; }

$stmtComment = $db->prepare("SELECT c.*, u.name as user_name, u.role FROM comments c JOIN users u ON c.user_id = u.id WHERE c.ticket_id = ? ORDER BY c.created_at");
$stmtComment->execute([$ticketId]);
$comments = $stmtComment->fetchAll();

$stmtRating = $db->prepare("SELECT * FROM ratings WHERE ticket_id = ?");
$stmtRating->execute([$ticketId]);
$ratings = $stmtRating->fetchAll();
$userRating = $user ? current(array_filter($ratings, fn($r) => $r['user_id'] == $user['id'])) : null;

$avgRating = count($ratings) > 0 ? round(array_sum(array_column($ratings, 'rating')) / count($ratings), 1) : null;

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    if (!Auth::validateCsrf()) {
        $msg = 'Token CSRF invalido.';
    } else {
    if (isset($_POST['add_comment']) && Auth::canResolve()) {
        $comment = trim($_POST['comment'] ?? '');
        if ($comment) {
            $st = $db->prepare("INSERT INTO comments (ticket_id, user_id, comment) VALUES (?, ?, ?)");
            $st->execute([$ticketId, $user['id'], $comment]);
            AuditLog::log('comment_add', 'ticket', $ticketId, "Comentario adicionado por " . $user['name']);
            $msg = 'Comentario adicionado.';
        }
    }
    if (isset($_POST['add_rating']) && Auth::canEvaluate() && $ticket['status'] === 'resolved') {
        $rating = (int) ($_POST['rating'] ?? 0);
        if ($rating >= 1 && $rating <= 5) {
            $st = $db->prepare("INSERT INTO ratings (ticket_id, user_id, rating) VALUES (?, ?, ?)");
            $st->execute([$ticketId, $user['id'], $rating]);
            AuditLog::log('ticket_rate', 'ticket', $ticketId, "Nota $rating por " . $user['name']);
            $msg = 'Avaliacao registrada.';
        }
    }
    if (isset($_POST['refresh_hostname']) && Auth::canResolve() && empty($ticket['hostname'])) {
        require_once __DIR__ . '/../src/GLPILookup.php';
        $newHostname = GLPILookup::getHostnameByIp($ticket['ip']);
        if ($newHostname) {
            $st = $db->prepare("UPDATE tickets SET hostname = ? WHERE id = ?");
            $st->execute([$newHostname, $ticketId]);
            AuditLog::log('ticket_update', 'ticket', $ticketId, "Hostname atualizado: $newHostname");
            $msg = "Hostname atualizado: $newHostname";
        } else {
            $msg = 'Nenhum hostname encontrado no GLPI para este IP.';
        }
    }
    if (isset($_POST['update_priority']) && Auth::canResolve()) {
        $newPriority = $_POST['priority'] ?? 'medium';
        if (in_array($newPriority, ['low', 'medium', 'high', 'critical'])) {
            $oldPriority = $ticket['priority'] ?? 'medium';
            $st = $db->prepare("UPDATE tickets SET priority = ? WHERE id = ?");
            $st->execute([$newPriority, $ticketId]);
            AuditLog::log('ticket_priority', 'ticket', $ticketId, "Prioridade: $oldPriority -> $newPriority");
            $msg = 'Prioridade atualizada.';
        }
    }
    if (isset($_POST['update_assignment']) && Auth::canResolve()) {
        $assignId = (int) ($_POST['assign_to'] ?? 0);
        $st = $db->prepare("UPDATE tickets SET assigned_to = ? WHERE id = ?");
        $st->execute([$assignId ?: null, $ticketId]);
        $assignName = $assignId ? "ID $assignId" : "Ninguem";
        AuditLog::log('ticket_assign', 'ticket', $ticketId, "Atribuido para: $assignName");
        $msg = 'Atribuicao atualizada.';
    }
    if (isset($_POST['upload_attachment']) && Auth::canResolve()) {
        if (empty($_FILES['attachment']['name']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
            $msg = 'Erro ao enviar arquivo. Tente novamente.';
        } else {
            $file = $_FILES['attachment'];
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt'];
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/plain'];
            $maxSize = 5 * 1024 * 1024;

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExts)) {
                $msg = 'Formato nao permitido.' . htmlspecialchars($ext) . '. Use: JPG, PNG, GIF, PDF, TXT.';
            } elseif ($file['size'] > $maxSize) {
                $msg = 'Arquivo muito grande (' . round($file['size'] / 1024 / 1024, 1) . 'MB). Maximo: 5MB.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $detectedMime = $finfo->file($file['tmp_name']);
                if (!in_array($detectedMime, $allowedMimes)) {
                    $msg = 'Tipo de arquivo nao permitido (' . $detectedMime . ').';
                } else {
                    $safeFilename = uniqid('att_') . '.' . $ext;
                    $uploadPath = __DIR__ . '/../storage/uploads/' . $safeFilename;

                    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                        $st = $db->prepare("INSERT INTO ticket_attachments (ticket_id, filename, original_name, mime_type, size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
                        $st->execute([$ticketId, $safeFilename, $file['name'], $detectedMime, $file['size'], $user['id']]);
                        $msg = 'Anexo adicionado.';
                    } else {
                        $msg = 'Erro ao salvar arquivo no servidor.';
                    }
                }
            }
        }
    }
    }
    header("Location: ticket.php?id=$ticketId");
    exit;
}

$badgeMap = ['open' => 'danger', 'in_progress' => 'warning text-dark', 'resolved' => 'success', 'closed' => 'secondary'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($ticket['code']) ?> - S.I.C.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/theme.css" rel="stylesheet">
    <link href="assets/toast.css" rel="stylesheet">
    <style>.star { font-size:1.8rem; cursor:pointer; color:#ccc; } .star.active { color:#ffc107; } .star:hover { color:#ffc107; }</style>
</head>
<body>
    <nav class="navbar navbar-expand <?= Auth::navbarBg() ?> navbar-dark">
        <div class="container">
            <span class="navbar-brand fw-bold"><?= htmlspecialchars($ticket['code']) ?><?= NavHelper::badge() ?></span>
            <div class="ms-auto d-flex gap-2 align-items-center">
                <a href="index.php" class="btn btn-outline-light btn-sm">Dashboard</a>
                <?php if (Auth::canResolve()): ?>
                    <a href="support.php" class="btn btn-outline-light btn-sm">Suporte</a>
                <?php endif ?>
                <?php if (Auth::canViewAnalytics()): ?>
                    <a href="analytics.php" class="btn btn-outline-light btn-sm">ITIL</a>
                <?php endif ?>
                <?php if (Auth::isAdmin()): ?>
                    <a href="admin/index.php" class="btn btn-outline-light btn-sm">Admin</a>
                <?php endif ?>
                <?php if ($user): ?>
                    <a href="logout.php" class="btn btn-outline-light btn-sm">Sair</a>
                <?php endif ?>
                <button id="themeToggle" class="btn-theme-toggle" title="Alternar tema"></button>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <a href="<?= Auth::canResolve() ? 'support.php' : 'index.php' ?>" class="btn btn-secondary btn-sm mb-3" id="btnVoltar">&larr; Voltar <kbd class="ms-1" style="font-size:0.65rem">ESC</kbd></a>
        <a href="ticket_print.php?id=<?= $ticketId ?>" target="_blank" class="btn btn-outline-secondary btn-sm mb-3">&#128424; Imprimir</a>

        <div class="row justify-content-center">
            <div class="col-md-8">

                <?php if ($msg): ?><script>document.addEventListener('DOMContentLoaded', function(){ PS.toast('<?= addslashes(htmlspecialchars($msg)) ?>', 'success'); });</script><?php endif ?>

                <?php
                $priorityMap = ['low' => 'info', 'medium' => 'secondary', 'high' => 'warning text-dark', 'critical' => 'danger'];
                $priorityLabel = ['low' => 'Baixa', 'medium' => 'Media', 'high' => 'Alta', 'critical' => 'Critica'];
                ?>
                <div class="d-flex align-items-center gap-3 mb-4">
                    <h3 class="mb-0"><?= htmlspecialchars($ticket['code']) ?></h3>
                    <span class="badge bg-<?= $priorityMap[$ticket['priority'] ?? 'medium'] ?> fs-6"><?= $priorityLabel[$ticket['priority'] ?? 'medium'] ?></span>
                    <span class="badge bg-<?= $badgeMap[$ticket['status']] ?> fs-6"><?= $ticket['status'] ?></span>
                    <?php if ($avgRating): ?>
                        <span class="ms-auto text-warning"><?= str_repeat('&#9733;', (int)$avgRating) . str_repeat('&#9734;', 5 - (int)$avgRating) ?> <?= $avgRating ?></span>
                    <?php endif ?>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6"><small class="text-muted d-block">Solicitante</small><strong><?= htmlspecialchars($ticket['requester_name']) ?></strong></div>
                            <div class="col-sm-6"><small class="text-muted d-block">Categoria</small><strong>Suporte Tecnico / <?= htmlspecialchars($ticket['subcategory']) ?></strong></div>
                            <div class="col-sm-6"><small class="text-muted d-block">Setor</small><strong><?= htmlspecialchars($ticket['setor'] ?: '-') ?></strong></div>
                            <div class="col-sm-6"><small class="text-muted d-block">Conf</small><strong><?= htmlspecialchars($ticket['conf'] ?: '-') ?></strong></div>
                            <div class="col-sm-6"><small class="text-muted d-block">Hostname</small><strong><?= htmlspecialchars($ticket['hostname'] ?: '-') ?></strong>
                                <?php if (empty($ticket['hostname']) && $user && Auth::canResolve()): ?>
                                <form method="post" class="d-inline">
                                    <?= Auth::csrfField() ?>
                                    <input type="hidden" name="refresh_hostname" value="1">
                                    <button type="submit" class="btn btn-outline-primary btn-sm mt-1">Atualizar dados</button>
                                </form>
                                <?php endif ?>
                            </div>
                            <div class="col-sm-6"><small class="text-muted d-block">IP</small><strong><?= htmlspecialchars($ticket['ip'] ?: '-') ?></strong></div>
                            <?php if ($user && Auth::canResolve()): ?>
                            <div class="col-sm-6">
                                <small class="text-muted d-block">Prioridade</small>
                                <form method="post" class="d-flex gap-1 align-items-center">
                                    <?= Auth::csrfField() ?>
                                    <select name="priority" class="form-select form-select-sm" style="width:auto">
                                        <option value="low" <?= ($ticket['priority'] ?? 'medium') === 'low' ? 'selected' : '' ?>>Baixa</option>
                                        <option value="medium" <?= ($ticket['priority'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>Media</option>
                                        <option value="high" <?= ($ticket['priority'] ?? 'medium') === 'high' ? 'selected' : '' ?>>Alta</option>
                                        <option value="critical" <?= ($ticket['priority'] ?? 'medium') === 'critical' ? 'selected' : '' ?>>Critica</option>
                                    </select>
                                    <input type="hidden" name="update_priority" value="1">
                                    <button type="submit" class="btn btn-outline-secondary btn-sm">Salvar</button>
                                </form>
                            </div>
                            <?php else: ?>
                            <div class="col-sm-6"><small class="text-muted d-block">Prioridade</small><strong><?= $priorityLabel[$ticket['priority'] ?? 'medium'] ?></strong></div>
                            <?php endif ?>
                            <div class="col-sm-6"><small class="text-muted d-block">Aberto em</small><strong><?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?></strong></div>
                            <?php
                            $supportUsers = $db->query("SELECT id, name FROM users WHERE role IN ('admin', 'suporte_ti') ORDER BY name")->fetchAll();
                            ?>
                            <?php if ($user && Auth::canResolve()): ?>
                            <div class="col-sm-6">
                                <small class="text-muted d-block">Atribuido a</small>
                                <form method="post" class="d-flex gap-1 align-items-center">
                                    <?= Auth::csrfField() ?>
                                    <select name="assign_to" class="form-select form-select-sm" style="width:auto">
                                        <option value="">Ninguem</option>
                                        <?php foreach ($supportUsers as $su): ?>
                                            <option value="<?= $su['id'] ?>" <?= ($ticket['assigned_to'] ?? '') == $su['id'] ? 'selected' : '' ?>><?= htmlspecialchars($su['name']) ?></option>
                                        <?php endforeach ?>
                                    </select>
                                    <input type="hidden" name="update_assignment" value="1">
                                    <button type="submit" class="btn btn-outline-secondary btn-sm">Salvar</button>
                                </form>
                            </div>
                            <?php else: ?>
                            <div class="col-sm-6"><small class="text-muted d-block">Atribuido a</small><strong><?= htmlspecialchars($ticket['assigned_name'] ?? 'Ninguem') ?></strong></div>
                            <?php endif ?>
                            <?php if ($ticket['resolved_at']): ?>
                            <div class="col-sm-6"><small class="text-muted d-block">Resolvido em</small><strong><?= date('d/m/Y H:i', strtotime($ticket['resolved_at'])) ?></strong></div>
                            <div class="col-sm-6"><small class="text-muted d-block">Resolvido por</small><strong><?= htmlspecialchars($ticket['resolved_by'] ?: '-') ?></strong></div>
                            <div class="col-sm-6">
                                <small class="text-muted d-block">Tempo total</small>
                                <strong><?= formatSlaElapsed($ticket['created_at'], $ticket['resolved_at']) ?></strong>
                            </div>
                            <?php else: ?>
                            <div class="col-sm-6">
                                <small class="text-muted d-block">SLA</small>
                                <strong id="slaTimer"><?= formatSlaElapsed($ticket['created_at']) ?></strong>
                            </div>
                            <?php endif ?>
                        </div>
                        <hr>
                        <h6>Descricao</h6>
                        <p class="mb-0 text-muted"><?= nl2br(htmlspecialchars($ticket['description'])) ?></p>
                    </div>
                </div>

                <?php if ($user && Auth::canEvaluate() && $ticket['status'] === 'resolved' && !$userRating): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h6>Avaliar Resolucao</h6>
                        <form method="post">
                            <?= Auth::csrfField() ?>
                            <div class="mb-2" id="starRating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span class="star" data-value="<?= $i ?>" onclick="setRating(<?= $i ?>)">&#9734;</span>
                                <?php endfor ?>
                            </div>
                            <input type="hidden" name="rating" id="ratingValue" value="0">
                            <input type="hidden" name="add_rating" value="1">
                            <button type="submit" class="btn btn-success btn-sm" id="rateBtn" disabled>Avaliar</button>
                        </form>
                    </div>
                </div>
                <?php endif ?>

                <?php if ($user && Auth::canResolve() && $ticket['status'] === 'resolved'): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h6>Comentar</h6>
                        <form method="post">
                            <?= Auth::csrfField() ?>
                            <textarea name="comment" class="form-control mb-2" rows="2" placeholder="Descreva o que foi feito..."></textarea>
                            <input type="hidden" name="add_comment" value="1">
                            <button type="submit" class="btn btn-primary btn-sm">Adicionar Comentario</button>
                        </form>
                    </div>
                </div>
                <?php endif ?>

                <?php if ($user && Auth::canResolve()): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h6>Anexar Arquivo</h6>
                        <form method="post" enctype="multipart/form-data">
                            <?= Auth::csrfField() ?>
                            <div id="dropZone" style="border:2px dashed #dee2e6; border-radius:8px; padding:20px; text-align:center; cursor:pointer; transition:border-color .2s">
                                <div class="text-muted mb-2" style="font-size:1.5rem">&#128206;</div>
                                <p class="text-muted small mb-1">Arraste um arquivo aqui ou clique para selecionar</p>
                                <p class="text-muted small mb-0">Max 5MB. JPG, PNG, GIF, PDF, TXT</p>
                            </div>
                            <input type="file" name="attachment" id="fileInput" class="d-none" accept=".jpg,.jpeg,.png,.gif,.pdf,.txt">
                            <div id="dropPreview" class="mt-2" style="display:none"></div>
                            <input type="hidden" name="upload_attachment" value="1">
                            <button type="submit" class="btn btn-outline-primary btn-sm mt-2">Enviar</button>
                        </form>
                    </div>
                </div>
                <?php endif ?>

                <?php
                $stmtAtt = $db->prepare("SELECT a.*, u.name as uploader FROM ticket_attachments a LEFT JOIN users u ON a.uploaded_by = u.id WHERE a.ticket_id = ? ORDER BY a.created_at DESC");
                $stmtAtt->execute([$ticketId]);
                $attachments = $stmtAtt->fetchAll();
                ?>
                <?php if ($attachments): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white"><h6 class="mb-0">Anexos (<?= count($attachments) ?>)</h6></div>
                    <div class="card-body">
                        <?php foreach ($attachments as $att): ?>
                            <?php $isImage = str_starts_with($att['mime_type'], 'image/'); ?>
                            <div class="border-bottom py-2 mb-2">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <a href="download.php?id=<?= $att['id'] ?>" class="text-decoration-none fw-bold"><?= htmlspecialchars($att['original_name']) ?></a>
                                        <small class="text-muted ms-2">(<?= round($att['size'] / 1024) ?>KB)</small>
                                    </div>
                                    <small class="text-muted"><?= $att['uploader'] ? htmlspecialchars($att['uploader']) . ' - ' : '' ?><?= date('d/m H:i', strtotime($att['created_at'])) ?></small>
                                </div>
                                <?php if ($isImage): ?>
                                    <div class="mt-2">
                                        <img src="download.php?id=<?= $att['id'] ?>" alt="<?= htmlspecialchars($att['original_name']) ?>" class="img-fluid rounded" style="max-height:300px; cursor:pointer" data-bs-toggle="modal" data-bs-target="#previewModal" onclick="document.getElementById('previewImg').src=this.src">
                                    </div>
                                <?php endif ?>
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>
                <?php endif ?>

                <?php if ($comments): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-white"><h6 class="mb-0">Comentarios</h6></div>
                    <div class="card-body">
                        <?php foreach ($comments as $c): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <strong class="small"><?= htmlspecialchars($c['user_name']) ?></strong>
                                <span class="text-muted small ms-2"><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></span>
                                <p class="mb-0 mt-1"><?= nl2br(htmlspecialchars($c['comment'])) ?></p>
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>
                <?php endif ?>

                <?php
                $timelineLabels = [
                    'ticket_create' => ['icon' => '&#128196;', 'color' => '#0d6efd', 'text' => 'Chamado aberto'],
                    'ticket_assign' => ['icon' => '&#128100;', 'color' => '#6f42c1', 'text' => 'Atribuido'],
                    'ticket_priority' => ['icon' => '&#9889;', 'color' => '#ffc107', 'text' => 'Prioridade alterada'],
                    'ticket_resolve' => ['icon' => '&#9989;', 'color' => '#198754', 'text' => 'Resolvido'],
                    'comment_add' => ['icon' => '&#128172;', 'color' => '#0dcaf0', 'text' => 'Comentario'],
                    'ticket_rate' => ['icon' => '&#11088;', 'color' => '#fd7e14', 'text' => 'Avaliado'],
                ];
                $st = $db->prepare("SELECT a.*, u.name as user_name FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id WHERE a.entity_type = 'ticket' AND a.entity_id = ? ORDER BY a.created_at ASC");
                $st->execute([$ticketId]);
                $timeline = $st->fetchAll();
                ?>
                <?php if ($timeline): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white"><h6 class="mb-0">Historico</h6></div>
                    <div class="card-body">
                        <?php foreach ($timeline as $i => $ev): ?>
                            <?php $tl = $timelineLabels[$ev['action']] ?? ['icon' => '&#128196;', 'color' => '#6c757d', 'text' => $ev['action']]; ?>
                            <div class="d-flex gap-3 <?= $i < count($timeline) - 1 ? 'mb-3' : '' ?>">
                                <div class="text-center" style="min-width:32px">
                                    <div style="width:32px;height:32px;border-radius:50%;background:<?= $tl['color'] ?>20;color:<?= $tl['color'] ?>;display:flex;align-items:center;justify-content:center;font-size:14px"><?= $tl['icon'] ?></div>
                                    <?php if ($i < count($timeline) - 1): ?>
                                        <div style="width:2px;height:20px;background:#dee2e6;margin:4px auto 0"></div>
                                    <?php endif ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold small"><?= $tl['text'] ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($ev['user_name'] ?? 'Sistema') ?> — <?= date('d/m/Y H:i', strtotime($ev['created_at'])) ?></div>
                                    <?php if ($ev['details']): ?>
                                        <div class="small mt-1"><?= htmlspecialchars($ev['details']) ?></div>
                                    <?php endif ?>
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>
                <?php endif ?>

            </div>
        </div>
    </div>

    <script>
    function setRating(v) {
        document.getElementById('ratingValue').value = v;
        document.getElementById('rateBtn').disabled = false;
        document.querySelectorAll('#starRating .star').forEach((el, i) => {
            el.innerHTML = i < v ? '&#9733;' : '&#9734;';
            el.classList.toggle('active', i < v);
        });
    }
    <?php if (!$ticket['resolved_at']): ?>
    (function() {
        var created = <?= strtotime($ticket['created_at']) * 1000 ?>;
        var slaHours = <?= SLA_HOURS ?>;
        function tick() {
            var now = new Date().getTime();
            var elapsed = now - created;
            var totalSec = Math.floor(elapsed / 1000);
            var h = Math.floor(totalSec / 3600);
            var m = Math.floor((totalSec % 3600) / 60);
            var s = totalSec % 60;
            var el = document.getElementById('slaTimer');
            el.textContent =
                ('0'+h).slice(-2) + 'h ' +
                ('0'+m).slice(-2) + 'm ' +
                ('0'+s).slice(-2) + 's';
            var pct = elapsed / (slaHours * 3600000);
            el.className = pct > 1 ? 'text-danger' : pct > 0.75 ? 'text-warning' : 'text-success';
        }
        tick();
        setInterval(tick, 1000);
    })();
    <?php endif ?>
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            var modal = document.querySelector('.modal.show');
            if (modal) return;
            window.location.href = document.getElementById('btnVoltar').href;
        }
    });
    </script>
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="background:transparent; border:none;">
                <div class="modal-body p-0 text-center">
                    <img id="previewImg" src="" class="img-fluid rounded" style="max-height:85vh">
                </div>
            </div>
        </div>
    </div>
    <script src="assets/toast.js"></script>
    <script src="assets/app.js"></script>
    <script src="assets/shortcuts.js"></script>
    <script src="assets/dropzone.js"></script>
    <script src="assets/theme.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
