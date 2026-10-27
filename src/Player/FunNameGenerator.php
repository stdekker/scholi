<?php

declare(strict_types=1);

namespace App\Player;

use Random\Randomizer;

/**
 * Suggests a playful Dutch player name such as "Vrolijke Vos" plus its animal emoji 🦊,
 * so pupils don't need to use their real name. The emoji is a separate player field.
 * These word lists are content data, not UI strings.
 *
 * - Adjectives are positive and already inflected ("-e"), which is only correct for de-words,
 *   so every animal is a de-word (no paard, konijn, schaap, …).
 * - Emoji are limited to Emoji 13.0 or older without ZWJ sequences, so they render on
 *   school Chromebooks and older tablets.
 * - Every combination fits Player::NAME_MAX_LENGTH.
 * - emojis() is also the list pupils can pick from in the emoji popup.
 */
final class FunNameGenerator
{
    public const array ADJECTIVES = [
        'Blije', 'Coole', 'Creatieve', 'Dappere', 'Eerlijke', 'Energieke', 'Fantastische',
        'Gave', 'Geweldige', 'Gezellige', 'Grappige', 'Handige', 'Heldhaftige', 'Kleurrijke',
        'Knappe', 'Lieve', 'Moedige', 'Nieuwsgierige', 'Pientere', 'Prachtige', 'Rustige',
        'Slimme', 'Snelle', 'Speelse', 'Sportieve', 'Sterke', 'Stoere', 'Stralende', 'Toffe',
        'Trotse', 'Vlotte', 'Vriendelijke', 'Vrolijke', 'Wijze', 'Zonnige',
    ];

    /** Animal (de-word) => emoji. */
    public const array ANIMALS = [
        'Aap' => '🐵', 'Beer' => '🐻', 'Bever' => '🦫', 'Bij' => '🐝', 'Dinosaurus' => '🦕',
        'Dolfijn' => '🐬', 'Draak' => '🐉', 'Eekhoorn' => '🐿️', 'Eend' => '🦆', 'Eenhoorn' => '🦄',
        'Egel' => '🦔', 'Flamingo' => '🦩', 'Giraf' => '🦒', 'Gorilla' => '🦍', 'Haai' => '🦈',
        'Haan' => '🐓', 'Hamster' => '🐹', 'Hond' => '🐶', 'Inktvis' => '🦑', 'Kameel' => '🐫',
        'Kangoeroe' => '🦘', 'Kat' => '🐱', 'Kikker' => '🐸', 'Kip' => '🐔', 'Koala' => '🐨',
        'Koe' => '🐮', 'Krab' => '🦀', 'Kreeft' => '🦞', 'Krokodil' => '🐊', 'Lama' => '🦙',
        'Leeuw' => '🦁', 'Mammoet' => '🦣', 'Mier' => '🐜', 'Muis' => '🐭', 'Neushoorn' => '🦏',
        'Octopus' => '🐙', 'Olifant' => '🐘', 'Otter' => '🦦', 'Panda' => '🐼', 'Papegaai' => '🦜',
        'Pauw' => '🦚', 'Pinguïn' => '🐧', 'Schildpad' => '🐢', 'Slak' => '🐌', 'Tijger' => '🐯',
        'Uil' => '🦉', 'Vis' => '🐟', 'Vleermuis' => '🦇', 'Vlinder' => '🦋', 'Vos' => '🦊',
        'Walvis' => '🐳', 'Wasbeer' => '🦝', 'Wolf' => '🐺', 'Zebra' => '🦓', 'Zeehond' => '🦭',
        'Zwaan' => '🦢',
    ];

    private readonly Randomizer $randomizer;

    public function __construct(?Randomizer $randomizer = null)
    {
        $this->randomizer = $randomizer ?? new Randomizer();
    }

    /**
     * Emoji a player may choose, keyed by Dutch animal name.
     *
     * @return array<string, string>
     */
    public static function emojis(): array
    {
        return self::ANIMALS;
    }

    /**
     * @return array{name: string, emoji: string}
     */
    public function generate(): array
    {
        $adjective = self::ADJECTIVES[$this->randomizer->getInt(0, \count(self::ADJECTIVES) - 1)];
        $animals = array_keys(self::ANIMALS);
        $animal = $animals[$this->randomizer->getInt(0, \count($animals) - 1)];

        return ['name' => $adjective.' '.$animal, 'emoji' => self::ANIMALS[$animal]];
    }

    /**
     * Different suggestions for the "Andere naam" button on the registration form.
     *
     * @param positive-int $count
     *
     * @return non-empty-list<array{name: string, emoji: string}>
     */
    public function suggestions(int $count): array
    {
        $suggestions = [];
        while (\count($suggestions) < $count) {
            $suggestion = $this->generate();
            $suggestions[$suggestion['name']] = $suggestion;
        }

        return array_values($suggestions);
    }
}
