<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Rules of the erbij-en-eraf game that the server needs to know: how a round is stored and its
 * time limit. The sums themselves are made in assets/games/rekenen/erbij_eraf.js.
 *
 * A round is stored under level × 10 + operation code, e.g. 32 = tot 20 met brug, eraf.
 */
final class ErbijEraf
{
    /**
     * Every round a pupil can pick, per level, with the number it is stored under.
     *
     * @return list<array{level: ErbijErafLevel, rounds: list<array{operation: ErbijErafOperation, stored: int}>}>
     */
    public static function grid(): array
    {
        return array_map(
            static fn (ErbijErafLevel $level): array => [
                'level' => $level,
                'rounds' => array_map(
                    static fn (ErbijErafOperation $operation): array => ['operation' => $operation, 'stored' => self::storedLevel($level, $operation)],
                    ErbijErafOperation::cases(),
                ),
            ],
            ErbijErafLevel::cases(),
        );
    }

    public static function storedLevel(ErbijErafLevel $level, ErbijErafOperation $operation): int
    {
        return $level->value * 10 + $operation->code();
    }

    /**
     * @return array{ErbijErafLevel, ErbijErafOperation}|null
     */
    public static function fromStoredLevel(int $stored): ?array
    {
        $level = ErbijErafLevel::tryFrom(intdiv($stored, 10));
        $operation = ErbijErafOperation::fromCode($stored % 10);

        return null === $level || null === $operation ? null : [$level, $operation];
    }

    public static function isKnown(int $stored): bool
    {
        return null !== self::fromStoredLevel($stored);
    }

    public static function timeLimitMs(int $stored): int
    {
        $round = self::fromStoredLevel($stored);

        return null === $round ? RoundTime::ONE_MINUTE_MS : $round[0]->timeLimitMs();
    }
}
