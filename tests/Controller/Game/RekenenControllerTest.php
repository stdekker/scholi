<?php

declare(strict_types=1);

namespace App\Tests\Controller\Game;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RekenenControllerTest extends WebTestCase
{
    public function testPickerLinksToTablesTwoToTen(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/rekenen');

        $links = $crawler->filter('.table-picker__link')->each(
            static fn ($link): string => trim($link->text()),
        );

        self::assertSame(
            ['Tafel van 2', 'Tafel van 3', 'Tafel van 4', 'Tafel van 5', 'Tafel van 6', 'Tafel van 7', 'Tafel van 8', 'Tafel van 9', 'Tafel van 10', 'Alle tafels'],
            $links,
        );
    }

    public function testTablePageConfiguresTheGame(): void
    {
        $client = static::createClient();
        $client->request('GET', '/rekenen/tafel/6');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'De tafel van 6');
        self::assertSelectorExists('[data-controller="tafels"][data-tafels-table-value="6"]');
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

    public function testAlleTafelsPageConfiguresTheGame(): void
    {
        $client = static::createClient();
        $client->request('GET', '/rekenen/alle-tafels');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Alle tafels');
        self::assertSelectorTextContains('.bubble-game__header', 'binnen twee minuten');
        self::assertSelectorExists('[data-controller="tafels"][data-tafels-table-value="0"][data-tafels-time-limit-value="120000"]');
        self::assertSelectorTextSame('[role="timer"]', '2:00');
        self::assertSelectorTextSame('[data-tafels-target="readyTime"]', '2:00');
        self::assertSelectorTextContains('[data-tafels-target="passedMessage"]', 'binnen twee minuten');
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
        yield ['1'];
        yield ['11'];
        yield ['100'];
        yield ['02'];
        yield ['abc'];
    }
}
