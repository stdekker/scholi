<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The elephant mascot sits in the bottom right corner of the task selection screens, and nowhere
 * else on the pages (the popup has its own small one).
 */
final class HomeControllerTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function selectionScreens(): iterable
    {
        yield 'home' => ['/'];
        yield 'rekenen' => ['/rekenen'];
        yield 'taal' => ['/taal'];
        yield 'tafels' => ['/rekenen/tafels'];
        yield 'alle tafels' => ['/rekenen/alle-tafels'];
        yield 'erbij en eraf' => ['/rekenen/erbij-eraf'];
        yield 'dictee' => ['/taal/dictee'];
        yield 'dictee groep 8' => ['/taal/dictee?groep=8'];
    }

    #[DataProvider('selectionScreens')]
    public function testElephantSitsInTheBottomRightCornerOfSelectionScreens(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        // In the footer, next to the "Meer over Scholi" button; decorative, so no alt text.
        self::assertSelectorExists('.site-footer img.site-footer__mascot[src^="/assets/images/scholi-mascot-large-"][alt=""]');
        self::assertSelectorCount(1, '.site-footer__mascot');
        self::assertSelectorNotExists('.site-header img');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pagesWithoutTheElephant(): iterable
    {
        yield 'tafel game' => ['/rekenen/tafel/6'];
        yield 'alle tafels game' => ['/rekenen/alle-tafels/2-tot-10'];
        yield 'erbij en eraf game' => ['/rekenen/erbij-eraf/tot-10/erbij'];
        yield 'dictee game' => ['/taal/dictee/groep-6/overschrijven'];
        yield 'new player' => ['/speler/nieuw'];
    }

    #[DataProvider('pagesWithoutTheElephant')]
    public function testGamesAndOtherPagesHaveNoElephantInTheCorner(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.site-footer__mascot');
        self::assertSelectorNotExists('.site-header img');
    }
}
