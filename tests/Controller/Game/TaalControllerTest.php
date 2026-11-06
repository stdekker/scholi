<?php

declare(strict_types=1);

namespace App\Tests\Controller\Game;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TaalControllerTest extends WebTestCase
{
    public function testListsTheGames(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/taal');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Taal');
        self::assertSame(
            ['/taal/dictee'],
            $crawler->filter('a.game-card')->each(static fn ($link): string => (string) $link->attr('href')),
        );
        self::assertSelectorTextContains('a.game-card[href="/taal/dictee"]', 'Dictee');
    }

    public function testHomeLinksToTheSubjects(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertSame(
            ['/rekenen', '/taal'],
            $crawler->filter('a.game-card')->each(static fn ($link): string => (string) $link->attr('href')),
        );
        self::assertSelectorTextContains('a.game-card[href="/taal"]', 'Taal');
    }
}
