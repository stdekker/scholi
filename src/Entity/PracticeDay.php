<?php

declare(strict_types=1);

namespace App\Entity;

use App\Player\PracticeTime;
use App\Repository\PracticeDayRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The seconds a player practised on one day, summed over finished rounds. Only the days of the
 * rolling window are kept (see PracticeTime, PracticeDayRepository::addSeconds()).
 */
#[ORM\Entity(repositoryClass: PracticeDayRepository::class)]
#[ORM\UniqueConstraint(name: 'practice_day_player_day', columns: ['player_id', 'day'])]
class PracticeDay
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Player $player;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $day;

    #[ORM\Column]
    private int $seconds;

    public function __construct(Player $player, \DateTimeImmutable $day, int $seconds)
    {
        $this->player = $player;
        $this->day = $day;
        $this->seconds = $seconds;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }

    public function getDay(): \DateTimeImmutable
    {
        return $this->day;
    }

    public function getSeconds(): int
    {
        return $this->seconds;
    }

    public function addSeconds(int $seconds): void
    {
        $this->seconds = min(PracticeTime::MAX_DAY_SECONDS, $this->seconds + $seconds);
    }
}
