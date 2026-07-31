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

        $fp = fopen($file, 'c+');
        if (!$fp) return true;

        flock($fp, LOCK_EX);
        $data = json_decode(stream_get_contents($fp), true);
        if (!is_array($data)) {
            $data = ['attempts' => []];
        }

        $data['attempts'] = array_filter($data['attempts'], fn($ts) => $ts > $now - $windowSeconds);

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
        flock($fp, LOCK_UN);
        fclose($fp);

        return count($data['attempts']) < $maxAttempts;
    }

    public static function record(string $key): void
    {
        self::ensureDir();
        $file = self::getKey($key);
        $now = time();

        $fp = fopen($file, 'c+');
        if (!$fp) return;

        flock($fp, LOCK_EX);
        $data = json_decode(stream_get_contents($fp), true) ?? ['attempts' => []];

        $data['attempts'][] = $now;

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    public static function clear(string $key): void
    {
        $file = self::getKey($key);
        if (file_exists($file)) {
            @unlink($file);
        }
    }
}
