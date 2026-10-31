<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Player;
use App\Player\PracticeTime;
use App\Repository\GameResultRepository;
use App\Repository\PracticeDayRepository;
use Twig\Attribute\AsTwigFunction;

/**
 * player_stats(): the badges and practice minutes the header shows next to a player's name.
 */
final readonly class PlayerStatsExtension
{
    public function __construct(
        private GameResultRepository $results,
        private PracticeDayRepository $practiceDays,
    ) {
    }

    /**
     * @return array{badges: int, minutes: int} badges over all games, whole minutes practised in the last seven days
     */
    #[AsTwigFunction('player_stats')]
    public function playerStats(Player $player): array
    {
        return [
            'badges' => $this->results->badgeCount($player),
            'minutes' => PracticeTime::minutes($this->practiceDays->secondsInWindow($player)),
        ];
    }
}
