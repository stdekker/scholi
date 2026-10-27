<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\PlayerToken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlayerTokenTest extends TestCase
{
    public function testGeneratesRandomWellFormedTokens(): void
    {
        $first = PlayerToken::generate();
        $second = PlayerToken::generate();

        self::assertSame(PlayerToken::LENGTH, \strlen($first));
        self::assertTrue(PlayerToken::isWellFormed($first));
        self::assertNotSame($first, $second);
    }

    public function testHashIsSha256(): void
    {
        self::assertSame(hash('sha256', 'abc'), PlayerToken::hash('abc'));
        self::assertSame(64, \strlen(PlayerToken::hash(PlayerToken::generate())));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedTokens(): iterable
    {
        yield 'route word' => ['nieuw'];
        yield 'too short' => [str_repeat('a', 31)];
        yield 'too long' => [str_repeat('a', 33)];
        yield 'dash' => [str_repeat('a', 31).'-'];
        yield 'trailing newline' => [str_repeat('a', 32)."\n"];
    }

    #[DataProvider('malformedTokens')]
    public function testRejectsMalformedTokens(string $token): void
    {
        self::assertFalse(PlayerToken::isWellFormed($token));
    }
}
