<?php

declare(strict_types=1);

namespace App\Tests\Unit\Game;

use App\Entity\GameResult;
use App\Entity\Player;
use App\Game\ErbijEraf;
use App\Game\ErbijErafLevel;
use App\Game\ErbijErafOperation;
use App\Game\Game;
use App\Game\LevelProgress;
use App\Game\RoundTime;
use App\Game\TafelsMix;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LevelProgressTest extends TestCase
{
    public function testKeepsBestTimePerLevelAndAnyPass(): void
    {
        $player = new Player('Slimme Vos', str_repeat('a', 64), new \DateTimeImmutable());
        $result = static fn (int $table, int $ms): GameResult => new GameResult($player, Game::Tafels, $table, $ms, new \DateTimeImmutable());

        $progress = LevelProgress::fromResults([
            $result(7, 90_000),
            $result(3, 70_000),
            $result(7, 55_000),
            $result(7, 80_000),
        ]);

        self::assertSame([3, 7], array_keys($progress));
        self::assertEquals(new LevelProgress(3, 70_000, false, 60_000), $progress[3]);
        self::assertEquals(new LevelProgress(7, 55_000, true, 60_000), $progress[7]);
        self::assertSame('0:55', $progress[7]->bestTime());
        self::assertFalse($progress[7]->hasTwoMinutes());
    }

    public function testNoResultsMeansNoProgress(): void
    {
        self::assertSame([], LevelProgress::fromResults([]));
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
        self::assertSame($expected, RoundTime::format($ms));
    }

    public function testPassedIsWithinTheTimeLimit(): void
    {
        self::assertTrue(Game::Tafels->isPassed(6, 60_000));
        self::assertFalse(Game::Tafels->isPassed(6, 60_001));
    }

    public function testAlleTafelsGetsTwoMinutes(): void
    {
        self::assertSame(120_000, Game::Tafels->timeLimitMs(TafelsMix::TwoToTen->value));
        self::assertTrue(Game::Tafels->isPassed(TafelsMix::TwoToTen->value, 120_000));
        self::assertFalse(Game::Tafels->isPassed(TafelsMix::TwoToTen->value, 120_001));
    }

    public function testErbijErafGetsTwoMinutesOnlyUpToHundred(): void
    {
        $stored = static fn (ErbijErafLevel $level): int => ErbijEraf::storedLevel($level, ErbijErafOperation::Eraf);

        self::assertSame(60_000, Game::ErbijEraf->timeLimitMs($stored(ErbijErafLevel::Tot10)));
        self::assertSame(60_000, Game::ErbijEraf->timeLimitMs($stored(ErbijErafLevel::Tientallen)));
        self::assertSame(120_000, Game::ErbijEraf->timeLimitMs($stored(ErbijErafLevel::Tot100)));
        self::assertSame(120_000, Game::ErbijEraf->timeLimitMs($stored(ErbijErafLevel::Tot100Brug)));
    }

    public function testErbijErafStoresLevelAndOperation(): void
    {
        self::assertSame(32, ErbijEraf::storedLevel(ErbijErafLevel::Tot20Brug, ErbijErafOperation::Eraf));
        self::assertSame([ErbijErafLevel::Tot100Brug, ErbijErafOperation::DoorElkaar], ErbijEraf::fromStoredLevel(63));
        self::assertCount(18, array_merge(...array_map(static fn (array $row): array => $row['rounds'], ErbijEraf::grid())));

        foreach ([0, 6, 10, 14, 70, -11] as $unknown) {
            self::assertFalse(Game::ErbijEraf->isKnownLevel($unknown), (string) $unknown);
        }
        self::assertTrue(Game::ErbijEraf->isKnownLevel(11));
    }
}
