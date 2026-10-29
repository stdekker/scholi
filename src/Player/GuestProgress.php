<?php

declare(strict_types=1);

namespace App\Player;

use App\Game\TableProgress;
use App\Game\Tafels;

/**
 * A guest's best tafels round per table, kept in the browser (see GuestProgressCookie) until
 * the guest makes a player. "Passed" is never stored: it follows from the best time.
 */
final readonly class GuestProgress
{
    /**
     * @param array<int, array{bestMs: int, completedAt: \DateTimeImmutable}> $results keyed by table number
     */
    public function __construct(
        public array $results = [],
    ) {
    }

    /**
     * Returns the progress with this round added, if it beats the best time for its table.
     */
    public function withResult(int $table, int $durationMs, \DateTimeImmutable $completedAt): self
    {
        $current = $this->results[$table] ?? null;
        if (null !== $current && $current['bestMs'] <= $durationMs) {
            return $this;
        }

        $results = $this->results;
        $results[$table] = ['bestMs' => $durationMs, 'completedAt' => $completedAt];
        ksort($results);

        return new self($results);
    }

    public function isEmpty(): bool
    {
        return [] === $this->results;
    }

    /**
     * @return array<int, TableProgress> keyed by table number, sorted
     */
    public function progress(): array
    {
        $progress = [];
        foreach ($this->results as $table => $result) {
            $progress[$table] = new TableProgress($table, $result['bestMs'], Tafels::isPassed($table, $result['bestMs']));
        }

        return $progress;
    }
}
