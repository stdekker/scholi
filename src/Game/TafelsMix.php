<?php

declare(strict_types=1);

namespace App\Game;

/**
 * The fixed "alle tafels" mixes: 20 sums from a range of tables, with a two-minute clock.
 *
 * The value is the table number its results are stored under (outside 1–12). "2 tot 10" keeps 0,
 * the number "alle tafels" was stored under before there were mixes, so existing results and
 * guest cookies stay valid. "Geoefende tafels" is not a mix: its tables differ per pupil, so its
 * rounds are never saved.
 */
enum TafelsMix: int
{
    use HasNextCase;
    use HasSlug;

    case TwoToFive = -5;
    case TwoToTen = 0;
    case TwoToTwelve = -12;

    /** Route requirement matching slug(). */
    public const string SLUG_PATTERN = '2-tot-(?:5|10|12)';

    public function slug(): string
    {
        return \sprintf('2-tot-%d', $this->lastTable());
    }

    /**
     * @return list<int>
     */
    public function tables(): array
    {
        return range(2, $this->lastTable());
    }

    public function lastTable(): int
    {
        return match ($this) {
            self::TwoToFive => 5,
            self::TwoToTen => 10,
            self::TwoToTwelve => 12,
        };
    }
}
