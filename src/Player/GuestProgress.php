<?php

declare(strict_types=1);

namespace App\Player;

use App\Game\Game;
use App\Game\LevelProgress;

/**
 * A guest's best round per game and level, kept in the browser (see GuestProgressCookie) until
 * the guest makes a player. "Passed" is never stored: it follows from the best time.
 */
final readonly class GuestProgress
{
    /**
     * @param array<string, array<int, array{bestMs: int, completedAt: \DateTimeImmutable}>> $results keyed by Game value, then level
     */
    public function __construct(
        public array $results = [],
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

        return new self($results);
    }

    public function isEmpty(): bool
    {
        return [] === $this->results;
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
