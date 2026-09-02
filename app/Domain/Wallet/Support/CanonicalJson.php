<?php

namespace App\Domain\Wallet\Support;

final class CanonicalJson
{
    /**
     * A stable SHA-256 of a request payload: keys sorted recursively so the
     * fingerprint does not depend on JSON key order.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fingerprint(array $payload): string
    {
        return hash('sha256', self::encode(self::sortRecursive($payload)));
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private static function sortRecursive(array $data): array
    {
        ksort($data);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::sortRecursive($value);
            }
        }

        return $data;
    }

    /**
     * @param  array<mixed>  $data
     */
    private static function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
