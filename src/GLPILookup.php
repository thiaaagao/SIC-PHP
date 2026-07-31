<?php

class GLPILookup
{
    private static ?PDO $glpiDb = null;

    private static function getDb(): PDO
    {
        if (self::$glpiDb === null) {
            self::$glpiDb = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=glpi_db;charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        }
        return self::$glpiDb;
    }

    public static function getHostnameByIp(string $ip): ?string
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        $db = self::getDb();

        $stmt = $db->prepare("
            SELECT c.name
            FROM glpi_ipaddresses ip
            JOIN glpi_networknames nn ON nn.id = ip.items_id AND ip.itemtype = 'NetworkName' AND nn.is_deleted = 0
            JOIN glpi_networkports np ON np.id = nn.items_id AND nn.itemtype = 'NetworkPort' AND np.is_deleted = 0
            JOIN glpi_computers c ON c.id = np.items_id AND np.itemtype = 'Computer' AND c.is_deleted = 0
            WHERE ip.name = ? AND ip.is_deleted = 0
            LIMIT 1
        ");
        $stmt->execute([$ip]);
        $row = $stmt->fetch();

        return $row ? $row['name'] : null;
    }

    public static function getIpsByHostname(string $hostname): array
    {
        $db = self::getDb();

        $stmt = $db->prepare("
            SELECT ip.name
            FROM glpi_computers c
            JOIN glpi_networkports np ON np.items_id = c.id AND np.itemtype = 'Computer' AND np.is_deleted = 0
            JOIN glpi_networknames nn ON nn.items_id = np.id AND nn.itemtype = 'NetworkPort' AND nn.is_deleted = 0
            JOIN glpi_ipaddresses ip ON ip.items_id = nn.id AND ip.itemtype = 'NetworkName' AND ip.is_deleted = 0
            WHERE c.name = ? AND c.is_deleted = 0 AND ip.version = 4
            GROUP BY ip.name
        ");
        $stmt->execute([$hostname]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
