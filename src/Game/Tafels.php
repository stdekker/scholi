<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Rules of the tafels (multiplication tables) game that the server needs to know.
 *
 * The time limits are the single source of truth: they are passed to the Stimulus controller
 * (assets/controllers/tafels_controller.js) so the client and the stored results agree.
 *
 * "Alle tafels" rounds mix several tables: the fixed TafelsMix ranges (stored under their own
 * table numbers) and "geoefende tafels". They have 20 sums (count lives in
 * assets/games/rekenen/tafels.js, ALL_TABLES_COUNT) and twice the time.
 */
final class Tafels
{
    public const int MIN_TABLE = 1;
    public const int MAX_TABLE = 12;
    /** Route requirement matching MIN_TABLE..MAX_TABLE. */
    public const string TABLE_PATTERN = '[1-9]|1[0-2]';
    public const int TIME_LIMIT_MS = RoundTime::ONE_MINUTE_MS;
    public const int ALL_TIME_LIMIT_MS = RoundTime::TWO_MINUTES_MS;
    /** "Geoefende tafels" needs this many finished tables to make 20 different sums. */
    public const int MIN_PRACTISED_TABLES = 2;

    /**
     * @return list<int>
     */
    public static function tables(): array
    {
        return range(self::MIN_TABLE, self::MAX_TABLE);
    }

    /**
     * Whether results may be stored under this table number: a table or a fixed mix.
     */
    public static function isKnown(int $table): bool
    {
        return \in_array($table, self::tables(), true) || null !== TafelsMix::tryFrom($table);
    }

    /**
     * @param int $table a table, or a TafelsMix value
     */
    public static function timeLimitMs(int $table): int
    {
        return null === TafelsMix::tryFrom($table) ? self::TIME_LIMIT_MS : self::ALL_TIME_LIMIT_MS;
    }

    /**
     * The tables with at least one finished round, for "geoefende tafels".
     *
     * @param array<int, LevelProgress> $progress keyed by table number
     *
     * @return list<int>
     */
    public static function practisedTables(array $progress): array
    {
        return array_values(array_filter(self::tables(), static fn (int $table): bool => isset($progress[$table])));
    }
}
