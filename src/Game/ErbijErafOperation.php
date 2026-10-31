<?php

declare(strict_types=1);

namespace App\Game;

/**
 * What an erbij-en-eraf round asks: plussommen, minsommen or both (5 + 5). The value is the
 * URL slug and the name the JS logic uses; code() is part of the stored level.
 */
enum ErbijErafOperation: string
{
    case Erbij = 'erbij';
    case Eraf = 'eraf';
    case DoorElkaar = 'door-elkaar';

    /** Route requirement matching the values. */
    public const string PATTERN = 'erbij|eraf|door-elkaar';

    public static function fromCode(int $code): ?self
    {
        foreach (self::cases() as $operation) {
            if ($operation->code() === $code) {
                return $operation;
            }
        }

        return null;
    }

    /** The next case in order, recommended after passing this one; null after the last. */
    public function next(): ?self
    {
        $cases = self::cases();

        return $cases[array_search($this, $cases, true) + 1] ?? null;
    }

    public function code(): int
    {
        return match ($this) {
            self::Erbij => 1,
            self::Eraf => 2,
            self::DoorElkaar => 3,
        };
    }

    /** Translation keys live under game.rekenen.erbij_eraf.operations.<key>. */
    public function key(): string
    {
        return str_replace('-', '_', $this->value);
    }
}
