<?php

declare(strict_types=1);

namespace App\Tests\Controller\Game;

use App\Player\GuestProgress;
use App\Tests\Support\PlayerTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RekenenControllerTest extends WebTestCase
{
    use PlayerTestTrait;

    public function testPickerLinksToTablesOneToTwelve(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/rekenen');

        $links = $crawler->filter('.table-picker__link')->each(
            static fn ($link): string => trim($link->text()),
        );

        self::assertSame(
            [...array_map(static fn (int $table): string => 'Tafel van '.$table, range(1, 12)), 'Alle tafels'],
            $links,
        );
    }

    public function testTablePageConfiguresTheGame(): void
    {
        $client = static::createClient();
        $client->request('GET', '/rekenen/tafel/6');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'De tafel van 6');
        self::assertSelectorExists('[data-controller="tafels"][data-tafels-tables-value="[6]"]');
        self::assertSelectorExists('meta[name="turbo-cache-control"][content="no-cache"]');
        self::assertSelectorTextSame('[role="timer"]', '1:00');
        self::assertSelectorTextSame('dialog.ready-dialog h2', 'Ben je er klaar voor?');
        self::assertSelectorTextContains('dialog.ready-dialog button', 'Ik ben er klaar voor!');
        self::assertSelectorTextContains('dialog.ready-dialog a.ready-dialog__back[href="/rekenen"]', 'Terug');
        self::assertSelectorTextContains('[data-tafels-target="lateMessage"]', 'Iets sneller de volgende keer!');
        self::assertSelectorExists('[data-tafels-target="frozenMessage"][hidden]');
        self::assertSelectorExists('[data-tafels-frozen-value="Oei, deze bubbel is bevroren. Ga verder met een andere!"]');
        self::assertSelectorExists('[data-tafels-done-frozen-value*="%solved% van de %total%"]');
    }

    public function testAlleTafelsPicksAMix(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/rekenen/alle-tafels');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Alle tafels');
        self::assertSelectorExists('meta[name="turbo-cache-control"][content="no-cache"]');
        self::assertSame(
            ['/rekenen/alle-tafels/2-tot-5', '/rekenen/alle-tafels/2-tot-10', '/rekenen/alle-tafels/2-tot-12'],
            $crawler->filter('a.table-picker__link')->each(static fn ($link): string => (string) $link->attr('href')),
        );
        self::assertSelectorTextContains('.table-picker li:nth-child(1)', 'Tafels 2 tot 5');
        // Without finished tables "geoefende tafels" is not a link yet.
        self::assertSelectorTextContains('.table-picker__link--locked', 'Maak eerst twee tafels af.');
    }

    public function testMixPageConfiguresTheGame(): void
    {
        $client = static::createClient();
        $client->request('GET', '/rekenen/alle-tafels/2-tot-10');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Tafels 2 tot 10');
        self::assertSelectorTextContains('.bubble-game__header', 'binnen twee minuten');
        self::assertSelectorExists('[data-controller="tafels"][data-tafels-tables-value="[2,3,4,5,6,7,8,9,10]"][data-tafels-time-limit-value="120000"]');
        self::assertSelectorTextSame('[role="timer"]', '2:00');
        self::assertSelectorTextSame('[data-tafels-target="readyTime"]', '2:00');
        self::assertSelectorTextContains('[data-tafels-target="passedMessage"]', 'binnen twee minuten');
        self::assertSelectorExists('a.ready-dialog__back[href="/rekenen/alle-tafels"]');
        self::assertSelectorTextContains('.round-complete a[href="/rekenen/alle-tafels"]', 'Andere tafels');
    }

    public function testUnknownMixIsNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/rekenen/alle-tafels/2-tot-11');

        self::assertResponseStatusCodeSame(404);
    }

    public function testPractisedTablesNeedTwoFinishedTables(): void
    {
        $client = static::createClient();
        $this->setGuestProgress($client, (new GuestProgress())->withResult(7, 75_000, new \DateTimeImmutable()));

        $client->request('GET', '/rekenen/alle-tafels/geoefend');

        self::assertResponseRedirects('/rekenen/alle-tafels');
    }

    public function testPractisedTablesForAGuest(): void
    {
        $client = static::createClient();
        $this->setGuestProgress($client, (new GuestProgress())
            ->withResult(7, 75_000, new \DateTimeImmutable())
            ->withResult(3, 45_000, new \DateTimeImmutable())
            ->withResult(0, 110_000, new \DateTimeImmutable()));

        $client->request('GET', '/rekenen/alle-tafels');
        self::assertSelectorTextContains('a.table-picker__link[href="/rekenen/alle-tafels/geoefend"]', '3, 7');

        $client->request('GET', '/rekenen/alle-tafels/geoefend');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Geoefende tafels');
        // Mixes don't count as practised tables; the round is never saved.
        self::assertSelectorExists('[data-tafels-tables-value="[3,7]"][data-tafels-time-limit-value="120000"][data-tafels-save-url-value=""]');
    }

    public function testPractisedTablesForAPlayer(): void
    {
        $client = static::createClient();
        [$player, $token] = $this->createPlayer();
        $this->addResult($player, 12, 50_000);
        $this->addResult($player, 1, 70_000);
        $this->logIn($client, $token);

        $client->request('GET', '/rekenen/alle-tafels/geoefend');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-tafels-tables-value="[1,12]"]');
    }

    #[DataProvider('invalidTables')]
    public function testInvalidTableIsNotFound(string $table): void
    {
        $client = static::createClient();
        $client->request('GET', '/rekenen/tafel/'.$table);

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function invalidTables(): iterable
    {
        yield ['0'];
        yield ['13'];
        yield ['100'];
        yield ['02'];
        yield ['abc'];
    }
}
