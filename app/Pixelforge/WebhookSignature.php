<?php

namespace App\Pixelforge;

/**
 * Checks the Pixelforge-Signature header: "t=<unix time>,v1=<hex HMAC-SHA256 of "<t>.<raw body>">".
 */
final readonly class WebhookSignature
{
    public function __construct(
        private string $secret,
        private int $toleranceSeconds = 300,
    ) {}

    public function isValid(string $payload, ?string $header, int $now): bool
    {
        if ($this->secret === '' || ! $header) {
            return false;
        }

        $parts = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$key] = $value;
        }

        $timestamp = $parts['t'] ?? '';

        if (! ctype_digit($timestamp) || abs($now - (int) $timestamp) > $this->toleranceSeconds) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $this->secret);

        return hash_equals($expected, $parts['v1'] ?? '');
    }
}
