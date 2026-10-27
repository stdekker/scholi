<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Rules of the tafels (multiplication tables) game that the server needs to know.
 *
 * The time limits are the single source of truth: they are passed to the Stimulus controller
 * (assets/controllers/tafels_controller.js) so the client and the stored results agree.
 *
 * "Alle tafels" is stored as table number ALL; its round has 20 sums (count lives in
 * assets/games/rekenen/tafels.js, ALL_TABLES_COUNT) and twice the time.
 */
final class Tafels
{
    public const int MIN_TABLE = 2;
    public const int MAX_TABLE = 10;
    /** Route requirement matching MIN_TABLE..MAX_TABLE. */
    public const string TABLE_PATTERN = '[2-9]|10';
    public const int TIME_LIMIT_MS = 60_000;
    /** Table number of an "alle tafels" round (mirrors ALL_TABLES in tafels.js). */
    public const int ALL = 0;
    public const int ALL_TIME_LIMIT_MS = 120_000;

    /**
     * @return list<int>
     */
    public static function tables(): array
    {
        return range(self::MIN_TABLE, self::MAX_TABLE);
    }

    public static function timeLimitMs(int $table): int
    {
        return self::ALL === $table ? self::ALL_TIME_LIMIT_MS : self::TIME_LIMIT_MS;
    }

    public static function isPassed(int $table, int $durationMs): bool
    {
        return $durationMs <= self::timeLimitMs($table);
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
