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
    case TwoToFive = -5;
    case TwoToTen = 0;
    case TwoToTwelve = -12;

    /** Route requirement matching slug(). */
    public const string SLUG_PATTERN = '2-tot-(?:5|10|12)';

    public static function fromSlug(string $slug): ?self
    {
        foreach (self::cases() as $mix) {
            if ($mix->slug() === $slug) {
                return $mix;
            }
        }

        return null;
    }

    /** The mix to recommend after passing this one; null after the last. */
    public function next(): ?self
    {
        return match ($this) {
            self::TwoToFive => self::TwoToTen,
            self::TwoToTen => self::TwoToTwelve,
            self::TwoToTwelve => null,
        };
    }

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
