<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\Game;
use App\Repository\GameResultRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One completed round of a game. Whether it counts as passed is decided here, on the server.
 */
#[ORM\Entity(repositoryClass: GameResultRepository::class)]
#[ORM\Index(name: 'game_result_player_game_level', columns: ['player_id', 'game', 'level'])]
class GameResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Player $player;

    #[ORM\Column(length: 16, enumType: Game::class)]
    private Game $game;

    /** Meaning depends on the game: see Game. */
    #[ORM\Column(type: Types::SMALLINT)]
    private int $level;

    #[ORM\Column]
    private int $durationMs;

    #[ORM\Column]
    private bool $passed;

    #[ORM\Column]
    private \DateTimeImmutable $completedAt;

    public function __construct(Player $player, Game $game, int $level, int $durationMs, \DateTimeImmutable $completedAt)
    {
        $this->player = $player;
        $this->game = $game;
        $this->level = $level;
        $this->durationMs = $durationMs;
        $this->passed = $game->isPassed($level, $durationMs);
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

    public function getGame(): Game
    {
        return $this->game;
    }

    public function getLevel(): int
    {
        return $this->level;
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
