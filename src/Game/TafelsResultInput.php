<?php

declare(strict_types=1);

namespace App\Game;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * JSON payload the tafels game posts when a round is complete.
 * The client only reports the duration; "passed" is decided by the server.
 */
final readonly class TafelsResultInput
{
    /** One hour: anything longer is not a real round. */
    public const int MAX_DURATION_MS = 3_600_000;

    public function __construct(
        #[Assert\Range(min: 1, max: self::MAX_DURATION_MS)]
        public int $durationMs,
    ) {
    }
}
