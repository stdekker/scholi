<?php

declare(strict_types=1);

namespace App\Tests\Unit\Game;

use App\Entity\Player;
use App\Entity\TafelsResult;
use App\Game\TableProgress;
use App\Game\Tafels;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TableProgressTest extends TestCase
{
    public function testKeepsBestTimePerTableAndAnyPass(): void
    {
        $player = new Player('Slimme Vos', str_repeat('a', 64), new \DateTimeImmutable());
        $result = static fn (int $table, int $ms): TafelsResult => new TafelsResult($player, $table, $ms, new \DateTimeImmutable());

        $progress = TableProgress::fromResults([
            $result(7, 90_000),
            $result(3, 70_000),
            $result(7, 55_000),
            $result(7, 80_000),
        ]);

        self::assertSame([3, 7], array_keys($progress));
        self::assertEquals(new TableProgress(3, 70_000, false), $progress[3]);
        self::assertEquals(new TableProgress(7, 55_000, true), $progress[7]);
        self::assertSame('0:55', $progress[7]->bestTime());
    }

    public function testNoResultsMeansNoProgress(): void
    {
        self::assertSame([], TableProgress::fromResults([]));
    }

    /**
     * @return iterable<array{int, string}>
     */
    public static function times(): iterable
    {
        yield [60_000, '1:00'];
        yield [59_001, '1:00'];
        yield [59_000, '0:59'];
        yield [75_000, '1:15'];
        yield [1, '0:01'];
        yield [0, '0:00'];
    }

    #[DataProvider('times')]
    public function testFormatTimeMatchesTheGameClock(int $ms, string $expected): void
    {
        self::assertSame($expected, Tafels::formatTime($ms));
    }

    public function testPassedIsWithinTheTimeLimit(): void
    {
        self::assertTrue(Tafels::isPassed(6, 60_000));
        self::assertFalse(Tafels::isPassed(6, 60_001));
    }

    public function testAlleTafelsGetsTwoMinutes(): void
    {
        self::assertSame(120_000, Tafels::timeLimitMs(Tafels::ALL));
        self::assertTrue(Tafels::isPassed(Tafels::ALL, 120_000));
        self::assertFalse(Tafels::isPassed(Tafels::ALL, 120_001));
    }
}
