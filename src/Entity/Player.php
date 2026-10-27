<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PlayerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A pupil's account. It holds only a self-chosen (fun) name and animal emoji; the player link token is the
 * only credential and is stored as a SHA-256 hash (see App\Security\PlayerToken).
 */
#[ORM\Entity(repositoryClass: PlayerRepository::class)]
class Player implements UserInterface
{
    public const int NAME_MAX_LENGTH = 30;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: self::NAME_MAX_LENGTH)]
    private string $name;

    /** One of FunNameGenerator::emojis(); null for players created before emoji existed. */
    #[ORM\Column(length: 16, nullable: true)]
    private ?string $emoji;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $name, string $tokenHash, \DateTimeImmutable $createdAt, ?string $emoji = null)
    {
        $this->name = $name;
        $this->emoji = $emoji;
        $this->tokenHash = $tokenHash;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmoji(): ?string
    {
        return $this->emoji;
    }

    /** Name followed by the emoji, e.g. "Dappere Dolfijn 🐬". */
    public function getDisplayName(): string
    {
        return null === $this->emoji ? $this->name : $this->name.' '.$this->emoji;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getRoles(): array
    {
        return ['ROLE_PLAYER'];
    }

    public function getUserIdentifier(): string
    {
        \assert('' !== $this->tokenHash);

        return $this->tokenHash;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }
}
