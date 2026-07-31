<?php

class RateLimit
{
    private static string $dir = __DIR__ . '/../storage/ratelimit';

    private static function ensureDir(): void
    {
        if (!is_dir(self::$dir)) {
            mkdir(self::$dir, 0770, true);
        }
    }

    private static function getKey(string $key): string
    {
        return self::$dir . '/' . md5($key) . '.json';
    }

    public static function check(string $key, int $maxAttempts = 5, int $windowSeconds = 60): bool
    {
        self::ensureDir();
        $file = self::getKey($key);
        $now = time();

        if (!file_exists($file)) {
            return true;
        }

        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data)) {
            return true;
        }

        $data['attempts'] = array_filter($data['attempts'], fn($ts) => $ts > $now - $windowSeconds);
        file_put_contents($file, json_encode($data));

        return count($data['attempts']) < $maxAttempts;
    }

    public static function record(string $key): void
    {
        self::ensureDir();
        $file = self::getKey($key);
        $now = time();

        $data = ['attempts' => []];
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true) ?? $data;
        }

        $data['attempts'][] = $now;
        file_put_contents($file, json_encode($data));
    }

    public static function clear(string $key): void
    {
        $file = self::getKey($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }
}
