<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Player;
use App\Entity\PracticeDay;
use App\Player\PracticeTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PracticeDay>
 */
final class PracticeDayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PracticeDay::class);
    }

    /**
     * Adds a finished round's seconds to a day (today unless given) and deletes the player's days
     * before the window, so no more than the window is ever kept. Flushes.
     */
    public function addSeconds(Player $player, int $seconds, ?\DateTimeImmutable $day = null): void
    {
        $day ??= PracticeTime::today();
        $entityManager = $this->getEntityManager();

        $practiceDay = $this->findOneBy(['player' => $player, 'day' => $day]);
        if (null === $practiceDay) {
            $entityManager->persist(new PracticeDay($player, $day, min($seconds, PracticeTime::MAX_DAY_SECONDS)));
        } else {
            $practiceDay->addSeconds($seconds);
        }
        $entityManager->flush();

        $this->createQueryBuilder('p')
            ->delete()
            ->where('p.player = :player AND p.day < :start')
            ->setParameter('player', $player)
            ->setParameter('start', PracticeTime::windowStart(), 'date_immutable')
            ->getQuery()
            ->execute();
    }

    /**
     * The seconds practised in the window (today and the days before).
     */
    public function secondsInWindow(Player $player): int
    {
        $seconds = $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.seconds), 0)')
            ->where('p.player = :player AND p.day >= :start')
            ->setParameter('player', $player)
            ->setParameter('start', PracticeTime::windowStart(), 'date_immutable')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $seconds;
    }
}
