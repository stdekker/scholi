<?php

declare(strict_types=1);

namespace App\Game;

/**
 * For enums whose cases are played in the order they are declared: the case to recommend after
 * passing this one.
 *
 * @phpstan-require-implements \UnitEnum
 */
trait HasNextCase
{
    /** The next case in order, recommended after passing this one; null after the last. */
    public function next(): ?self
    {
        $cases = self::cases();

        return $cases[array_search($this, $cases, true) + 1] ?? null;
    }
}
