<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Rules of the tafels (multiplication tables) game that the server needs to know.
 *
 * TIME_LIMIT_MS is the single source of truth: it is passed to the Stimulus controller
 * (assets/controllers/tafels_controller.js) so the client and the stored results agree.
 */
final class Tafels
{
    public const int MIN_TABLE = 2;
    public const int MAX_TABLE = 10;
    /** Route requirement matching MIN_TABLE..MAX_TABLE. */
    public const string TABLE_PATTERN = '[2-9]|10';
    public const int TIME_LIMIT_MS = 60_000;

    /**
     * @return list<int>
     */
    public static function tables(): array
    {
        return range(self::MIN_TABLE, self::MAX_TABLE);
    }

    public static function isPassed(int $durationMs): bool
    {
        return $durationMs <= self::TIME_LIMIT_MS;
    }

    /**
     * Formats milliseconds as m:ss, rounding up (mirrors formatTime() in assets/games/rekenen/tafels.js).
     */
    public static function formatTime(int $ms): string
    {
        $seconds = (int) ceil(max(0, $ms) / 1000);

        return \sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
