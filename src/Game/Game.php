<?php

declare(strict_types=1);

namespace App\Game;

/**
 * The games whose rounds are saved. Each stores its results per "level": a number whose meaning
 * belongs to the game (tafels: the table or a TafelsMix value; erbij en eraf: ErbijEraf::storedLevel()).
 *
 * The value is stored in the database and in the guest cookie: never change it.
 */
enum Game: string
{
    case Tafels = 'tafels';
    case ErbijEraf = 'erbij-eraf';

    public function isKnownLevel(int $level): bool
    {
        return match ($this) {
            self::Tafels => Tafels::isKnown($level),
            self::ErbijEraf => ErbijEraf::isKnown($level),
        };
    }

    /**
     * The time limit of a round, also passed to the game's Stimulus controller so the client's
     * verdict and the stored result always agree.
     */
    public function timeLimitMs(int $level): int
    {
        return match ($this) {
            self::Tafels => Tafels::timeLimitMs($level),
            self::ErbijEraf => ErbijEraf::timeLimitMs($level),
        };
    }

    public function isPassed(int $level, int $durationMs): bool
    {
        return $durationMs <= $this->timeLimitMs($level);
    }
}
