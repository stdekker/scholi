<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\GameResult;
use App\Entity\Player;
use App\Game\Game;
use App\Player\GuestProgress;
use App\Security\GuestProgressCookie;
use App\Security\PlayerCookie;
use App\Security\PlayerToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Cookie as ResponseCookie;

/**
 * Helpers for functional tests with link-based players. Requires a booted client.
 */
trait PlayerTestTrait
{
    /**
     * @return array{Player, string} the player and its raw link token
     */
    private function createPlayer(string $name = 'Slimme Vos'): array
    {
        $token = PlayerToken::generate();
        $player = new Player($name, PlayerToken::hash($token), new \DateTimeImmutable());

        $entityManager = $this->entityManager();
        $entityManager->persist($player);
        $entityManager->flush();

        return [$player, $token];
    }

    private function logIn(KernelBrowser $client, string $token): void
    {
        // Same domain/path as the server's cookie, so a cleared cookie replaces this one.
        $client->getCookieJar()->set(new Cookie(PlayerCookie::NAME, $token, null, '/', 'localhost'));
    }

    private function setGuestProgress(KernelBrowser $client, GuestProgress $progress): void
    {
        $this->setGuestCookie($client, $this->guestCookie()->encode($progress));
    }

    private function setGuestCookie(KernelBrowser $client, string $value): void
    {
        $client->getCookieJar()->set(new Cookie(GuestProgressCookie::NAME, $value, null, '/', 'localhost'));
    }

    private function guestCookie(): GuestProgressCookie
    {
        return static::getContainer()->get(GuestProgressCookie::class);
    }

    private function addResult(Player $player, int $level, int $durationMs, Game $game = Game::Tafels): void
    {
        $entityManager = $this->entityManager();
        $entityManager->persist(new GameResult($player, $game, $level, $durationMs, new \DateTimeImmutable()));
        $entityManager->flush();
    }

    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function responsePlayerCookie(KernelBrowser $client): ?ResponseCookie
    {
        return $this->responseCookie($client, PlayerCookie::NAME);
    }

    private function responseGuestCookie(KernelBrowser $client): ?ResponseCookie
    {
        return $this->responseCookie($client, GuestProgressCookie::NAME);
    }

    private function responseCookie(KernelBrowser $client, string $name): ?ResponseCookie
    {
        foreach ($client->getResponse()->headers->getCookies() as $cookie) {
            if ($name === $cookie->getName()) {
                return $cookie;
            }
        }

        return null;
    }
}
