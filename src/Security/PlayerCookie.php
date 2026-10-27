<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * Remembers the player link token in the browser. HttpOnly + SameSite=Lax; the
 * SameSite policy is also what protects the JSON result endpoint against CSRF.
 */
final class PlayerCookie
{
    public const string NAME = 'scholi_player';
    /** Browsers cap cookie lifetime at 400 days; the cookie is refreshed while the player is active. */
    private const string LIFETIME = '+400 days';

    public static function create(Request $request, string $token): Cookie
    {
        return Cookie::create(
            self::NAME,
            $token,
            new \DateTimeImmutable(self::LIFETIME),
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            Cookie::SAMESITE_LAX,
        );
    }

    public static function clear(Request $request): Cookie
    {
        return Cookie::create(self::NAME, null, 1, '/', null, $request->isSecure(), true, false, Cookie::SAMESITE_LAX);
    }
}
