<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SmokeTest extends WebTestCase
{
    #[DataProvider('publicPages')]
    public function testPublicPageLoads(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('html[lang="nl"]');
        self::assertSelectorTextContains('.site-footer button.about-button[popovertarget="about-scholi"]', 'Meer over Scholi');
        self::assertSelectorTextContains('#about-scholi[popover]', 'privacyvriendelijke leeromgeving voor kinderen');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function publicPages(): iterable
    {
        yield 'home' => ['/'];
        yield 'rekenen' => ['/rekenen'];
        yield 'tafel van 1' => ['/rekenen/tafel/1'];
        yield 'tafel van 12' => ['/rekenen/tafel/12'];
        yield 'alle tafels' => ['/rekenen/alle-tafels'];
        yield 'tafels 2 tot 5' => ['/rekenen/alle-tafels/2-tot-5'];
        yield 'tafels 2 tot 12' => ['/rekenen/alle-tafels/2-tot-12'];
    }
}
