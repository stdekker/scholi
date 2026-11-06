<?php

declare(strict_types=1);

namespace App\Tests\Unit\Game;

use App\Game\Dictee;
use App\Game\DicteeGroup;
use App\Game\DicteeLevel;
use App\Game\Game;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DicteeTest extends TestCase
{
    /** A round asks ten different words. */
    private const int ROUND_SIZE = 10;

    /**
     * @return iterable<string, array{DicteeGroup}>
     */
    public static function dicteeGroups(): iterable
    {
        foreach (DicteeGroup::cases() as $group) {
            yield $group->slug() => [$group];
        }
    }

    #[DataProvider('dicteeGroups')]
    public function testEveryGroupHasEnoughValidWords(DicteeGroup $group): void
    {
        $words = Dictee::words($group);

        self::assertGreaterThanOrEqual(self::ROUND_SIZE, \count($words));
        self::assertSame($words, array_values(array_unique($words)), 'No word twice in a list');
        foreach ($words as $word) {
            self::assertMatchesRegularExpression('/^[a-z]+$/', $word, 'Lowercase letters a-z only, as the audio file name and the input need');
            self::assertLessThanOrEqual(Dictee::ANSWER_MAX_LENGTH, \strlen($word), $word.' fits in the answer field');
        }
    }

    public function testEveryWordHasARecordingAndEveryRecordingAWord(): void
    {
        $audioDir = \dirname(__DIR__, 3).'/assets/'.Dictee::AUDIO_DIR;
        $words = array_merge(...array_map(Dictee::words(...), DicteeGroup::cases()));

        foreach ($words as $word) {
            $file = \dirname(__DIR__, 3).'/assets/'.Dictee::audioPath($word);
            self::assertFileExists($file, \sprintf('Run `ddev npm run audio:dictee` to record "%s"', $word));
            self::assertGreaterThan(1000, (int) filesize($file), $word.' has a recording with sound');
        }

        $recorded = array_map(static fn (string $file): string => basename($file, '.mp3'), glob($audioDir.'/*.mp3') ?: []);
        self::assertEqualsCanonicalizing(array_unique($words), $recorded, 'No recordings of words that left the lists');
    }

    public function testLevelsAreStoredPerGroup(): void
    {
        self::assertSame(61, Dictee::storedLevel(DicteeGroup::Groep6, DicteeLevel::Copy));
        self::assertSame(83, Dictee::storedLevel(DicteeGroup::Groep8, DicteeLevel::Blanks));
        self::assertSame([DicteeGroup::Groep8, DicteeLevel::Hidden], Dictee::fromStoredLevel(84));
    }

    public function testEveryStoredLevelIsKnownAndOthersAreNot(): void
    {
        foreach (DicteeGroup::cases() as $group) {
            foreach (DicteeLevel::cases() as $level) {
                $stored = Dictee::storedLevel($group, $level);
                self::assertTrue(Dictee::isKnown($stored));
                self::assertTrue(Game::Dictee->isKnownLevel($stored));
                self::assertSame([$group, $level], Dictee::fromStoredLevel($stored));
            }
        }

        foreach ([0, 1, 4, 60, 65, 71, 80, 85, 99, -61] as $unknown) {
            self::assertFalse(Dictee::isKnown($unknown), (string) $unknown);
        }
    }

    public function testGridHasARoundForEveryLevelAndGroup(): void
    {
        $grid = Dictee::grid();

        self::assertSame(DicteeLevel::cases(), array_column($grid, 'level'));
        self::assertSame([61, 81], array_column($grid[0]['rounds'], 'stored'));
        self::assertSame([DicteeGroup::Groep6, DicteeGroup::Groep8], array_column($grid[3]['rounds'], 'group'));
    }

    public function testEveryRoundGetsThreeMinutes(): void
    {
        self::assertSame(180_000, Dictee::timeLimitMs(61));
        self::assertSame(180_000, Game::Dictee->timeLimitMs(84));
        self::assertTrue(Game::Dictee->isPassed(61, 180_000));
        self::assertFalse(Game::Dictee->isPassed(61, 180_001));
    }

    /**
     * @return iterable<string, array{DicteeLevel, string}>
     */
    public static function modes(): iterable
    {
        yield 'copy' => [DicteeLevel::Copy, 'full'];
        yield 'partial' => [DicteeLevel::Partial, 'partial'];
        yield 'blanks' => [DicteeLevel::Blanks, 'blanks'];
        yield 'hidden' => [DicteeLevel::Hidden, 'hidden'];
    }

    #[DataProvider('modes')]
    public function testLevelsTellTheGameHowMuchToShow(DicteeLevel $level, string $mode): void
    {
        self::assertSame($mode, $level->mode());
    }
}
