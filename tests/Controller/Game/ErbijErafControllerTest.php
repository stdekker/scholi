<?php

declare(strict_types=1);

namespace App\Tests\Controller\Game;

use App\Entity\GameResult;
use App\Game\Game;
use App\Player\GuestProgress;
use App\Tests\Support\PlayerTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ErbijErafControllerTest extends WebTestCase
{
    use PlayerTestTrait;

    public function testPickerListsEveryLevelAndOperation(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/rekenen/erbij-eraf');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('meta[name="turbo-cache-control"][content="no-cache"]');
        self::assertSelectorCount(6, '.level-picker__level');
        self::assertSelectorTextContains('.level-picker__level:nth-child(3) h2', 'Tot 20 met brug');
        self::assertSelectorTextContains('.level-picker__level:nth-child(3) .level-picker__example', '8 + 5');

        $links = $crawler->filter('.level-picker a.table-picker__link')->each(static fn ($link): string => (string) $link->attr('href'));
        self::assertCount(18, $links);
        self::assertSame(['/rekenen/erbij-eraf/tot-10/erbij', '/rekenen/erbij-eraf/tot-10/eraf', '/rekenen/erbij-eraf/tot-10/door-elkaar'], \array_slice($links, 0, 3));
        self::assertContains('/rekenen/erbij-eraf/tot-100-brug/door-elkaar', $links);
    }

    public function testPickerShowsBadgesForAGuest(): void
    {
        $client = static::createClient();
        $this->setGuestProgress($client, (new GuestProgress())
            ->withResult(Game::ErbijEraf, 32, 45_000, new \DateTimeImmutable())
            ->withResult(Game::Tafels, 6, 45_000, new \DateTimeImmutable()));

        $client->request('GET', '/rekenen/erbij-eraf');

        self::assertSelectorCount(1, '.progress-badge');
        self::assertSelectorTextContains('.level-picker__level:nth-child(3) li:nth-child(2) .progress-badge--passed', '0:45');
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
        self::assertSelectorExists('a.ready-dialog__back[href="/rekenen/erbij-eraf"]');
        self::assertSelectorTextContains('.round-complete a[href="/rekenen/erbij-eraf"]', 'Ander niveau');
        self::assertSelectorExists('meta[name="turbo-cache-control"][content="no-cache"]');
    }

    public function testHundredLevelsGetTwoMinutes(): void
    {
        $client = static::createClient();
        $client->request('GET', '/rekenen/erbij-eraf/tot-100/door-elkaar');

        self::assertSelectorExists('[data-erbij-eraf-time-limit-value="120000"]');
        self::assertSelectorTextSame('[role="timer"]', '2:00');
        self::assertSelectorTextContains('[data-erbij-eraf-target="passedMessage"]', 'binnen twee minuten');
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

    /**
     * @return iterable<string, array{string, int, int, bool}>
     */
    public static function results(): iterable
    {
        yield 'tot 10 erbij within a minute' => ['tot-10/erbij', 11, 60_000, true];
        yield 'tot 10 erbij too slow' => ['tot-10/erbij', 11, 60_001, false];
        yield 'tot 20 met brug eraf' => ['tot-20-brug/eraf', 32, 45_000, true];
        yield 'tot 100 door elkaar in two minutes' => ['tot-100/door-elkaar', 53, 120_000, true];
        yield 'tot 100 met brug too slow' => ['tot-100-brug/erbij', 61, 120_001, false];
    }

    #[DataProvider('results')]
    public function testPlayerResultIsStoredAndJudgedByTheServer(string $round, int $level, int $durationMs, bool $passed): void
    {
        $client = static::createClient();
        [$player, $token] = $this->createPlayer();
        $this->logIn($client, $token);

        $this->postJson($client, '/rekenen/erbij-eraf/'.$round.'/resultaat', ['durationMs' => $durationMs]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(['passed' => $passed], json_decode((string) $client->getResponse()->getContent(), true));
        $results = $this->entityManager()->getRepository(GameResult::class)->findBy(['player' => $player]);
        self::assertCount(1, $results);
        self::assertSame(Game::ErbijEraf, $results[0]->getGame());
        self::assertSame($level, $results[0]->getLevel());
        self::assertSame($passed, $results[0]->isPassed());
    }

    public function testGuestResultIsKeptInTheCookie(): void
    {
        $client = static::createClient();

        $this->postJson($client, '/rekenen/erbij-eraf/tot-10/eraf/resultaat', ['durationMs' => 40_000]);

        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->entityManager()->getRepository(GameResult::class)->count(), 'Nothing is stored on the server for guests');
        $progress = $this->guestCookie()->decode((string) $this->responseGuestCookie($client)?->getValue());
        self::assertSame([12], array_keys($progress?->progress(Game::ErbijEraf) ?? []));
    }

    public function testOnlyJsonIsAccepted(): void
    {
        $client = static::createClient();
        $client->request('POST', '/rekenen/erbij-eraf/tot-10/eraf/resultaat', ['durationMs' => 40_000]);

        self::assertResponseStatusCodeSame(415);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postJson(KernelBrowser $client, string $url, array $payload): void
    {
        $client->request('POST', $url, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload, \JSON_THROW_ON_ERROR));
    }
}
