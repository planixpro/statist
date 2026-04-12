<?php

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $route = 'dashboard', array $params = []): string
    {
        $route = trim($route, '/');
        $path = '/list/' . ($route === '' ? 'dashboard' : $route);
        if ($params) {
            $path .= '?' . http_build_query($params);
        }
        return $path;
    }
}

if (!function_exists('is_valid_domain_name')) {
    function is_valid_domain_name(string $domain): bool
    {
        if ($domain === '' || strlen($domain) > 253) {
            return false;
        }

        return (bool)preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $domain);
    }
}

if (!function_exists('normalize_cidr')) {
    function normalize_cidr(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return $value;
        }

        if (!str_contains($value, '/')) {
            return null;
        }

        [$ip, $mask] = array_pad(explode('/', $value, 2), 2, '');
        if (!filter_var($ip, FILTER_VALIDATE_IP) || !ctype_digit($mask)) {
            return null;
        }

        $mask = (int)$mask;
        $max  = str_contains($ip, ':') ? 128 : 32;
        if ($mask < 0 || $mask > $max) {
            return null;
        }

        return $ip . '/' . $mask;
    }
}

if (!function_exists('ip_in_cidr')) {
    function ip_in_cidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return false;
        }

        [$subnet, $mask] = explode('/', $cidr, 2);
        if (!filter_var($ip, FILTER_VALIDATE_IP) || !filter_var($subnet, FILTER_VALIDATE_IP) || !ctype_digit($mask)) {
            return false;
        }

        $mask = (int)$mask;

        if (str_contains($ip, ':') || str_contains($subnet, ':')) {
            $ipBin = inet_pton($ip);
            $subnetBin = inet_pton($subnet);
            if ($ipBin === false || $subnetBin === false) {
                return false;
            }

            $bytes = intdiv($mask, 8);
            $bits  = $mask % 8;

            if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
                return false;
            }

            if ($bits === 0) {
                return true;
            }

            $maskByte = ~(255 >> $bits) & 255;
            return ((ord($ipBin[$bytes]) & $maskByte) === (ord($subnetBin[$bytes]) & $maskByte));
        }

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $maskLong = $mask === 0 ? 0 : (~0 << (32 - $mask));
        return (($ipLong & $maskLong) === ($subnetLong & $maskLong));
    }
}
