<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Player;
use App\Entity\TafelsResult;
use App\Game\TableProgress;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TafelsResult>
 */
final class TafelsResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TafelsResult::class);
    }

    /**
     * @return array<int, TableProgress> keyed by table number
     */
    public function progressFor(Player $player): array
    {
        return TableProgress::fromResults($this->findBy(['player' => $player]));
    }
}
