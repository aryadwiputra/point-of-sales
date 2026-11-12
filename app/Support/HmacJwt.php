<?php

namespace App\Support;

use RuntimeException;

/**
 * JWT ringkas berbasis HMAC-SHA256 (HS256) tanpa dependensi eksternal.
 *
 * Format sama dengan JWT pada umumnya sehingga aplikasi lain (mis. aplikasi
 * Finance) dapat memverifikasinya dengan library standar atau implementasi
 * serupa. Hanya mendukung HS256 — cukup untuk secret bersama.
 */
final class HmacJwt
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function encode(array $payload, string $secret): string
    {
        $header = self::base64UrlEncode(json_encode(
            ['alg' => 'HS256', 'typ' => 'JWT'],
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));

        $body = self::base64UrlEncode(json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ));

        $signature = self::base64UrlEncode(hash_hmac('sha256', $header.'.'.$body, $secret, true));

        return $header.'.'.$body.'.'.$signature;
    }

    /**
     * @return array<string, mixed>
     */
    public static function decode(string $token, string $secret): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new RuntimeException('Token tidak valid.');
        }

        [$header, $body, $signature] = $parts;

        $expected = self::base64UrlEncode(hash_hmac('sha256', $header.'.'.$body, $secret, true));

        if (! hash_equals($expected, $signature)) {
            throw new RuntimeException('Signature token tidak valid.');
        }

        $decoded = json_decode(self::base64UrlDecode($body), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Payload token tidak valid.');
        }

        if (isset($decoded['exp']) && (int) $decoded['exp'] < time()) {
            throw new RuntimeException('Token sudah kedaluwarsa.');
        }

        if (isset($decoded['nbf']) && (int) $decoded['nbf'] > time()) {
            throw new RuntimeException('Token belum berlaku.');
        }

        return $decoded;
    }

    public static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new RuntimeException('Payload token tidak valid.');
        }

        return $decoded;
    }
}
