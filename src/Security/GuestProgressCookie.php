<?php

declare(strict_types=1);

namespace App\Security;

use App\Game\Game;
use App\Game\RoundResultInput;
use App\Player\GuestProgress;
use App\Player\PracticeTime;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * Keeps a guest's progress in the browser: an HttpOnly cookie signed with an HMAC, so it only
 * ever holds results the server accepted. It moves to the account when the guest makes a player.
 *
 * Value: base64url({"v":2,"r":{"<game>":{"<level>":[bestMs, completedAtUnix]}},"p":{"<Y-m-d>":seconds}}) . "." . base64url(hmac)
 * "p" (practice time per day, see PracticeTime) is optional: older version 2 cookies lack it.
 * Version 1 had only tafels: {"v":1,"r":{"<table>":[…]}}. It is still read, so guests keep their stars.
 */
final readonly class GuestProgressCookie
{
    public const string NAME = 'scholi_guest';
    private const string LIFETIME = '+400 days';
    private const int VERSION = 2;
    private const int VERSION_TAFELS_ONLY = 1;
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
        foreach ($progress->results as $game => $levels) {
            $entries = [];
            foreach ($levels as $level => $result) {
                $entries[$level] = [$result['bestMs'], $result['completedAt']->getTimestamp()];
            }
            // (object): keeps the levels a JSON object, also when the only key is 0 (tafels 2 tot 10).
            $results[$game] = (object) $entries;
        }

        $data = ['v' => self::VERSION, 'r' => (object) $results];
        if ([] !== $progress->practice) {
            $data['p'] = $progress->practice;
        }
        $payload = self::base64UrlEncode(json_encode($data, \JSON_THROW_ON_ERROR));

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
            $data = false === $json ? null : json_decode($json, true, 5, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!\is_array($data) || !\is_array($data['r'] ?? null)) {
            return null;
        }

        $results = match ($data['v'] ?? null) {
            self::VERSION => $data['r'],
            self::VERSION_TAFELS_ONLY => [Game::Tafels->value => $data['r']],
            default => null,
        };
        if (null === $results) {
            return null;
        }

        $now = new \DateTimeImmutable();
        $progress = new GuestProgress();
        foreach ($results as $gameValue => $levels) {
            $game = \is_string($gameValue) ? Game::tryFrom($gameValue) : null;
            if (null === $game || !\is_array($levels)) {
                continue;
            }

            foreach ($levels as $level => $result) {
                // Signed by us, so a bad entry can only come from an older version of the rules: skip it.
                if (!\is_int($level) || !$game->isKnownLevel($level) || !\is_array($result) || 2 !== \count($result)) {
                    continue;
                }

                [$durationMs, $completedAt] = [$result[0] ?? null, $result[1] ?? null];
                if (!\is_int($durationMs) || $durationMs < 1 || $durationMs > RoundResultInput::MAX_DURATION_MS || !\is_int($completedAt) || $completedAt < 0) {
                    continue;
                }

                $progress = $progress->withResult($game, $level, $durationMs, min($now, $now->setTimestamp($completedAt)));
            }
        }

        $windowStart = PracticeTime::windowStart()->format('Y-m-d');
        $today = PracticeTime::today()->format('Y-m-d');
        foreach (\is_array($data['p'] ?? null) ? $data['p'] : [] as $day => $seconds) {
            // Days outside the window no longer count; skip anything that isn't a plain day and a sane number.
            if (!\is_string($day) || 1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || $day > $today
                || !\is_int($seconds) || $seconds < 1 || $seconds > PracticeTime::MAX_DAY_SECONDS) {
                continue;
            }

            $progress = $progress->withPractice($day, $seconds, $windowStart);
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
}
