<?php

declare(strict_types=1);

namespace App\Security;

use App\Game\Tafels;
use App\Game\TafelsResultInput;
use App\Player\GuestProgress;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * Keeps a guest's progress in the browser: an HttpOnly cookie signed with an HMAC, so it only
 * ever holds results the server accepted. It moves to the account when the guest makes a player.
 *
 * Value: base64url({"v":1,"r":{"<table>":[bestMs, completedAtUnix]}}) . "." . base64url(hmac)
 */
final readonly class GuestProgressCookie
{
    public const string NAME = 'scholi_guest';
    private const string LIFETIME = '+400 days';
    private const int VERSION = 1;
    /** Derives a key for this purpose only, so the raw APP_SECRET is never used directly. */
    private const string KEY_PURPOSE = 'scholi-guest-progress';

    private string $key;

    public function __construct(#[Autowire('%kernel.secret%')] string $secret)
    {
        if ('' === $secret) {
            throw new \LogicException('APP_SECRET must be set to sign guest progress.');
        }

        $this->key = hash_hmac('sha256', self::KEY_PURPOSE, $secret, true);
    }

    /**
     * Returns the guest's progress (empty without a cookie), or null when the cookie is
     * present but not valid: tampered with, malformed or signed with another secret.
     */
    public function read(Request $request): ?GuestProgress
    {
        if (!$request->cookies->has(self::NAME)) {
            return new GuestProgress();
        }

        return $this->decode($request->cookies->getString(self::NAME));
    }

    public function create(Request $request, GuestProgress $progress): Cookie
    {
        return Cookie::create(
            self::NAME,
            $this->encode($progress),
            new \DateTimeImmutable(self::LIFETIME),
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            Cookie::SAMESITE_LAX,
        );
    }

    public function clear(Request $request): Cookie
    {
        return Cookie::create(self::NAME, null, 1, '/', null, $request->isSecure(), true, false, Cookie::SAMESITE_LAX);
    }

    public function encode(GuestProgress $progress): string
    {
        $results = [];
        foreach ($progress->results as $table => $result) {
            $results[$table] = [$result['bestMs'], $result['completedAt']->getTimestamp()];
        }

        // (object): keeps "r" a JSON object, also when the only key is 0 (alle tafels).
        $payload = self::base64UrlEncode(json_encode(['v' => self::VERSION, 'r' => (object) $results], \JSON_THROW_ON_ERROR));

        return $payload.'.'.$this->sign($payload);
    }

    public function decode(string $value): ?GuestProgress
    {
        $parts = explode('.', $value);
        if (2 !== \count($parts) || !hash_equals($this->sign($parts[0]), $parts[1])) {
            return null;
        }

        $json = base64_decode(strtr($parts[0], '-_', '+/'), true);
        try {
            $data = false === $json ? null : json_decode($json, true, 4, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!\is_array($data) || self::VERSION !== ($data['v'] ?? null) || !\is_array($data['r'] ?? null)) {
            return null;
        }

        $now = new \DateTimeImmutable();
        $progress = new GuestProgress();
        foreach ($data['r'] as $table => $result) {
            // Signed by us, so a bad entry can only come from an older version of the rules: skip it.
            if (!\is_int($table) || !self::isKnownTable($table) || !\is_array($result) || 2 !== \count($result)) {
                continue;
            }

            [$durationMs, $completedAt] = [$result[0] ?? null, $result[1] ?? null];
            if (!\is_int($durationMs) || $durationMs < 1 || $durationMs > TafelsResultInput::MAX_DURATION_MS || !\is_int($completedAt) || $completedAt < 0) {
                continue;
            }

            $progress = $progress->withResult($table, $durationMs, min($now, $now->setTimestamp($completedAt)));
        }

        return $progress;
    }

    private function sign(string $payload): string
    {
        return self::base64UrlEncode(hash_hmac('sha256', $payload, $this->key, true));
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function isKnownTable(int $table): bool
    {
        return Tafels::ALL === $table || \in_array($table, Tafels::tables(), true);
    }
}
