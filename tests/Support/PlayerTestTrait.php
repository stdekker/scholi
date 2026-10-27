<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Player;
use App\Entity\TafelsResult;
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

    private function addResult(Player $player, int $table, int $durationMs): void
    {
        $entityManager = $this->entityManager();
        $entityManager->persist(new TafelsResult($player, $table, $durationMs, new \DateTimeImmutable()));
        $entityManager->flush();
    }

    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function responsePlayerCookie(KernelBrowser $client): ?ResponseCookie
    {
        foreach ($client->getResponse()->headers->getCookies() as $cookie) {
            if (PlayerCookie::NAME === $cookie->getName()) {
                return $cookie;
            }
        }

        return null;
    }
}
