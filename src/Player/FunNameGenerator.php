<?php

declare(strict_types=1);

namespace App\Player;

use Random\Randomizer;

/**
 * Suggests a playful Dutch player name such as "Vrolijke Vos", so pupils don't need to
 * use their real name. These word lists are content data, not UI strings.
 * Animals are de-words only: the adjective ending "-e" would be wrong for het-words.
 */
final class FunNameGenerator
{
    private const array ADJECTIVES = [
        'Blije', 'Dappere', 'Grappige', 'Handige', 'Rustige', 'Slimme', 'Snelle',
        'Sterke', 'Stoere', 'Vrolijke', 'Wijze', 'Knappe', 'Coole',
    ];

    private const array ANIMALS = [
        'Bever', 'Dolfijn', 'Draak', 'Eekhoorn', 'Egel', 'Giraf', 'Haai', 'Kikker',
        'Koala', 'Leeuw', 'Olifant', 'Otter', 'Panda', 'Pinguïn', 'Schildpad',
        'Tijger', 'Uil', 'Vos', 'Walvis', 'Wasbeer', 'Zebra',
    ];

    private readonly Randomizer $randomizer;

    public function __construct(?Randomizer $randomizer = null)
    {
        $this->randomizer = $randomizer ?? new Randomizer();
    }

    public function generate(): string
    {
        return $this->pick(self::ADJECTIVES).' '.$this->pick(self::ANIMALS);
    }

    /**
     * @param non-empty-list<string> $words
     */
    private function pick(array $words): string
    {
        return $words[$this->randomizer->getInt(0, \count($words) - 1)];
    }
}
