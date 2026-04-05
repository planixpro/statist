<?php

class GeoService
{
    private const CITY_DB = __DIR__ . '/../storage/geo/GeoLite2-City.mmdb';
    private const ASN_DB  = __DIR__ . '/../storage/geo/GeoLite2-ASN.mmdb';

    private static bool $autoloadRegistered = false;

    public static function lookup(string $ip): array
    {
        if (empty($ip)) {
            return [];
        }

        self::registerAutoload();

        $data = [
            'ip'           => $ip,
            'country_code' => null,
            'country'      => null,
            'city'         => null,
            'timezone'     => null,
            'is_eu'        => false,
            'asn'          => null,
            'provider'     => null,
        ];

        // -------------------------
        // CITY
        // -------------------------
        if (file_exists(self::CITY_DB)) {
            try {
                $reader = new \GeoIp2\Database\Reader(self::CITY_DB);
                $record = $reader->city($ip);

                $data['country_code'] = $record->country->isoCode ?? null;
                $data['country']      = $record->country->name ?? null;
                $data['city']         = $record->city->name ?? null;
                $data['timezone']     = $record->location->timeZone ?? null;
                $data['is_eu']        = $record->country->isInEuropeanUnion ?? false;

            } catch (\Throwable $e) {
                // тихо падаем
            }
        }

        // -------------------------
        // ASN (🔥 ключевая часть)
        // -------------------------
        if (file_exists(self::ASN_DB)) {
            try {
                $reader = new \GeoIp2\Database\Reader(self::ASN_DB);
                $record = $reader->asn($ip);

                $data['asn']      = isset($record->autonomousSystemNumber)
                    ? 'AS' . $record->autonomousSystemNumber
                    : null;

                $data['provider'] = $record->autonomousSystemOrganization ?? null;

            } catch (\Throwable $e) {
                // ignore
            }
        }

        return $data;
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