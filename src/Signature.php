<?php

namespace Myori\AiClient;

/**
 * How the gateway signs its calls into a system: HMAC-SHA256 over "{timestamp}.{raw body}",
 * keyed with the system's callback secret, sent as lowercase hex.
 */
final class Signature
{
    public const TIMESTAMP_HEADER = 'X-MyoriAI-Timestamp';

    public const SIGNATURE_HEADER = 'X-MyoriAI-Signature';

    public static function make(string $secret, int $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    /**
     * Headers for a signed request, as the gateway sends them.
     *
     * @return array<string, string>
     */
    public static function headers(string $secret, int $timestamp, string $body): array
    {
        return [
            self::TIMESTAMP_HEADER => (string) $timestamp,
            self::SIGNATURE_HEADER => self::make($secret, $timestamp, $body),
        ];
    }
}
