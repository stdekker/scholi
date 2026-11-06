<?php

declare(strict_types=1);

namespace App\Tests\Controller\Game;

use App\Entity\GameResult;
use App\Game\Dictee;
use App\Game\DicteeGroup;
use App\Game\Game;
use App\Player\GuestProgress;
use App\Tests\Support\PlayerTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DicteeControllerTest extends WebTestCase
{
    use PlayerTestTrait;

    public function testPickerShowsTheLevelsOfGroep6ByDefault(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/taal/dictee');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('meta[name="turbo-cache-control"][content="no-cache"]');
        self::assertSame(
            ['/taal/dictee?groep=6', '/taal/dictee?groep=8'],
            $crawler->filter('.operation-switch a')->each(static fn ($link): string => (string) $link->attr('href')),
        );
        self::assertSelectorTextContains('.operation-switch a[aria-current="page"]', 'Groep 6');
        self::assertSelectorCount(1, '.operation-switch a[aria-current]');

        $links = $crawler->filter('.table-picker--levels a.table-picker__link')->each(static fn ($link): string => (string) $link->attr('href'));
        self::assertSame([
            '/taal/dictee/groep-6/overschrijven',
            '/taal/dictee/groep-6/aanvullen',
            '/taal/dictee/groep-6/streepjes',
            '/taal/dictee/groep-6/zonder-hulp',
        ], $links);
        self::assertSelectorTextContains('.table-picker--levels li:nth-child(2)', 'Letters aanvullen');
        self::assertSelectorTextContains('.table-picker--levels li:nth-child(2) .table-picker__note', 'Een deel van de letters ontbreekt.');
    }

    public function testPickerSwitchesToAnotherGroep(): void
    {
        $client = static::createClient();
        $client->request('GET', '/taal/dictee?groep=8');

        self::assertSelectorTextContains('.operation-switch a[aria-current="page"]', 'Groep 8');
        self::assertSelectorExists('.table-picker--levels a[href="/taal/dictee/groep-8/streepjes"]');

        foreach (['5', 'groep-8', ''] as $unknown) {
            $client->request('GET', '/taal/dictee', ['groep' => $unknown]);
            self::assertSelectorTextContains('.operation-switch a[aria-current="page"]', 'Groep 6', 'An unknown groep falls back to groep 6');
        }
    }

    public function testPickerShowsBadgesOfTheChosenGroep(): void
    {
        $client = static::createClient();
        $this->setGuestProgress($client, (new GuestProgress())
            ->withResult(Game::Dictee, 82, 150_000, new \DateTimeImmutable())
            ->withResult(Game::Tafels, 6, 45_000, new \DateTimeImmutable()));

        $client->request('GET', '/taal/dictee');
        self::assertSelectorCount(0, '.progress-badge');

        $client->request('GET', '/taal/dictee?groep=8');
        self::assertSelectorCount(1, '.progress-badge');
        self::assertSelectorTextContains('.table-picker--levels li:nth-child(2) .progress-badge--passed', '2:30');
    }

    public function testGamePageConfiguresTheGame(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/taal/dictee/groep-8/streepjes');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Dictee Groep 8: Streepjes');
        self::assertSelectorTextContains('.bubble-game__header', 'binnen drie minuten');
        self::assertSelectorExists('section.bubble-game.bubble-game--words[data-controller="dictee"][data-dictee-mode-value="blanks"][data-dictee-time-limit-value="180000"][data-dictee-answer-max-length-value="20"]');
        self::assertSelectorExists('[data-dictee-save-url-value="/taal/dictee/groep-8/streepjes/resultaat"]');
        self::assertSelectorTextSame('[role="timer"]', '3:00');
        self::assertSelectorExists('a.ready-dialog__back[href="/taal/dictee?groep=8"]');
        self::assertSelectorTextContains('.round-complete a[href="/taal/dictee?groep=8"]', 'Ander niveau');
        self::assertSelectorExists('meta[name="turbo-cache-control"][content="no-cache"]');
        self::assertSelectorTextContains('[data-dictee-target="passedMessage"]', 'binnen drie minuten');
    }

    public function testGamePageHasTheWordsOfTheGroepWithTheirRecordings(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/taal/dictee/groep-6/overschrijven');

        $audio = json_decode((string) $crawler->filter('[data-controller="dictee"]')->attr('data-dictee-audio-value'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertIsArray($audio);
        self::assertSame(Dictee::words(DicteeGroup::Groep6), array_keys($audio));
        self::assertNotContains('verantwoordelijk', array_keys($audio), 'Groep 8 words are not in groep 6');
        // Served by this site (digested AssetMapper URLs), never by a speech service.
        self::assertIsString($audio['vriendelijk']);
        self::assertMatchesRegularExpression('#^/assets/audio/taal/dictee/vriendelijk-[A-Za-z0-9_-]+\.mp3$#', $audio['vriendelijk']);
        foreach ($audio as $url) {
            self::assertIsString($url);
            self::assertStringStartsWith('/assets/audio/taal/dictee/', $url);
        }
    }

    public function testGamePageAsksForWordsInsteadOfSums(): void
    {
        $client = static::createClient();
        $client->request('GET', '/taal/dictee/groep-6/aanvullen');

        // Typed on the keyboard: no digits, only the OK button, and no autocorrect on the answer.
        self::assertSelectorCount(1, '.keypad__key');
        self::assertSelectorExists('.keypad.keypad--check-only .keypad__key--check');
        self::assertSelectorExists('input.bubble__answer--word[inputmode="text"][maxlength="20"][autocomplete="off"][autocapitalize="off"][autocorrect="off"][spellcheck="false"]');
        self::assertSelectorTextContains('.bubble button.bubble__replay', 'Luister nog eens');
        self::assertSelectorExists('.bubble__speaker');
        self::assertSelectorTextContains('.ready-dialog', 'Zet je geluid aan.');
        self::assertSelectorNotExists('.ready-dialog:contains("sommen")');
        self::assertSelectorExists('[data-dictee-correct-value="Goed zo! Het woord is %answer%."]');
        self::assertSelectorExists('[data-dictee-remaining-value="Woorden over: %count%"]');
        self::assertSelectorExists('[data-dictee-time-up-value="De tijd is om! Schrijf de woorden rustig af."]');
        self::assertSelectorExists('[data-dictee-word-label-hint-value="Woord %number%: %hint%"][data-dictee-blank-label-value="leeg"]');
        self::assertSelectorTextContains('[data-dictee-target="lateMessage"]', 'Je hebt alle woorden geschreven.');
        self::assertSelectorExists('input[aria-label="Schrijf het woord"]');
    }

    /**
     * @return iterable<string, array{string, string|null, string}>
     */
    public static function nextLevels(): iterable
    {
        yield 'show less' => ['groep-6/overschrijven', '/taal/dictee/groep-6/aanvullen', 'Volgende: Dictee Groep 6: Letters aanvullen'];
        yield 'stays in the groep' => ['groep-8/streepjes', '/taal/dictee/groep-8/zonder-hulp', 'Volgende: Dictee Groep 8: Zonder hulp'];
        yield 'last' => ['groep-8/zonder-hulp', null, ''];
    }

    #[DataProvider('nextLevels')]
    public function testRecommendsTheNextLevelOfTheSameGroep(string $game, ?string $nextUrl, string $label): void
    {
        $client = static::createClient();
        $client->request('GET', '/taal/dictee/'.$game);

        if (null === $nextUrl) {
            self::assertSelectorNotExists('[data-dictee-target="next"]');

            return;
        }
        self::assertSelectorTextContains('.round-complete a[data-dictee-target="next"][hidden][href="'.$nextUrl.'"]', $label);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function unknownRounds(): iterable
    {
        yield ['/taal/dictee/groep-5/overschrijven'];
        yield ['/taal/dictee/groep-6/raden'];
        yield ['/taal/dictee/groep-6'];
        yield ['/taal/dictee/overschrijven'];
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
        yield 'groep 6 overschrijven within three minutes' => ['groep-6/overschrijven', 61, 180_000, true];
        yield 'groep 6 overschrijven too slow' => ['groep-6/overschrijven', 61, 180_001, false];
        yield 'groep 6 letters aanvullen' => ['groep-6/aanvullen', 62, 95_000, true];
        yield 'groep 8 streepjes' => ['groep-8/streepjes', 83, 120_000, true];
        yield 'groep 8 zonder hulp too slow' => ['groep-8/zonder-hulp', 84, 240_000, false];
    }

    #[DataProvider('results')]
    public function testPlayerResultIsStoredAndJudgedByTheServer(string $round, int $level, int $durationMs, bool $passed): void
    {
        $client = static::createClient();
        [$player, $token] = $this->createPlayer();
        $this->logIn($client, $token);

        $this->postJson($client, '/taal/dictee/'.$round.'/resultaat', ['durationMs' => $durationMs]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(['passed' => $passed], json_decode((string) $client->getResponse()->getContent(), true));
        $results = $this->entityManager()->getRepository(GameResult::class)->findBy(['player' => $player]);
        self::assertCount(1, $results);
        self::assertSame(Game::Dictee, $results[0]->getGame());
        self::assertSame($level, $results[0]->getLevel());
        self::assertSame($passed, $results[0]->isPassed());
    }

    public function testGuestResultIsKeptInTheCookie(): void
    {
        $client = static::createClient();

        $this->postJson($client, '/taal/dictee/groep-8/aanvullen/resultaat', ['durationMs' => 100_000]);

        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->entityManager()->getRepository(GameResult::class)->count(), 'Nothing is stored on the server for guests');
        $progress = $this->guestCookie()->decode((string) $this->responseGuestCookie($client)?->getValue());
        self::assertSame([82], array_keys($progress?->progress(Game::Dictee) ?? []));
    }

    public function testResultOfAnUnknownRoundIsNotStored(): void
    {
        $client = static::createClient();

        $this->postJson($client, '/taal/dictee/groep-5/aanvullen/resultaat', ['durationMs' => 100_000]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testOnlyJsonIsAccepted(): void
    {
        $client = static::createClient();
        $client->request('POST', '/taal/dictee/groep-6/overschrijven/resultaat', ['durationMs' => 40_000]);

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
