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
    public function testGeneratesAdjectiveAndAnimal(): void
    {
        $generator = new FunNameGenerator();

        for ($i = 0; $i < 50; ++$i) {
            $name = $generator->generate();

            self::assertMatchesRegularExpression('/^\p{Lu}\p{Ll}+e \p{Lu}\p{Ll}+$/u', $name);
            self::assertLessThanOrEqual(Player::NAME_MAX_LENGTH, mb_strlen($name));
        }
    }

    public function testIsDeterministicForASeededRandomizer(): void
    {
        $first = new FunNameGenerator(new Randomizer(new Mt19937(42)));
        $second = new FunNameGenerator(new Randomizer(new Mt19937(42)));

        self::assertSame($first->generate(), $second->generate());
    }
}
