<?php

require_once __DIR__ . '/Database.php';

class Auth
{
    private static array $roleHierarchy = [
        'visitante'    => 0,
        'encarregado'  => 1,
        'suporte_ti'   => 2,
        'admin'        => 3,
    ];

    public static function requireAccess(): void
    {
        if (!self::isIpAllowed()) {
            http_response_code(403);
            die('Acesso negado: IP nao autorizado.');
        }
    }

    public static function isIpAllowed(): bool
    {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
        foreach (ALLOWED_IPS as $allowed) {
            if (str_contains($allowed, '/')) {
                if (self::ipInCidr($clientIp, $allowed)) return true;
            } else {
                if ($clientIp === $allowed) return true;
            }
        }
        return false;
    }

    public static function login(string $username, string $password): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'name' => $user['name'],
                'role' => $user['role'],
            ];
            return true;
        }
        return false;
    }

    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['user']);
    }

    public static function getUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function getRole(): ?string
    {
        return $_SESSION['user']['role'] ?? null;
    }

    public static function roleLevel(?string $role = null): int
    {
        return self::$roleHierarchy[$role ?? self::getRole()] ?? 0;
    }

    public static function hasMinLevel(string $minRole): bool
    {
        return self::roleLevel() >= self::roleLevel($minRole);
    }

    public static function requireMinLevel(string $minRole): void
    {
        self::requireAccess();
        if (!self::isLoggedIn() || !self::hasMinLevel($minRole)) {
            header('Location: login.php');
            exit;
        }
        if (!self::checkSessionTimeout()) {
            header('Location: login.php?timeout=1');
            exit;
        }
    }

    public static function isAdmin(): bool
    {
        return self::getRole() === 'admin';
    }

    public static function isSuporteTi(): bool
    {
        return self::getRole() === 'suporte_ti';
    }

    public static function isEncarregado(): bool
    {
        return self::getRole() === 'encarregado';
    }

    public static function canResolve(): bool
    {
        return self::hasMinLevel('suporte_ti');
    }

    public static function canEvaluate(): bool
    {
        return self::hasMinLevel('encarregado');
    }

    public static function canViewAnalytics(): bool
    {
        return self::hasMinLevel('suporte_ti');
    }

    public static function logout(): void
    {
        unset($_SESSION['user']);
        unset($_SESSION['last_activity']);
        session_destroy();
    }

    public static function checkSessionTimeout(int $timeoutSeconds = 1800): bool
    {
        if (!self::isLoggedIn()) {
            return false;
        }

        $now = time();
        if (!isset($_SESSION['last_activity'])) {
            $_SESSION['last_activity'] = $now;
            return true;
        }

        if ($now - $_SESSION['last_activity'] > $timeoutSeconds) {
            self::logout();
            return false;
        }

        $_SESSION['last_activity'] = $now;
        return true;
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . self::csrfToken() . '">';
    }

    public static function validateCsrf(): bool
    {
        $token = $_POST['csrf_token'] ?? '';
        return !empty($token) && hash_equals(self::csrfToken(), $token);
    }

    public static function navbarBg(): string
    {
        return match(self::getRole()) {
            'admin' => 'bg-dark',
            'suporte_ti' => 'bg-primary',
            'encarregado' => 'bg-success',
            default => 'bg-secondary',
        };
    }

    private static function ipInCidr(string $ip, string $cidr): bool
    {
        $parts = explode('/', $cidr);
        $net = $parts[0];
        $mask = (int) ($parts[1] ?? 32);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($net, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            $netLong = ip2long($net);
            $maskLong = -1 << (32 - $mask);
            return ($ipLong & $maskLong) === ($netLong & $maskLong);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && filter_var($net, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ipBin = inet_pton($ip);
            $netBin = inet_pton($net);
            $fullBytes = $mask >> 3;
            $partialBits = $mask & 7;
            $compare = substr($ipBin, 0, $fullBytes) === substr($netBin, 0, $fullBytes);
            if ($partialBits && $compare) {
                $maskByte = 0xFF << (8 - $partialBits) & 0xFF;
                $compare = (ord($ipBin[$fullBytes]) & $maskByte) === (ord($netBin[$fullBytes]) & $maskByte);
            }
            return $compare;
        }
        return false;
    }
}
