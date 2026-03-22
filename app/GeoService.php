<?php
class GeoService
{
    private const DB_PATH = __DIR__ . '/../storage/geo/GeoLite2-City.mmdb';

    private static bool $autoloadRegistered = false;

    public static function lookup(string $ip): array
    {
        if (empty($ip)) {
            return [];
        }

        self::registerAutoload();

        if (!file_exists(self::DB_PATH)) {
            return [];
        }

        try {
            $reader = new \GeoIp2\Database\Reader(self::DB_PATH);
            $record = $reader->city($ip);

            return [
                'ip'           => $ip,
                'country_code' => $record->country->isoCode           ?? null,
                'country'      => $record->country->name              ?? null,
                'city'         => $record->city->name                 ?? null,
                'timezone'     => $record->location->timeZone         ?? null,
                'is_eu'        => $record->country->isInEuropeanUnion ?? false,
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function registerAutoload(): void
    {
        if (self::$autoloadRegistered) {
            return;
        }

        $baseDir = __DIR__ . '/../lib/geoip/src/';

        spl_autoload_register(function (string $class) use ($baseDir) {
            $file = $baseDir . str_replace('\\', '/', $class) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        });

        self::$autoloadRegistered = true;
    }
}



