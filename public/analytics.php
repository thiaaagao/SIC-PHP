<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/NavHelper.php';

session_start();
logAccess();
Auth::requireMinLevel('suporte_ti');

$db = Database::getInstance();
$user = Auth::getUser();

$totalTickets = $db->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
$resolvedTickets = $db->query("SELECT COUNT(*) FROM tickets WHERE status = 'resolved'")->fetchColumn();
$avgRating = $db->query("SELECT COALESCE(AVG(rating), 0) FROM ratings")->fetchColumn();
$avgResolveHours = $db->query("SELECT COALESCE(AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)), 0) FROM tickets WHERE resolved_at IS NOT NULL")->fetchColumn();

$ticketsByCategory = $db->query("SELECT subcategory, COUNT(*) as total FROM tickets GROUP BY subcategory ORDER BY total DESC")->fetchAll();
$ticketsByStatus = $db->query("SELECT status, COUNT(*) as total FROM tickets GROUP BY status")->fetchAll();
$ratingsByScore = $db->query("SELECT rating, COUNT(*) as total FROM ratings GROUP BY rating ORDER BY rating")->fetchAll();

$technicianPerf = $db->query("SELECT resolved_by, COUNT(*) as resolved_count, COALESCE(AVG(r.rating), 0) as avg_rating FROM tickets t LEFT JOIN ratings r ON r.ticket_id = t.id WHERE t.resolved_by IS NOT NULL AND t.status = 'resolved' GROUP BY t.resolved_by ORDER BY resolved_count DESC")->fetchAll();

$ratingByCategory = $db->query("SELECT t.subcategory, COALESCE(AVG(r.rating), 0) as avg_rating FROM tickets t LEFT JOIN ratings r ON r.ticket_id = t.id WHERE r.id IS NOT NULL GROUP BY t.subcategory ORDER BY avg_rating DESC")->fetchAll();

$monthlyTrend = $db->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as opened, SUM(CASE WHEN resolved_at IS NOT NULL THEN 1 ELSE 0 END) as resolved FROM tickets GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month LIMIT 12")->fetchAll();

$mostCommonProblems = $db->query("SELECT description, COUNT(*) as total FROM tickets GROUP BY description ORDER BY total DESC LIMIT 10")->fetchAll();

$ratingLabels = [];
$ratingData = [];
for ($i = 1; $i <= 5; $i++) {
    $found = 0;
    foreach ($ratingsByScore as $r) {
        if ((int)$r['rating'] === $i) { $found = (int)$r['total']; break; }
    }
    $ratingLabels[] = "$i * ($found)";
    $ratingData[] = $found;
}
$trendMonths = array_map(fn($m) => $m['month'], $monthlyTrend);
$trendOpened = array_map(fn($m) => (int)$m['opened'], $monthlyTrend);
$trendResolved = array_map(fn($m) => (int)$m['resolved'], $monthlyTrend);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ITIL Analytics - P.S. Profarma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/theme.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <style>
        .chart-container { position: relative; height: 250px; }
        .metric-value { font-size: 2rem; font-weight: 700; line-height: 1.2; color: var(--text-main); }
        .metric-label { font-size: .8rem; text-transform: uppercase; letter-spacing: .5px; color: var(--text-secondary); }
        .itil-section { border-left: 4px solid #0d6efd; padding-left: 1rem; margin-bottom: 1.5rem; }
        .itil-section.csi { border-left-color: #198754; }
        .itil-section.incident { border-left-color: #dc3545; }
        .itil-section.service { border-left-color: #6f42c1; }
        .itil-section.problem { border-left-color: #fd7e14; }
        .itil-section h6 { color: var(--text-main); }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand <?= Auth::navbarBg() ?> navbar-dark">
        <div class="container">
            <span class="navbar-brand fw-bold">ITIL Analytics<?= NavHelper::badge() ?></span>
            <div class="ms-auto d-flex gap-2 align-items-center">
                <a href="index.php" class="btn btn-outline-light btn-sm">Dashboard</a>
                <a href="support.php" class="btn btn-outline-light btn-sm">Suporte</a>
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
        <a href="index.php" class="btn btn-outline-secondary btn-sm mb-3">&larr; Voltar</a>

        <div class="d-flex align-items-center gap-3 mb-4">
            <h4 class="mb-0">ITIL Analytics</h4>
            <span class="text-muted small">Baseado em <?= $totalTickets ?> tickets atendidos</span>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="metric-value text-primary"><?= $totalTickets ?></div>
                        <div class="metric-label">Total de Incidentes</div>
                        <small class="text-muted">ITIL: Gerenciamento de Incidente</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="metric-value text-success"><?= $resolvedTickets ?></div>
                        <div class="metric-label">Resolvidos</div>
                        <small class="text-muted"><?= $totalTickets > 0 ? round($resolvedTickets / $totalTickets * 100) : 0 ?>% Taxa de Resolucao</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="metric-value text-warning"><?= number_format($avgResolveHours, 1) ?>h</div>
                        <div class="metric-label">Tempo Medio Resolucao</div>
                        <small class="text-muted">ITIL: SLA / Nivel de Servico</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="metric-value text-info"><?= number_format($avgRating, 1) ?> *</div>
                        <div class="metric-label">Satisfacao Media</div>
                        <small class="text-muted">ITIL: CSI / Melhoria Continua</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-8">
                <div class="itil-section incident">
                    <h6 class="fw-bold">Gerenciamento de Incidentes - Tendencia Mensal</h6>
                </div>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="chart-container"><canvas id="trendChart"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="itil-section incident">
                    <h6 class="fw-bold">Status dos Incidentes</h6>
                </div>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="chart-container"><canvas id="statusChart"></canvas></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-5">
                <div class="itil-section problem">
                    <h6 class="fw-bold">Gerenciamento de Problemas - Categorias</h6>
                </div>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="chart-container"><canvas id="categoryChart"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="itil-section problem">
                    <h6 class="fw-bold">Top Problemas Recorrentes</h6>
                </div>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="chart-container"><canvas id="problemChart"></canvas></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="itil-section service">
                    <h6 class="fw-bold">Gerenciamento de Nivel de Servico - Avaliacoes</h6>
                </div>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="chart-container"><canvas id="ratingChart"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="itil-section service">
                    <h6 class="fw-bold">Satisfacao por Categoria</h6>
                </div>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="chart-container"><canvas id="categoryRatingChart"></canvas></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-12">
                <div class="itil-section csi">
                    <h6 class="fw-bold">CSI - Continual Service Improvement: Performance dos Tecnicos</h6>
                </div>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="chart-container" style="height:200px"><canvas id="techChart"></canvas></div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
    function isDark() { return document.documentElement.getAttribute('data-theme') === 'dark'; }
    function chartColors() {
        const d = isDark();
        return {
            text: d ? '#e0e0e0' : '#333333',
            grid: d ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)',
            bg: d ? '#1e1e1e' : '#ffffff'
        };
    }
    function chartDefaults() {
        const c = chartColors();
        Chart.defaults.color = c.text;
        Chart.defaults.borderColor = c.grid;
    }
    chartDefaults();

    const teal = 'rgba(13,110,253,0.8)';
    const green = 'rgba(25,135,84,0.8)';
    const orange = 'rgba(253,126,20,0.8)';
    const colors = [teal, green, orange, 'rgba(111,66,193,0.8)', 'rgba(13,202,240,0.8)', 'rgba(255,193,7,0.8)'];

    new Chart(document.getElementById('trendChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($trendMonths) ?>,
            datasets: [
                { label: 'Abertos', data: <?= json_encode($trendOpened) ?>, backgroundColor: teal },
                { label: 'Resolvidos', data: <?= json_encode($trendResolved) ?>, backgroundColor: green }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' } } }
    });

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: [<?php foreach ($ticketsByStatus as $s): ?>'<?= $s['status'] ?> (<?= $s['total'] ?>)',<?php endforeach ?>],
            datasets: [{ data: [<?php foreach ($ticketsByStatus as $s): ?><?= $s['total'] ?>,<?php endforeach ?>], backgroundColor: ['#dc3545','#ffc107','#198754','#6c757d'] }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
    });

    new Chart(document.getElementById('categoryChart'), {
        type: 'polarArea',
        data: {
            labels: [<?php foreach ($ticketsByCategory as $c): ?>'<?= $c['subcategory'] ?> (<?= $c['total'] ?>)',<?php endforeach ?>],
            datasets: [{ data: [<?php foreach ($ticketsByCategory as $c): ?><?= $c['total'] ?>,<?php endforeach ?>], backgroundColor: colors.slice(0, <?= count($ticketsByCategory) ?>) }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
    });

    new Chart(document.getElementById('problemChart'), {
        type: 'bar',
        data: {
            labels: [<?php foreach ($mostCommonProblems as $p): ?>'<?= htmlspecialchars(addslashes(substr($p['description'], 0, 30))) ?>',<?php endforeach ?>],
            datasets: [{ label: 'Ocorrencias', data: [<?php foreach ($mostCommonProblems as $p): ?><?= $p['total'] ?>,<?php endforeach ?>], backgroundColor: orange }]
        },
        options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } } }
    });

    new Chart(document.getElementById('ratingChart'), {
        type: 'bar',
        data: { labels: <?= json_encode($ratingLabels) ?>, datasets: [{ label: 'Avaliacoes', data: <?= json_encode($ratingData) ?>, backgroundColor: ['#dc3545','#fd7e14','#ffc107','#198754','#0d6efd'] }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });

    new Chart(document.getElementById('categoryRatingChart'), {
        type: 'radar',
        data: {
            labels: [<?php foreach ($ratingByCategory as $c): ?>'<?= $c['subcategory'] ?>',<?php endforeach ?>],
            datasets: [{ label: 'Media', data: [<?php foreach ($ratingByCategory as $c): ?><?= $c['avg_rating'] ?>,<?php endforeach ?>], backgroundColor: 'rgba(13,110,253,0.2)', borderColor: teal, pointBackgroundColor: teal }]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { r: { min: 0, max: 5, ticks: { stepSize: 1 } } } }
    });

    new Chart(document.getElementById('techChart'), {
        type: 'bar',
        data: {
            labels: [<?php foreach ($technicianPerf as $t): ?>'<?= htmlspecialchars(addslashes($t['resolved_by'])) ?>',<?php endforeach ?>],
            datasets: [
                { label: 'Resolvidos', data: [<?php foreach ($technicianPerf as $t): ?><?= $t['resolved_count'] ?>,<?php endforeach ?>], backgroundColor: teal },
                { label: 'Media Avaliacao', data: [<?php foreach ($technicianPerf as $t): ?><?= $t['avg_rating'] ?>,<?php endforeach ?>], backgroundColor: green }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' } } }
    });

    window.addEventListener('themeChanged', function() { chartDefaults(); });
    </script>
    <script src="assets/theme.js"></script>
    <script src="assets/shortcuts.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
