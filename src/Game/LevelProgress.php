<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameResult;

/**
 * A pupil's best result for one level of a game (a table, a mix, an erbij-en-eraf level …).
 */
final readonly class LevelProgress
{
    public function __construct(
        public int $level,
        public int $bestMs,
        public bool $passed,
        public int $timeLimitMs,
    ) {
    }

    public function bestTime(): string
    {
        return RoundTime::format($this->bestMs);
    }

    /** Translation key that finishes the badge's "Binnen …": "een minuut", "twee minuten" … */
    public function withinKey(): string
    {
        return RoundTime::withinKey($this->timeLimitMs);
    }

    /**
     * @param iterable<GameResult> $results of one game
     *
     * @return array<int, self> keyed by level, sorted
     */
    public static function fromResults(iterable $results): array
    {
        $progress = [];

        foreach ($results as $result) {
            $level = $result->getLevel();
            $current = $progress[$level] ?? null;

            $progress[$level] = new self(
                $level,
                min($current->bestMs ?? \PHP_INT_MAX, $result->getDurationMs()),
                ($current->passed ?? false) || $result->isPassed(),
                $result->getGame()->timeLimitMs($level),
            );
        }

        ksort($progress);

        return $progress;
    }
}
