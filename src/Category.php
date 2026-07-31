<?php
class Category
{
    public static function getAll(): array
    {
        $db = Database::getInstance();
        return $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
    }

    public static function getActive(): array
    {
        $db = Database::getInstance();
        return $db->query("SELECT * FROM categories WHERE active = 1 ORDER BY name")->fetchAll();
    }

    public static function getById(int $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $name): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->execute([trim($name)]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, string $name, bool $active = true): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE categories SET name = ?, active = ? WHERE id = ?");
        $stmt->execute([trim($name), $active ? 1 : 0, $id]);
    }

    public static function delete(int $id): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
    }

    public static function getSubcategories(int $categoryId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM subcategories WHERE category_id = ? ORDER BY name");
        $stmt->execute([$categoryId]);
        return $stmt->fetchAll();
    }

    public static function getActiveSubcategories(int $categoryId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM subcategories WHERE category_id = ? AND active = 1 ORDER BY name");
        $stmt->execute([$categoryId]);
        return $stmt->fetchAll();
    }

    public static function getAllWithSubs(): array
    {
        $db = Database::getInstance();
        $cats = $db->query("SELECT * FROM categories WHERE active = 1 ORDER BY name")->fetchAll();
        foreach ($cats as &$cat) {
            $stmt = $db->prepare("SELECT * FROM subcategories WHERE category_id = ? AND active = 1 ORDER BY name");
            $stmt->execute([$cat['id']]);
            $cat['subs'] = $stmt->fetchAll();
        }
        return $cats;
    }

    public static function createSub(int $categoryId, string $name): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO subcategories (category_id, name) VALUES (?, ?)");
        $stmt->execute([$categoryId, trim($name)]);
        return (int) $db->lastInsertId();
    }

    public static function updateSub(int $id, string $name, bool $active = true): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE subcategories SET name = ?, active = ? WHERE id = ?");
        $stmt->execute([trim($name), $active ? 1 : 0, $id]);
    }

    public static function deleteSub(int $id): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM subcategories WHERE id = ?");
        $stmt->execute([$id]);
    }
}
