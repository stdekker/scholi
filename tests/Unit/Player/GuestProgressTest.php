<?php

declare(strict_types=1);

namespace App\Tests\Unit\Player;

use App\Game\TafelsMix;
use App\Player\GuestProgress;
use PHPUnit\Framework\TestCase;

final class GuestProgressTest extends TestCase
{
    public function testKeepsTheBestTimePerTable(): void
    {
        $first = new \DateTimeImmutable('2026-10-20 10:00');
        $later = new \DateTimeImmutable('2026-10-21 10:00');

        $progress = (new GuestProgress())
            ->withResult(6, 50_000, $first)
            ->withResult(6, 70_000, $later)
            ->withResult(6, 50_000, $later)
            ->withResult(3, 40_000, $first)
            ->withResult(3, 30_000, $later);

        self::assertSame([3, 6], array_keys($progress->results), 'Sorted by table');
        self::assertSame(['bestMs' => 30_000, 'completedAt' => $later], $progress->results[3]);
        self::assertSame(['bestMs' => 50_000, 'completedAt' => $first], $progress->results[6], 'A tie keeps the earlier round');
    }

    public function testWithResultDoesNotChangeTheOriginal(): void
    {
        $empty = new GuestProgress();
        $progress = $empty->withResult(6, 50_000, new \DateTimeImmutable());

        self::assertTrue($empty->isEmpty());
        self::assertFalse($progress->isEmpty());
    }

    public function testProgressDecidesPassedFromTheBestTime(): void
    {
        $progress = (new GuestProgress())
            ->withResult(6, 60_000, new \DateTimeImmutable())
            ->withResult(7, 60_001, new \DateTimeImmutable())
            ->withResult(TafelsMix::TwoToTen->value, 120_000, new \DateTimeImmutable())
            ->progress();

        self::assertSame([TafelsMix::TwoToTen->value, 6, 7], array_keys($progress));
        self::assertTrue($progress[6]->passed);
        self::assertFalse($progress[7]->passed);
        self::assertTrue($progress[TafelsMix::TwoToTen->value]->passed);
        self::assertSame('1:01', $progress[7]->bestTime());
    }
}
