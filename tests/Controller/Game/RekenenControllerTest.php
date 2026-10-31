<?php

declare(strict_types=1);

namespace App\Tests\Controller\Game;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RekenenControllerTest extends WebTestCase
{
    public function testListsTheGames(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/rekenen');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Rekenen');
        self::assertSame(
            ['/rekenen/tafels', '/rekenen/erbij-eraf'],
            $crawler->filter('a.game-card')->each(static fn ($link): string => (string) $link->attr('href')),
        );
        self::assertSelectorTextContains('a.game-card[href="/rekenen/erbij-eraf"]', 'Erbij en eraf');
    }
}
