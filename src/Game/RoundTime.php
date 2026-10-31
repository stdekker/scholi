<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Times of the bubble games, as the clock in the game shows them.
 */
final class RoundTime
{
    public const int ONE_MINUTE_MS = 60_000;
    public const int TWO_MINUTES_MS = 120_000;

    /**
     * Formats milliseconds as m:ss, rounding up (mirrors formatTime() in assets/games/shared/format.js).
     */
    public static function format(int $ms): string
    {
        $seconds = (int) ceil(max(0, $ms) / 1000);

        return \sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    /**
     * Translation key that finishes "binnen …" for a time limit: "een minuut" or "twee minuten".
     */
    public static function withinKey(int $timeLimitMs): string
    {
        return $timeLimitMs > self::ONE_MINUTE_MS ? 'game.bubbles.within.two_minutes' : 'game.bubbles.within.one_minute';
    }
}
