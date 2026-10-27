<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\String\ByteString;

/**
 * The secret in a player link (/speler/{token}). It is the player's only credential:
 * 32 random alphanumeric characters (~190 bits). Only its SHA-256 hash is stored.
 */
final class PlayerToken
{
    public const int LENGTH = 32;
    /** Route requirement for the token; never matches fixed paths like /speler/nieuw. */
    public const string PATTERN = '[A-Za-z0-9]{32}';

    public static function generate(): string
    {
        return ByteString::fromRandom(self::LENGTH)->toString();
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function isWellFormed(string $token): bool
    {
        return 1 === preg_match('/^'.self::PATTERN.'$/D', $token);
    }
}
