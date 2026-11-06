<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Levels of dictee: how much of the spoken word the pupil gets to see. The value is part of the
 * stored level (Dictee::storedLevel()). The hint itself is made in assets/games/taal/dictee.js,
 * which knows the level by its mode().
 */
enum DicteeLevel: int
{
    case Copy = 1;
    case Partial = 2;
    case Blanks = 3;
    case Hidden = 4;

    /** Route requirement matching slug(). */
    public const string SLUG_PATTERN = 'overschrijven|aanvullen|streepjes|zonder-hulp';

    public static function fromSlug(string $slug): ?self
    {
        foreach (self::cases() as $level) {
            if ($level->slug() === $slug) {
                return $level;
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

    public function slug(): string
    {
        return match ($this) {
            self::Copy => 'overschrijven',
            self::Partial => 'aanvullen',
            self::Blanks => 'streepjes',
            self::Hidden => 'zonder-hulp',
        };
    }

    /** Translation keys live under game.taal.dictee.levels.<key>. */
    public function key(): string
    {
        return str_replace('-', '_', $this->slug());
    }

    /**
     * What the pupil sees of the word, passed to the game's Stimulus controller:
     * "full" (all letters), "partial" (some letters), "blanks" (only a line per letter) or
     * "hidden" (nothing).
     */
    public function mode(): string
    {
        return match ($this) {
            self::Copy => 'full',
            self::Partial => 'partial',
            self::Blanks => 'blanks',
            self::Hidden => 'hidden',
        };
    }

    /** Ten words take a while to listen to and write, so every level gets three minutes. */
    public function timeLimitMs(): int
    {
        return RoundTime::THREE_MINUTES_MS;
    }
}
