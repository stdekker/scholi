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

    /**
     * The player's badges over all games: every level with a finished round (⭐ or ✓).
     */
    public function badgeCount(Player $player): int
    {
        $levels = $this->createQueryBuilder('r')
            ->select('r.game, r.level')
            ->where('r.player = :player')
            ->groupBy('r.game, r.level')
            ->setParameter('player', $player)
            ->getQuery()
            ->getScalarResult();

        return \count($levels);
    }
}
