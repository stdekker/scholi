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
    use HasSlug;

    case Groep6 = 6;
    case Groep8 = 8;

    /** Route requirement matching slug(). */
    public const string SLUG_PATTERN = 'groep-6|groep-8';

    public function slug(): string
    {
        return 'groep-'.$this->value;
    }
}
