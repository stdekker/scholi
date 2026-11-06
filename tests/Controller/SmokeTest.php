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
        // The elephant mascot, served by this site, is in the popup (decorative) and not in the header.
        self::assertSelectorExists('#about-scholi img.about-popover__logo[src^="/assets/images/scholi-mascot-"][alt=""]');
        self::assertSelectorNotExists('.site-header img');
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
        yield 'tafels' => ['/rekenen/tafels'];
        yield 'erbij en eraf' => ['/rekenen/erbij-eraf'];
        yield 'tot 10 erbij' => ['/rekenen/erbij-eraf/tot-10/erbij'];
        yield 'tot 100 met brug door elkaar' => ['/rekenen/erbij-eraf/tot-100-brug/door-elkaar'];
        yield 'taal' => ['/taal'];
        yield 'dictee' => ['/taal/dictee'];
        yield 'dictee groep 8' => ['/taal/dictee?groep=8'];
        yield 'dictee groep 6 overschrijven' => ['/taal/dictee/groep-6/overschrijven'];
        yield 'dictee groep 8 zonder hulp' => ['/taal/dictee/groep-8/zonder-hulp'];
    }
}
