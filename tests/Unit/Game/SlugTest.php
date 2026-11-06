<?php

declare(strict_types=1);

namespace App\Tests\Unit\Game;

use App\Game\DicteeGroup;
use App\Game\DicteeLevel;
use App\Game\ErbijErafLevel;
use App\Game\ErbijErafOperation;
use App\Game\TafelsMix;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The route requirement constants of the game enums are attribute constants, so they are written by
 * hand next to slug(). This keeps them in step with the slugs.
 */
final class SlugTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}> the route pattern, and the slugs of all cases
     */
    public static function patterns(): iterable
    {
        yield 'dictee groep' => [DicteeGroup::SLUG_PATTERN, array_map(static fn (DicteeGroup $case): string => $case->slug(), DicteeGroup::cases())];
        yield 'dictee level' => [DicteeLevel::SLUG_PATTERN, array_map(static fn (DicteeLevel $case): string => $case->slug(), DicteeLevel::cases())];
        yield 'erbij en eraf level' => [ErbijErafLevel::SLUG_PATTERN, array_map(static fn (ErbijErafLevel $case): string => $case->slug(), ErbijErafLevel::cases())];
        yield 'erbij en eraf operation' => [ErbijErafOperation::PATTERN, array_map(static fn (ErbijErafOperation $case): string => $case->value, ErbijErafOperation::cases())];
        yield 'tafels mix' => [TafelsMix::SLUG_PATTERN, array_map(static fn (TafelsMix $case): string => $case->slug(), TafelsMix::cases())];
    }

    /**
     * @param list<string> $slugs
     */
    #[DataProvider('patterns')]
    public function testPatternMatchesEverySlug(string $pattern, array $slugs): void
    {
        foreach ($slugs as $slug) {
            self::assertMatchesRegularExpression('/^(?:'.$pattern.')$/', $slug);
        }
    }

    /**
     * @param list<string> $slugs
     */
    #[DataProvider('patterns')]
    public function testPatternMatchesNothingElse(string $pattern, array $slugs): void
    {
        if (str_contains($pattern, '(')) {
            // Nested groups can't be split into alternatives: only the slugs themselves are checked.
            self::assertMatchesRegularExpression('/^(?:'.$pattern.')$/', $slugs[0]);
            self::assertDoesNotMatchRegularExpression('/^(?:'.$pattern.')$/', 'onbekend');

            return;
        }

        $alternatives = explode('|', $pattern);
        sort($alternatives);
        sort($slugs);

        self::assertSame($slugs, $alternatives);
    }

    public function testCasesAreFoundBySlug(): void
    {
        foreach (DicteeGroup::cases() as $case) {
            self::assertSame($case, DicteeGroup::fromSlug($case->slug()));
        }
        foreach (DicteeLevel::cases() as $case) {
            self::assertSame($case, DicteeLevel::fromSlug($case->slug()));
        }
        foreach (ErbijErafLevel::cases() as $case) {
            self::assertSame($case, ErbijErafLevel::fromSlug($case->slug()));
        }
        foreach (TafelsMix::cases() as $case) {
            self::assertSame($case, TafelsMix::fromSlug($case->slug()));
        }

        self::assertNull(DicteeGroup::fromSlug('groep-5'));
        self::assertNull(DicteeLevel::fromSlug('raden'));
        self::assertNull(ErbijErafLevel::fromSlug('tot-1000'));
        self::assertNull(TafelsMix::fromSlug('2-tot-7'));
    }

    public function testTranslationKeysUseUnderscores(): void
    {
        self::assertSame('groep_6', DicteeGroup::Groep6->key());
        self::assertSame('zonder_hulp', DicteeLevel::Hidden->key());
        self::assertSame('tot_20_brug', ErbijErafLevel::Tot20Brug->key());
        self::assertSame('door_elkaar', ErbijErafOperation::DoorElkaar->key());
    }
}
