<?php

class Network
{
    public static function getClientIp(): string
    {
        $candidates = [];

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            foreach (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $ip) {
                $candidates[] = trim($ip);
            }
        }
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $candidates[] = trim($_SERVER['HTTP_X_REAL_IP']);
        }
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $candidates[] = trim($_SERVER['REMOTE_ADDR']);
        }

        foreach ($candidates as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                continue;
            }
            $normalized = self::normalizeIp($ip);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return '';
    }

    public static function normalizeIp(string $ip): ?string
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        if ($ip === '::1') {
            return '127.0.0.1';
        }

        if (str_starts_with($ip, '::ffff:') && filter_var(substr($ip, 7), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return substr($ip, 7);
        }

        return $ip;
    }

    public static function getHostnameByIp(string $ip): string
    {
        if ($ip === '') {
            return '';
        }

        $glpiHostname = GLPILookup::getHostnameByIp($ip);
        if ($glpiHostname) {
            return $glpiHostname;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP) && checkdnsrr($ip, 'PTR')) {
            $resolved = gethostbyaddr($ip);
            if ($resolved && $resolved !== $ip) {
                return $resolved;
            }
        }

        if ($ip === '127.0.0.1' || $ip === '::1') {
            $local = gethostname();
            if ($local) {
                return $local;
            }
        }

        return '';
    }
}
