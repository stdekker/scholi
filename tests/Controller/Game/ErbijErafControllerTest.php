<?php

declare(strict_types=1);

namespace App\Tests\Controller\Game;

use App\Game\Game;
use App\Player\GuestProgress;
use App\Tests\Support\PlayerTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ErbijErafControllerTest extends WebTestCase
{
    use PlayerTestTrait;

    public function testPickerShowsTheLevelsForErbijByDefault(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/rekenen/erbij-eraf');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('meta[name="turbo-cache-control"][content="no-cache"]');
        self::assertSame(
            ['/rekenen/erbij-eraf?soort=erbij', '/rekenen/erbij-eraf?soort=eraf', '/rekenen/erbij-eraf?soort=door-elkaar'],
            $crawler->filter('.operation-switch a')->each(static fn ($link): string => (string) $link->attr('href')),
        );
        self::assertSelectorTextContains('.operation-switch a[aria-current="page"]', 'Erbij');
        self::assertSelectorCount(1, '.operation-switch a[aria-current]');

        $links = $crawler->filter('.table-picker--levels a.table-picker__link')->each(static fn ($link): string => (string) $link->attr('href'));
        self::assertSame([
            '/rekenen/erbij-eraf/tot-10/erbij',
            '/rekenen/erbij-eraf/tot-20/erbij',
            '/rekenen/erbij-eraf/tot-20-brug/erbij',
            '/rekenen/erbij-eraf/tientallen/erbij',
            '/rekenen/erbij-eraf/tot-100/erbij',
            '/rekenen/erbij-eraf/tot-100-brug/erbij',
        ], $links);
        self::assertSelectorTextContains('.table-picker--levels li:nth-child(3)', 'Tot 20 met brug');
        self::assertSelectorTextContains('.table-picker--levels li:nth-child(3) .table-picker__note', '8 + 5');
    }

    /**
     * @return iterable<string, array{string, string|null, string}>
     */
    public static function nextLevels(): iterable
    {
        yield 'next level' => ['tot-10/erbij', '/rekenen/erbij-eraf/tot-20/erbij', 'Volgende: Tot 20 zonder brug: Erbij'];
        yield 'erbij to eraf' => ['tot-100-brug/erbij', '/rekenen/erbij-eraf/tot-10/eraf', 'Volgende: Tot 10: Eraf'];
        yield 'eraf to door elkaar' => ['tot-100-brug/eraf', '/rekenen/erbij-eraf/tot-10/door-elkaar', 'Volgende: Tot 10: Door elkaar'];
        yield 'last' => ['tot-100-brug/door-elkaar', null, ''];
    }

    #[DataProvider('nextLevels')]
    public function testRecommendsTheNextLevel(string $game, ?string $nextUrl, string $label): void
    {
        $client = static::createClient();
        $client->request('GET', '/rekenen/erbij-eraf/'.$game);

        if (null === $nextUrl) {
            self::assertSelectorNotExists('[data-erbij-eraf-target="next"]');

            return;
        }
        self::assertSelectorTextContains('.round-complete a[data-erbij-eraf-target="next"][hidden][href="'.$nextUrl.'"]', $label);
    }

    public function testPickerSwitchesToAnotherSoort(): void
    {
        $client = static::createClient();
        $client->request('GET', '/rekenen/erbij-eraf?soort=eraf');

        self::assertSelectorTextContains('.operation-switch a[aria-current="page"]', 'Eraf');
        self::assertSelectorExists('.table-picker--levels a[href="/rekenen/erbij-eraf/tot-20-brug/eraf"]');
        self::assertSelectorTextContains('.table-picker--levels li:nth-child(3) .table-picker__note', '13 − 6');

        $client->request('GET', '/rekenen/erbij-eraf?soort=keer');
        self::assertSelectorTextContains('.operation-switch a[aria-current="page"]', 'Erbij', 'An unknown soort falls back to erbij');
    }

    public function testPickerShowsBadgesOfTheChosenSoort(): void
    {
        $client = static::createClient();
        $this->setGuestProgress($client, (new GuestProgress())
            ->withResult(Game::ErbijEraf, 32, 45_000, new \DateTimeImmutable())
            ->withResult(Game::Tafels, 6, 45_000, new \DateTimeImmutable()));

        $client->request('GET', '/rekenen/erbij-eraf');
        self::assertSelectorCount(0, '.progress-badge');

        $client->request('GET', '/rekenen/erbij-eraf?soort=eraf');
        self::assertSelectorCount(1, '.progress-badge');
        self::assertSelectorTextContains('.table-picker--levels li:nth-child(3) .progress-badge--passed', '0:45');
    }

    public function testGamePageConfiguresTheGame(): void
    {
        $client = static::createClient();
        $client->request('GET', '/rekenen/erbij-eraf/tot-20-brug/eraf');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Tot 20 met brug: Eraf');
        self::assertSelectorTextContains('.bubble-game__header', 'binnen een minuut');
        self::assertSelectorExists('[data-controller="erbij-eraf"][data-erbij-eraf-level-value="3"][data-erbij-eraf-operation-value="eraf"][data-erbij-eraf-time-limit-value="60000"]');
        self::assertSelectorExists('[data-erbij-eraf-save-url-value="/rekenen/erbij-eraf/tot-20-brug/eraf/resultaat"]');
        self::assertSelectorExists('[data-erbij-eraf-sum-labels-value*="%left% min %right%"]');
        self::assertSelectorTextSame('[role="timer"]', '1:00');
        self::assertSelectorExists('a.ready-dialog__back[href="/rekenen/erbij-eraf?soort=eraf"]');
        self::assertSelectorTextContains('.round-complete a[href="/rekenen/erbij-eraf?soort=eraf"]', 'Ander niveau');
        self::assertSelectorExists('meta[name="turbo-cache-control"][content="no-cache"]');
        // The sums game keeps its digit keypad and three-digit answer field.
        self::assertSelectorCount(12, '.keypad__key');
        self::assertSelectorNotExists('.keypad--check-only');
        self::assertSelectorExists('input.bubble__answer[inputmode="none"][maxlength="3"]');
        self::assertSelectorNotExists('.bubble__replay');
        self::assertSelectorTextContains('.ready-dialog', 'Maak alle sommen binnen de tijd.');
    }

    public function testHundredLevelsGetTwoMinutes(): void
    {
        $client = static::createClient();
        $client->request('GET', '/rekenen/erbij-eraf/tot-100/door-elkaar');

        self::assertSelectorExists('[data-erbij-eraf-time-limit-value="120000"]');
        self::assertSelectorTextSame('[role="timer"]', '2:00');
        self::assertSelectorTextContains('[data-erbij-eraf-target="passedMessage"]', 'sneller dan twee minuten');
    }

    /**
     * @return iterable<array{string}>
     */
    public static function unknownRounds(): iterable
    {
        yield ['/rekenen/erbij-eraf/tot-1000/erbij'];
        yield ['/rekenen/erbij-eraf/tot-10/keer'];
        yield ['/rekenen/erbij-eraf/tot-10'];
    }

    #[DataProvider('unknownRounds')]
    public function testUnknownRoundIsNotFound(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        self::assertResponseStatusCodeSame(404);
    }
}
