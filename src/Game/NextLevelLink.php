<?php

declare(strict_types=1);

namespace App\Game;

use Symfony\Component\Translation\TranslatableMessage;

/**
 * The level recommended after passing one: where it is, and the name the "Volgende: …" button shows.
 */
final readonly class NextLevelLink
{
    public function __construct(
        public string $url,
        public TranslatableMessage $title,
    ) {
    }
}
