<?php

date_default_timezone_set('America/Sao_Paulo');

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.gc_maxlifetime', 1800);

function sendSecurityHeaders(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data:; font-src 'self' https://cdn.jsdelivr.net;");
}

define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'ps_system');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

define('TEAMS_WEBHOOK_URL', 'YOUR_POWER_AUTOMATE_WEBHOOK_URL_HERE');

define('ALLOWED_IPS', [
    '127.0.0.1',
    '::1',
    '10.0.0.0/8',
    '172.16.0.0/12',
    '192.168.0.0/16',
]);

define('BASE_URL', 'http://localhost:8080/ps-system');

define('SLA_HOURS', 4);

function formatSlaElapsed(string $createdAt, ?string $resolvedAt = null): string
{
    $start = new DateTime($createdAt);
    $end = $resolvedAt ? new DateTime($resolvedAt) : new DateTime('now');
    $diff = $start->diff($end);
    $h = $diff->days * 24 + $diff->h;
    return sprintf('%dh %02dm', $h, $diff->i);
}

function getSlaStatus(string $createdAt, ?string $resolvedAt = null, string $priority = 'medium'): string
{
    $start = new DateTime($createdAt);
    $end = $resolvedAt ? new DateTime($resolvedAt) : new DateTime('now');
    $elapsedHours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;

    $slaMap = [
        'low' => 24,
        'medium' => SLA_HOURS,
        'high' => 2,
        'critical' => 1,
    ];
    $sla = $slaMap[$priority] ?? SLA_HOURS;

    if ($elapsedHours <= $sla * 0.75) return 'ok';
    if ($elapsedHours <= $sla) return 'warning';
    return 'breached';
}

function logAccess(): void
{
    if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'download.php') return;
    if (strpos($_SERVER['SCRIPT_FILENAME'] ?? '', 'setup.php') !== false) return;

    $db = Database::getInstance();
    $userId = $_SESSION['user_id'] ?? null;
    $page = basename($_SERVER['SCRIPT_FILENAME'] ?? 'unknown');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $stmt = $db->prepare("INSERT INTO access_logs (user_id, page, ip, user_agent) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $page, $ip, $ua]);
}

sendSecurityHeaders();
