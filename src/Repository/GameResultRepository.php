<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GameResult;
use App\Entity\Player;
use App\Game\Game;
use App\Game\LevelProgress;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameResult>
 */
final class GameResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameResult::class);
    }

    /**
     * @return array<int, LevelProgress> keyed by level
     */
    public function progressFor(Player $player, Game $game): array
    {
        return LevelProgress::fromResults($this->findBy(['player' => $player, 'game' => $game]));
    }
}
