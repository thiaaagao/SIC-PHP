<?php

class NavHelper
{
    public static function openCount(): int
    {
        $db = Database::getInstance();
        return (int) $db->query("SELECT COUNT(*) FROM tickets WHERE status IN ('open','in_progress')")->fetchColumn();
    }

    public static function badge(): string
    {
        $count = self::openCount();
        if ($count === 0) return '';
        return '<span class="badge bg-danger rounded-pill ms-1" style="font-size:0.65rem">' . $count . '</span>';
    }
}
