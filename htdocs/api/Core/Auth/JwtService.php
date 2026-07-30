<?php

namespace App\Core\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtService
{
    private const ALGO = 'HS256';

    public static function issue(string $username, int $ttlSeconds = 28800): string
    {
        $now = time();

        $payload = [
            'sub' => $username,
            'iat' => $now,
            'exp' => $now + $ttlSeconds,
        ];

        return JWT::encode($payload, self::secret(), self::ALGO);
    }

    /** @return array<string, mixed>|null */
    public static function verify(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key(self::secret(), self::ALGO));
            return (array) $decoded;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function secret(): string
    {
        $secret = getenv('JWT_SECRET') ?: '';
        if ($secret === '') {
            throw new \RuntimeException('JWT_SECRET is not configured');
        }

        return $secret;
    }
}
