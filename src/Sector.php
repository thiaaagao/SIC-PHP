<?php

class Sector
{
    public static function getAll(): array
    {
        $db = Database::getInstance();
        return $db->query("SELECT * FROM sectors ORDER BY name")->fetchAll();
    }

    public static function getActive(): array
    {
        $db = Database::getInstance();
        return $db->query("SELECT name FROM sectors WHERE active = 1 ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function getById(int $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM sectors WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $name): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO sectors (name) VALUES (?)");
        $stmt->execute([trim($name)]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, string $name, bool $active): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE sectors SET name = ?, active = ? WHERE id = ?");
        $stmt->execute([trim($name), $active ? 1 : 0, $id]);
    }

    public static function delete(int $id): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM sectors WHERE id = ?");
        $stmt->execute([$id]);
    }

    public static function getActiveList(): array
    {
        return self::getActive();
    }
}
