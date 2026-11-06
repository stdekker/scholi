<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Levels of erbij en eraf, roughly groep 3 to 5. The sums of each level are made in
 * assets/games/rekenen/erbij_eraf.js; the value is part of the stored level (ErbijEraf::storedLevel()).
 */
enum ErbijErafLevel: int
{
    use HasNextCase;
    use HasSlug;

    case Tot10 = 1;
    case Tot20 = 2;
    case Tot20Brug = 3;
    case Tientallen = 4;
    case Tot100 = 5;
    case Tot100Brug = 6;

    /** Route requirement matching slug(). */
    public const string SLUG_PATTERN = 'tot-10|tot-20|tot-20-brug|tientallen|tot-100|tot-100-brug';

    public function slug(): string
    {
        return match ($this) {
            self::Tot10 => 'tot-10',
            self::Tot20 => 'tot-20',
            self::Tot20Brug => 'tot-20-brug',
            self::Tientallen => 'tientallen',
            self::Tot100 => 'tot-100',
            self::Tot100Brug => 'tot-100-brug',
        };
    }

    /** One minute up to tientallen; the two "tot 100" levels get two. */
    public function timeLimitMs(): int
    {
        return match ($this) {
            self::Tot100, self::Tot100Brug => RoundTime::TWO_MINUTES_MS,
            default => RoundTime::ONE_MINUTE_MS,
        };
    }
}
