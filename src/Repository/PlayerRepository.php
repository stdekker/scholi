<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Player;
use App\Security\PlayerToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Player>
 */
final class PlayerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Player::class);
    }

    public function findOneByToken(string $token): ?Player
    {
        if (!PlayerToken::isWellFormed($token)) {
            return null;
        }

        return $this->findOneBy(['tokenHash' => PlayerToken::hash($token)]);
    }
}
