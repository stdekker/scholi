<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\TafelsResult;

/**
 * A player's best result for one table.
 */
final readonly class TableProgress
{
    public function __construct(
        public int $table,
        public int $bestMs,
        public bool $passed,
    ) {
    }

    public function bestTime(): string
    {
        return Tafels::formatTime($this->bestMs);
    }

    public function isAllTables(): bool
    {
        return Tafels::ALL === $this->table;
    }

    /**
     * @param iterable<TafelsResult> $results
     *
     * @return array<int, self> keyed by table number, sorted
     */
    public static function fromResults(iterable $results): array
    {
        $progress = [];

        foreach ($results as $result) {
            $table = $result->getTableNumber();
            $current = $progress[$table] ?? null;

            $progress[$table] = new self(
                $table,
                min($current->bestMs ?? \PHP_INT_MAX, $result->getDurationMs()),
                ($current->passed ?? false) || $result->isPassed(),
            );
        }

        ksort($progress);

        return $progress;
    }
}
