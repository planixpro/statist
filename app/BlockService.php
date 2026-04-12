<?php

class BlockService
{
    private static array $cidrCache = [];

    public static function isBlocked(PDO $pdo, string $ip): bool
    {
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        // --- Проверка IP ---
        $stmt = $pdo->prepare("
            SELECT 1 FROM blocked_ips
            WHERE ip = :ip
              AND is_active = 1
              AND (expires_at IS NULL OR expires_at > NOW())
            LIMIT 1
        ");
        $stmt->execute(['ip' => $ip]);

        if ($stmt->fetch()) {
            return true;
        }

        // --- Проверка сетей ---
        foreach (self::getCidrs($pdo) as $cidr) {
            if (self::ipInCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    private static function getCidrs(PDO $pdo): array
    {
        // простой кеш на время запроса
        if (!empty(self::$cidrCache)) {
            return self::$cidrCache;
        }

        $rows = $pdo->query("
            SELECT cidr FROM blocked_networks
            WHERE is_active = 1
        ")->fetchAll(PDO::FETCH_COLUMN);

        self::$cidrCache = array_filter($rows);

        return self::$cidrCache;
    }

    private static function ipInCidr(string $ip, string $cidr): bool
    {
        if (strpos($cidr, '/') === false) {
            return false;
        }

        [$subnet, $mask] = explode('/', $cidr, 2);

        // --- IPv4 ---
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if (!filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return false;
            }

            $mask = (int)$mask;
            if ($mask < 0 || $mask > 32) {
                return false;
            }

            $ipLong     = ip2long($ip);
            $subnetLong = ip2long($subnet);

            if ($ipLong === false || $subnetLong === false) {
                return false;
            }

            $maskLong = -1 << (32 - $mask);

            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        }

        // --- IPv6 (упрощённо) ---
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if (!filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return false;
            }

            $mask = (int)$mask;
            if ($mask < 0 || $mask > 128) {
                return false;
            }

            $ipBin     = inet_pton($ip);
            $subnetBin = inet_pton($subnet);

            if ($ipBin === false || $subnetBin === false) {
                return false;
            }

            $bytes = intdiv($mask, 8);
            $bits  = $mask % 8;

            if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
                return false;
            }

            if ($bits > 0) {
                $maskByte = (~(0xff >> $bits)) & 0xff;

                if ((ord($ipBin[$bytes]) & $maskByte) !== (ord($subnetBin[$bytes]) & $maskByte)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }
}