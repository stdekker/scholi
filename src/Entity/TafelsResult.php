<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\Tafels;
use App\Repository\TafelsResultRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One completed tafels round. Whether it counts as passed is decided here, on the server.
 */
#[ORM\Entity(repositoryClass: TafelsResultRepository::class)]
#[ORM\Index(name: 'tafels_result_player_table', columns: ['player_id', 'table_number'])]
class TafelsResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Player $player;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $tableNumber;

    #[ORM\Column]
    private int $durationMs;

    #[ORM\Column]
    private bool $passed;

    #[ORM\Column]
    private \DateTimeImmutable $completedAt;

    public function __construct(Player $player, int $tableNumber, int $durationMs, \DateTimeImmutable $completedAt)
    {
        $this->player = $player;
        $this->tableNumber = $tableNumber;
        $this->durationMs = $durationMs;
        $this->passed = Tafels::isPassed($durationMs);
        $this->completedAt = $completedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }

    public function getTableNumber(): int
    {
        return $this->tableNumber;
    }

    public function getDurationMs(): int
    {
        return $this->durationMs;
    }

    public function isPassed(): bool
    {
        return $this->passed;
    }

    public function getCompletedAt(): \DateTimeImmutable
    {
        return $this->completedAt;
    }
}
