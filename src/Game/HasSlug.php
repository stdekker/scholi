<?php

declare(strict_types=1);

namespace App\Game;

/**
 * For enums whose cases have a URL slug (slug()): finds a case by its slug, and gives the
 * segment of its translation keys.
 *
 * The enum also declares a SLUG_PATTERN constant for its route requirements, which must match
 * exactly its slugs (tests/Unit/Game/SlugTest.php checks it).
 *
 * @phpstan-require-implements \UnitEnum
 */
trait HasSlug
{
    /** The URL segment of this case. */
    abstract public function slug(): string;

    public static function fromSlug(string $slug): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->slug() === $slug) {
                return $case;
            }
        }

        return null;
    }

    /** The segment of the translation keys of this case: its slug with underscores. */
    public function key(): string
    {
        return str_replace('-', '_', $this->slug());
    }
}
