<?php

declare(strict_types=1);

namespace App\Game;

use App\Player\PracticeTime;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * JSON payload a game posts when a round ends: the whole seconds between the clock's start and
 * the end of the round. Every finished round counts, also one with frozen bubbles.
 */
final readonly class PracticeTimeInput
{
    public function __construct(
        #[Assert\Range(min: 1, max: PracticeTime::MAX_ROUND_SECONDS)]
        public int $seconds,
    ) {
    }
}
