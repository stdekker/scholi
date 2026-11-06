<?php

declare(strict_types=1);

namespace App\Tests\Controller\Game;

use App\Entity\GameResult;
use App\Game\Game;
use App\Game\TafelsMix;
use App\Tests\Support\PlayerTestTrait;
use App\Tests\Support\PostJsonTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * What every bubble game's `…/resultaat` route does the same: store a round for a player, keep it
 * in the cookie for a guest, accept JSON only, and 404 on a round that does not exist. A new game
 * adds its rounds to rounds() and its unknown URLs to unknownRounds().
 */
final class BubbleGameResultTest extends WebTestCase
{
    use PlayerTestTrait;
    use PostJsonTrait;

    /**
     * @return iterable<string, array{string, Game, int, int}> result URL, game, stored level, time limit in ms
     */
    public static function rounds(): iterable
    {
        yield 'tafel van 6' => ['/rekenen/tafel/6/resultaat', Game::Tafels, 6, 60_000];
        foreach (TafelsMix::cases() as $mix) {
            yield 'tafels '.$mix->slug() => ['/rekenen/alle-tafels/'.$mix->slug().'/resultaat', Game::Tafels, $mix->value, 120_000];
        }
        yield 'erbij tot 10' => ['/rekenen/erbij-eraf/tot-10/erbij/resultaat', Game::ErbijEraf, 11, 60_000];
        yield 'eraf tot 10' => ['/rekenen/erbij-eraf/tot-10/eraf/resultaat', Game::ErbijEraf, 12, 60_000];
        yield 'eraf tot 20 met brug' => ['/rekenen/erbij-eraf/tot-20-brug/eraf/resultaat', Game::ErbijEraf, 32, 60_000];
        yield 'door elkaar tot 100' => ['/rekenen/erbij-eraf/tot-100/door-elkaar/resultaat', Game::ErbijEraf, 53, 120_000];
        yield 'erbij tot 100 met brug' => ['/rekenen/erbij-eraf/tot-100-brug/erbij/resultaat', Game::ErbijEraf, 61, 120_000];
        yield 'dictee groep 6 overschrijven' => ['/taal/dictee/groep-6/overschrijven/resultaat', Game::Dictee, 61, 180_000];
        yield 'dictee groep 6 letters aanvullen' => ['/taal/dictee/groep-6/aanvullen/resultaat', Game::Dictee, 62, 180_000];
        yield 'dictee groep 8 streepjes' => ['/taal/dictee/groep-8/streepjes/resultaat', Game::Dictee, 83, 180_000];
        yield 'dictee groep 8 zonder hulp' => ['/taal/dictee/groep-8/zonder-hulp/resultaat', Game::Dictee, 84, 180_000];
    }

    #[DataProvider('rounds')]
    public function testPlayerResultIsStoredAndJudgedByTheServer(string $url, Game $game, int $level, int $limitMs): void
    {
        $client = static::createClient();
        [$player, $token] = $this->createPlayer();
        $this->logIn($client, $token);

        foreach ([[intdiv($limitMs, 2), true], [$limitMs, true], [$limitMs + 1, false]] as [$durationMs, $passed]) {
            $this->postJson($client, $url, ['durationMs' => $durationMs]);

            self::assertResponseStatusCodeSame(201);
            self::assertSame(['passed' => $passed], json_decode((string) $client->getResponse()->getContent(), true));
        }

        $results = $this->entityManager()->getRepository(GameResult::class)->findBy(['player' => $player], ['id' => 'ASC']);
        self::assertCount(3, $results);
        foreach ($results as $result) {
            self::assertSame($game, $result->getGame());
            self::assertSame($level, $result->getLevel());
        }
        self::assertSame([true, true, false], array_map(static fn (GameResult $result): bool => $result->isPassed(), $results));
        self::assertSame($limitMs + 1, $results[2]->getDurationMs());
    }

    #[DataProvider('rounds')]
    public function testGuestResultIsKeptInTheCookie(string $url, Game $game, int $level, int $limitMs): void
    {
        $client = static::createClient();

        $this->postJson($client, $url, ['durationMs' => $limitMs]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['passed' => true], json_decode((string) $client->getResponse()->getContent(), true));
        self::assertSame(0, $this->entityManager()->getRepository(GameResult::class)->count(), 'Nothing is stored on the server for guests');
        $progress = $this->guestCookie()->decode((string) $this->responseGuestCookie($client)?->getValue());
        self::assertSame([$level], array_keys($progress?->progress($game) ?? []));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function resultUrls(): iterable
    {
        foreach (self::rounds() as $name => [$url]) {
            yield $name => [$url];
        }
    }

    #[DataProvider('resultUrls')]
    public function testOnlyJsonIsAccepted(string $url): void
    {
        $client = static::createClient();
        [, $token] = $this->createPlayer();
        $this->logIn($client, $token);

        $client->request('POST', $url, ['durationMs' => 40_000]);

        self::assertResponseStatusCodeSame(415);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownRounds(): iterable
    {
        yield 'tafel 13' => ['/rekenen/tafel/13/resultaat'];
        yield 'geoefende tafels are never saved' => ['/rekenen/alle-tafels/geoefend/resultaat'];
        yield 'unknown mix' => ['/rekenen/alle-tafels/2-tot-7/resultaat'];
        yield 'unknown erbij en eraf level' => ['/rekenen/erbij-eraf/tot-1000/erbij/resultaat'];
        yield 'unknown erbij en eraf operation' => ['/rekenen/erbij-eraf/tot-10/keer/resultaat'];
        yield 'unknown dictee groep' => ['/taal/dictee/groep-5/aanvullen/resultaat'];
        yield 'unknown dictee level' => ['/taal/dictee/groep-6/raden/resultaat'];
    }

    #[DataProvider('unknownRounds')]
    public function testResultOfAnUnknownRoundIsNotStored(string $url): void
    {
        $client = static::createClient();
        [, $token] = $this->createPlayer();
        $this->logIn($client, $token);

        $this->postJson($client, $url, ['durationMs' => 45_000]);

        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $this->entityManager()->getRepository(GameResult::class)->count());
    }
}
