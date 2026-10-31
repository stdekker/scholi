<?php

declare(strict_types=1);

namespace App\Player;

use App\Game\Game;
use App\Game\LevelProgress;

/**
 * A guest's best round per game and level, and the seconds practised per day (see PracticeTime),
 * kept in the browser (see GuestProgressCookie) until the guest makes a player. "Passed" is never
 * stored: it follows from the best time.
 */
final readonly class GuestProgress
{
    /**
     * @param array<string, array<int, array{bestMs: int, completedAt: \DateTimeImmutable}>> $results  keyed by Game value, then level
     * @param array<string, int>                                                             $practice seconds keyed by day (Y-m-d)
     */
    public function __construct(
        public array $results = [],
        public array $practice = [],
    ) {
    }

    /**
     * Returns the progress with this round added, if it beats the best time for its level.
     */
    public function withResult(Game $game, int $level, int $durationMs, \DateTimeImmutable $completedAt): self
    {
        $current = $this->results[$game->value][$level] ?? null;
        if (null !== $current && $current['bestMs'] <= $durationMs) {
            return $this;
        }

        $results = $this->results;
        $results[$game->value][$level] = ['bestMs' => $durationMs, 'completedAt' => $completedAt];
        ksort($results[$game->value]);
        ksort($results);

        return new self($results, $this->practice);
    }

    /**
     * Returns the progress with a finished round's seconds added to a day; days before the
     * window are dropped.
     *
     * @param string $day         Y-m-d
     * @param string $windowStart Y-m-d, the first day that still counts
     */
    public function withPractice(string $day, int $seconds, string $windowStart): self
    {
        $practice = array_filter($this->practice, static fn (string $kept): bool => $kept >= $windowStart, \ARRAY_FILTER_USE_KEY);
        if ($day >= $windowStart) {
            $practice[$day] = min(PracticeTime::MAX_DAY_SECONDS, ($practice[$day] ?? 0) + $seconds);
        }
        ksort($practice);

        return new self($this->results, $practice);
    }

    /**
     * @param string $windowStart Y-m-d, the first day that still counts
     */
    public function practiceSeconds(string $windowStart): int
    {
        return array_sum(array_filter($this->practice, static fn (string $day): bool => $day >= $windowStart, \ARRAY_FILTER_USE_KEY));
    }

    public function isEmpty(): bool
    {
        return [] === $this->results && [] === $this->practice;
    }

    /**
     * @return array<int, LevelProgress> keyed by level, sorted
     */
    public function progress(Game $game): array
    {
        $progress = [];
        foreach ($this->results[$game->value] ?? [] as $level => $result) {
            $progress[$level] = new LevelProgress($level, $result['bestMs'], $game->isPassed($level, $result['bestMs']), $game->timeLimitMs($level));
        }

        return $progress;
    }

    /**
     * Every result, e.g. to move them to a new player.
     *
     * @return iterable<array{Game, int, int, \DateTimeImmutable}> game, level, best time (ms), completed at
     */
    public function entries(): iterable
    {
        foreach ($this->results as $game => $levels) {
            $knownGame = Game::tryFrom($game);
            if (null === $knownGame) {
                continue;
            }

            foreach ($levels as $level => $result) {
                yield [$knownGame, $level, $result['bestMs'], $result['completedAt']];
            }
        }
    }
}
