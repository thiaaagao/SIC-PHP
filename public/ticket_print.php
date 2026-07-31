<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';

session_start();
Auth::requireAccess();

$db = Database::getInstance();
$id = (int) ($_GET['id'] ?? 0);

if (!$id) { header('Location: index.php'); exit; }

$stmt = $db->prepare("SELECT t.*, u.name as assigned_name FROM tickets t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.id = ?");
$stmt->execute([$id]);
$ticket = $stmt->fetch();

if (!$ticket) { header('Location: index.php'); exit; }

$st = $db->prepare("SELECT c.*, u.name as user_name FROM comments c JOIN users u ON c.user_id = u.id WHERE c.ticket_id = ? ORDER BY c.created_at");
$st->execute([$id]);
$comments = $st->fetchAll();

$st = $db->prepare("SELECT a.*, u.name as uploader FROM ticket_attachments a LEFT JOIN users u ON a.uploaded_by = u.id WHERE a.ticket_id = ?");
$st->execute([$id]);
$attachments = $st->fetchAll();

$st = $db->prepare("SELECT a.*, u.name as user_name FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id WHERE a.entity_type = 'ticket' AND a.entity_id = ? ORDER BY a.created_at");
$st->execute([$id]);
$timeline = $st->fetchAll();

$priorityLabel = ['low' => 'Baixa', 'medium' => 'Media', 'high' => 'Alta', 'critical' => 'Critica'];
$statusLabel = ['open' => 'Aberto', 'in_progress' => 'Em Andamento', 'resolved' => 'Resolvido', 'closed' => 'Fechado'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($ticket['code']) ?> - S.I.C.</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; margin: 20px; }
        h1 { font-size: 18px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        h3 { font-size: 14px; margin: 16px 0 6px; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin: 8px 0; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; font-size: 11px; }
        th { background: #f5f5f5; }
        .label { font-weight: bold; width: 120px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; color: #fff; }
        .bg-danger { background: #dc3545; } .bg-warning { background: #ffc107; color: #333; }
        .bg-success { background: #198754; } .bg-secondary { background: #6c757d; }
        .timeline { margin: 8px 0; }
        .timeline-item { padding: 4px 0; border-left: 3px solid #ddd; margin-left: 8px; padding-left: 12px; }
        @media print { body { margin: 10mm; } .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="text-align:right; margin-bottom:10px">
        <button onclick="window.print()" style="padding:6px 16px; cursor:pointer">Imprimir / Salvar PDF</button>
    </div>

    <h1>Chamado <?= htmlspecialchars($ticket['code']) ?></h1>

    <table>
        <tr><td class="label">Status</td><td><span class="badge bg-<?= $ticket['status'] === 'open' ? 'danger' : ($ticket['status'] === 'in_progress' ? 'warning' : ($ticket['status'] === 'resolved' ? 'success' : 'secondary')) ?>"><?= $statusLabel[$ticket['status']] ?></span></td>
            <td class="label">Prioridade</td><td><span class="badge bg-<?= $ticket['priority'] === 'critical' ? 'danger' : ($ticket['priority'] === 'high' ? 'warning' : ($ticket['priority'] === 'medium' ? 'secondary' : 'info')) ?>"><?= $priorityLabel[$ticket['priority']] ?></span></td></tr>
        <tr><td class="label">Solicitante</td><td><?= htmlspecialchars($ticket['requester_name']) ?></td>
            <td class="label">Atribuido a</td><td><?= htmlspecialchars($ticket['assigned_name'] ?: 'Nao atribuido') ?></td></tr>
        <tr><td class="label">Categoria</td><td><?= htmlspecialchars($ticket['subcategory']) ?></td>
            <td class="label">Setor / Conf</td><td><?= htmlspecialchars($ticket['setor'] ?: '-') ?> / <?= htmlspecialchars($ticket['conf'] ?: '-') ?></td></tr>
        <tr><td class="label">Hostname</td><td><?= htmlspecialchars($ticket['hostname'] ?: '-') ?></td>
            <td class="label">IP</td><td><?= htmlspecialchars($ticket['ip'] ?: '-') ?></td></tr>
        <tr><td class="label">Abertura</td><td><?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?></td>
            <td class="label">Resolucao</td><td><?= $ticket['resolved_at'] ? date('d/m/Y H:i', strtotime($ticket['resolved_at'])) : '-' ?></td></tr>
        <tr><td class="label">Resolvido por</td><td colspan="3"><?= htmlspecialchars($ticket['resolved_by'] ?: '-') ?></td></tr>
    </table>

    <h3>Descricao</h3>
    <p><?= nl2br(htmlspecialchars($ticket['description'])) ?></p>

    <?php if ($attachments): ?>
    <h3>Anexos (<?= count($attachments) ?>)</h3>
    <ul><?php foreach ($attachments as $a): ?>
        <li><?= htmlspecialchars($a['original_name']) ?> (<?= round($a['size']/1024) ?>KB) — <?= htmlspecialchars($a['uploader'] ?? '-') ?></li>
    <?php endforeach ?></ul>
    <?php endif ?>

    <?php if ($comments): ?>
    <h3>Comentarios (<?= count($comments) ?>)</h3>
    <table>
        <thead><tr><th>Data</th><th>Autor</th><th>Comentario</th></tr></thead>
        <tbody><?php foreach ($comments as $c): ?>
            <tr>
                <td><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
                <td><?= htmlspecialchars($c['user_name']) ?></td>
                <td><?= htmlspecialchars($c['comment']) ?></td>
            </tr>
        <?php endforeach ?></tbody>
    </table>
    <?php endif ?>

    <?php if ($timeline): ?>
    <h3>Historico</h3>
    <table>
        <thead><tr><th>Data</th><th>Acao</th><th>Detalhes</th></tr></thead>
        <tbody><?php foreach ($timeline as $ev): ?>
            <tr>
                <td><?= date('d/m/Y H:i', strtotime($ev['created_at'])) ?></td>
                <td><?= htmlspecialchars($ev['action']) ?></td>
                <td><?= htmlspecialchars($ev['details'] ?? '-') ?></td>
            </tr>
        <?php endforeach ?></tbody>
    </table>
    <?php endif ?>

    <hr>
    <small>Documento gerado em <?= date('d/m/Y H:i') ?> — S.I.C.</small>
</body>
</html>
