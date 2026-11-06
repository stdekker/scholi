<?php

declare(strict_types=1);

namespace App\Game;

/**
 * The groep (school year) whose words a dictee round asks. Each has its own list in
 * dictee_words.json, keyed by the value. More groups follow later; the value is part of the
 * stored level (Dictee::storedLevel()).
 */
enum DicteeGroup: int
{
    case Groep6 = 6;
    case Groep8 = 8;

    /** Route requirement matching slug(). */
    public const string SLUG_PATTERN = 'groep-6|groep-8';

    public static function fromSlug(string $slug): ?self
    {
        foreach (self::cases() as $group) {
            if ($group->slug() === $slug) {
                return $group;
            }
        }

        return null;
    }

    public function slug(): string
    {
        return 'groep-'.$this->value;
    }

    /** Translation keys live under game.taal.dictee.groups.<key>. */
    public function key(): string
    {
        return 'groep_'.$this->value;
    }
}
