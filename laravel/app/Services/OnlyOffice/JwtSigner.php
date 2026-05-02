<?php

namespace App\Services\OnlyOffice;

use RuntimeException;

class JwtSigner
{
    public function __construct(protected ?string $secret = null)
    {
        $this->secret = $this->normalizeSecret($secret ?? config('services.onlyoffice.jwt_secret'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sign(array $payload): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR)),
            $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR)),
        ];
        $signature = hash_hmac('sha256', implode('.', $segments), $this->secret, true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new RuntimeException('Token OnlyOffice tidak valid.');
        }

        [$header, $payload, $signature] = $parts;
        $expected = $this->base64UrlEncode(hash_hmac('sha256', $header.'.'.$payload, $this->secret, true));

        if (! hash_equals($expected, $signature)) {
            throw new RuntimeException('Signature OnlyOffice tidak valid.');
        }

        $decoded = json_decode($this->base64UrlDecode($payload), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Payload OnlyOffice tidak valid.');
        }

        if (isset($decoded['exp']) && is_numeric($decoded['exp']) && time() > (int) $decoded['exp']) {
            throw new RuntimeException('Token OnlyOffice kedaluwarsa.');
        }

        return $decoded;
    }

    protected function normalizeSecret(mixed $secret): string
    {
        $normalized = trim((string) $secret);

        if ($normalized === '') {
            throw new RuntimeException('ONLYOFFICE_JWT_SECRET wajib diisi.');
        }

        return $normalized;
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    protected function base64UrlDecode(string $value): string
    {
        $padded = $value.str_repeat('=', (4 - strlen($value) % 4) % 4);

        return base64_decode(strtr($padded, '-_', '+/')) ?: '';
    }
}
