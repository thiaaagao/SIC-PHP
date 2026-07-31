<?php
class AuditLog
{
    public static function log(string $action, string $entityType, ?int $entityId = null, ?string $details = null): void
    {
        $db = Database::getInstance();
        $userId = $_SESSION['user']['id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $entityType, $entityId, $details, $ip]);
    }

    public static function getRecent(int $limit = 50): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT a.*, u.name as user_name FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public static function getByEntity(string $entityType, int $entityId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT a.*, u.name as user_name FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id WHERE a.entity_type = ? AND a.entity_id = ? ORDER BY a.created_at DESC");
        $stmt->execute([$entityType, $entityId]);
        return $stmt->fetchAll();
    }
}
