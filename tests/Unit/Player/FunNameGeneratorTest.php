<?php

declare(strict_types=1);

namespace App\Tests\Unit\Player;

use App\Entity\Player;
use App\Player\FunNameGenerator;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

final class FunNameGeneratorTest extends TestCase
{
    private const string NAME_PATTERN = '/^\p{Lu}\p{Ll}+e \p{Lu}\p{Ll}+$/u';
    private const string EMOJI_PATTERN = '/^\p{Extended_Pictographic}\x{FE0F}?$/u';

    public function testGeneratesNameWithMatchingAnimalEmoji(): void
    {
        $generator = new FunNameGenerator();

        for ($i = 0; $i < 100; ++$i) {
            $suggestion = $generator->generate();
            $animal = explode(' ', $suggestion['name'])[1];

            self::assertMatchesRegularExpression(self::NAME_PATTERN, $suggestion['name']);
            self::assertSame(FunNameGenerator::emojis()[$animal], $suggestion['emoji']);
        }
    }

    public function testEveryCombinationFitsTheFields(): void
    {
        $longest = max(array_map(mb_strlen(...), FunNameGenerator::ADJECTIVES))
            + 1 + max(array_map(mb_strlen(...), array_keys(FunNameGenerator::ANIMALS)));
        self::assertLessThanOrEqual(Player::NAME_MAX_LENGTH, $longest);

        foreach (FunNameGenerator::emojis() as $emoji) {
            self::assertMatchesRegularExpression(self::EMOJI_PATTERN, $emoji);
            self::assertLessThanOrEqual(16, mb_strlen($emoji), 'Player::$emoji column length');
        }
    }

    public function testWordListsHaveNoDuplicates(): void
    {
        self::assertSame(FunNameGenerator::ADJECTIVES, array_values(array_unique(FunNameGenerator::ADJECTIVES)));
        self::assertSame(array_values(FunNameGenerator::ANIMALS), array_values(array_unique(FunNameGenerator::ANIMALS)), 'Each animal has its own emoji');
        self::assertGreaterThanOrEqual(30, \count(FunNameGenerator::ADJECTIVES));
        self::assertGreaterThanOrEqual(50, \count(FunNameGenerator::ANIMALS));
    }

    public function testSuggestionsAreDifferentNames(): void
    {
        $names = array_column((new FunNameGenerator())->suggestions(20), 'name');

        self::assertCount(20, array_unique($names));
    }

    public function testIsDeterministicForASeededRandomizer(): void
    {
        $first = new FunNameGenerator(new Randomizer(new Mt19937(42)));
        $second = new FunNameGenerator(new Randomizer(new Mt19937(42)));

        self::assertSame($first->suggestions(5), $second->suggestions(5));
    }
}
