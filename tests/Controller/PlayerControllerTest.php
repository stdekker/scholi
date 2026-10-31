<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\GameResult;
use App\Entity\Player;
use App\Entity\PracticeDay;
use App\Game\Game;
use App\Game\TafelsMix;
use App\Player\FunNameGenerator;
use App\Player\GuestProgress;
use App\Player\PracticeTime;
use App\Security\PlayerCookie;
use App\Security\PlayerToken;
use App\Tests\Support\PlayerTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Cookie;

final class PlayerControllerTest extends WebTestCase
{
    use PlayerTestTrait;

    public function testRegisteringCreatesPlayerAndRemembersLink(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/speler/nieuw');

        self::assertResponseIsSuccessful();
        $suggested = (string) $crawler->filter('#player_name')->attr('value');
        $decoded = json_decode((string) $crawler->filter('[data-name-suggestions-suggestions-value]')->attr('data-name-suggestions-suggestions-value'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        $names = array_values(array_filter(array_column(array_filter($decoded, \is_array(...)), 'name'), \is_string(...)));
        self::assertCount(20, array_unique($names), 'The randomize button gets 20 different names');
        self::assertSame($names[0], $suggested, 'The field starts with the first suggestion');
        self::assertSelectorExists('button.player-form__shuffle[type="button"][hidden]');
        self::assertSelectorCount(\count(FunNameGenerator::emojis()), '#emoji-picker[popover] input[type="radio"][name="player[emoji]"]');
        self::assertSelectorExists('#emoji-picker input[aria-label="Dolfijn"][value="🐬"]');

        $client->submitForm('Maak mijn speler', ['player[name]' => 'Rekenkanon', 'player[emoji]' => '🦉']);

        self::assertResponseRedirects('/speler?nieuw=1');
        $cookie = $this->responsePlayerCookie($client);
        self::assertNotNull($cookie);
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame(Cookie::SAMESITE_LAX, $cookie->getSameSite());
        self::assertSame('/', $cookie->getPath());
        self::assertGreaterThan(strtotime('+399 days'), $cookie->getExpiresTime());

        $token = (string) $cookie->getValue();
        self::assertTrue(PlayerToken::isWellFormed($token));

        $player = $this->entityManager()->getRepository(Player::class)->findOneBy(['name' => 'Rekenkanon']);
        self::assertNotNull($player);
        self::assertSame('🦉', $player->getEmoji());
        self::assertSame(PlayerToken::hash($token), $player->getTokenHash());
        self::assertNotSame($token, $player->getTokenHash(), 'The raw token is never stored');

        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Hoi Rekenkanon 🦉!');
        self::assertSelectorTextSame('.site-header__name', 'Rekenkanon 🦉');
        self::assertSelectorTextContains('.notice--success', 'Je speler is gemaakt!');
        self::assertSelectorExists(\sprintf('#player-link[value="http://localhost/speler/%s"]', $token));
    }

    public function testRegisteringTakesGuestProgressAlong(): void
    {
        $client = static::createClient();
        $playedAt = new \DateTimeImmutable('2026-10-20 14:30:00');
        $this->setGuestProgress($client, (new GuestProgress())
            ->withResult(Game::Tafels, 6, 48_200, $playedAt)
            ->withResult(Game::Tafels, 7, 75_000, $playedAt)
            ->withResult(Game::Tafels, TafelsMix::TwoToTen->value, 110_000, $playedAt)
            ->withResult(Game::ErbijEraf, 32, 50_000, $playedAt)
            ->withPractice(PracticeTime::today()->format('Y-m-d'), 200, PracticeTime::windowStart()->format('Y-m-d')));

        $client->request('GET', '/speler/nieuw');
        self::assertSelectorTextContains('.notice--success', 'gaat mee naar je nieuwe speler');

        $client->submitForm('Maak mijn speler', ['player[name]' => 'Snelle Vos', 'player[emoji]' => '🦊']);

        self::assertResponseRedirects('/speler?nieuw=1');
        self::assertNotNull($this->responsePlayerCookie($client));
        self::assertTrue((bool) $this->responseGuestCookie($client)?->isCleared(), 'The guest cookie is gone after the transfer');

        $player = $this->entityManager()->getRepository(Player::class)->findOneBy(['name' => 'Snelle Vos']);
        self::assertNotNull($player);
        $results = $this->entityManager()->getRepository(GameResult::class)->findBy(['player' => $player], ['level' => 'ASC']);
        self::assertSame(
            [['tafels', TafelsMix::TwoToTen->value, 110_000, true], ['tafels', 6, 48_200, true], ['tafels', 7, 75_000, false], ['erbij-eraf', 32, 50_000, true]],
            array_map(static fn (GameResult $result): array => [$result->getGame()->value, $result->getLevel(), $result->getDurationMs(), $result->isPassed()], $results),
        );
        self::assertEquals($playedAt, $results[1]->getCompletedAt());

        $days = $this->entityManager()->getRepository(PracticeDay::class)->findBy(['player' => $player]);
        self::assertSame([[PracticeTime::today()->format('Y-m-d'), 200]], array_map(static fn (PracticeDay $day): array => [$day->getDay()->format('Y-m-d'), $day->getSeconds()], $days));

        $client->followRedirect();
        self::assertSelectorCount(4, '.progress-badge');
        self::assertSelectorTextContains('.practice-time', 'Deze week heb je 3 minuten geoefend.');
    }

    public function testTamperedGuestProgressIsNotTransferred(): void
    {
        $client = static::createClient();
        $this->setGuestCookie($client, 'eyJ2IjoxLCJyIjp7IjYiOlsxLDBdfX0.bm90LXNpZ25lZA');

        $client->request('GET', '/speler/nieuw');
        self::assertSelectorNotExists('.notice--success');
        $client->submitForm('Maak mijn speler', ['player[name]' => 'Valse Vos']);

        self::assertResponseRedirects('/speler?nieuw=1');
        self::assertTrue((bool) $this->responseGuestCookie($client)?->isCleared());
        $player = $this->entityManager()->getRepository(Player::class)->findOneBy(['name' => 'Valse Vos']);
        self::assertNotNull($player);
        self::assertSame(0, $this->entityManager()->getRepository(GameResult::class)->count(['player' => $player]));
    }

    public function testPlayerLinkLeavesGuestProgressAlone(): void
    {
        $client = static::createClient();
        [$player, $token] = $this->createPlayer('Dappere Draak');
        $this->setGuestProgress($client, (new GuestProgress())->withResult(Game::Tafels, 6, 48_200, new \DateTimeImmutable()));

        $client->request('GET', '/speler/'.$token);

        self::assertResponseRedirects('/speler');
        self::assertNull($this->responseGuestCookie($client));
        self::assertSame(0, $this->entityManager()->getRepository(GameResult::class)->count(['player' => $player]));
    }

    public function testSuggestedNameAndEmojiAreStored(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/speler/nieuw');
        $suggested = (string) $crawler->filter('#player_name')->attr('value');
        $emoji = (string) $crawler->filter('#emoji-picker input[checked]')->attr('value');
        self::assertSame($emoji, trim($crawler->filter('.emoji-picker__preview')->text()));

        $client->submitForm('Maak mijn speler');
        $client->followRedirect();

        self::assertSelectorTextSame('h1', 'Hoi '.$suggested.' '.$emoji.'!');
        $player = $this->entityManager()->getRepository(Player::class)->findOneBy(['name' => $suggested]);
        self::assertSame($emoji, $player?->getEmoji());
    }

    public function testOnlyListedEmojiAreAccepted(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/speler/nieuw');
        $form = $crawler->selectButton('Maak mijn speler')->form();
        $form->disableValidation()->setValues(['player[emoji]' => '💩']);

        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->responsePlayerCookie($client));
    }

    public function testPlayerWithoutEmojiKeepsGenericIcon(): void
    {
        $client = static::createClient();
        [, $token] = $this->createPlayer('Oude Speler');
        $this->logIn($client, $token);

        $client->request('GET', '/');

        self::assertSelectorTextSame('.site-header__name', '👤 Oude Speler');
    }

    public function testNameIsRequired(): void
    {
        $client = static::createClient();
        $client->request('GET', '/speler/nieuw');
        $client->submitForm('Maak mijn speler', ['player[name]' => '   ']);

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->responsePlayerCookie($client));
    }

    public function testGuestIsSentToRegistration(): void
    {
        $client = static::createClient();
        $client->request('GET', '/speler');

        self::assertResponseRedirects('/speler/nieuw');
    }

    public function testRegisteredPlayerSkipsRegistration(): void
    {
        $client = static::createClient();
        [, $token] = $this->createPlayer();
        $this->logIn($client, $token);

        $client->request('GET', '/speler/nieuw');

        self::assertResponseRedirects('/speler');
    }

    public function testPlayerLinkLogsInOnThisBrowser(): void
    {
        $client = static::createClient();
        [, $token] = $this->createPlayer('Dappere Draak');

        $client->request('GET', '/speler/'.$token);

        self::assertResponseRedirects('/speler');
        self::assertSame($token, $this->responsePlayerCookie($client)?->getValue());

        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Hoi Dappere Draak!');
        self::assertSelectorTextContains('.site-header__player', 'Dappere Draak');
    }

    public function testUnknownPlayerLinkIsNotFoundAndSetsNoCookie(): void
    {
        $client = static::createClient();
        $client->request('GET', '/speler/'.PlayerToken::generate());

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('h1', 'Deze spelerslink kennen we niet');
        self::assertNull($this->responsePlayerCookie($client));
    }

    public function testRememberedPlayerIsRecognisedAndCookieRefreshed(): void
    {
        $client = static::createClient();
        [, $token] = $this->createPlayer('Wijze Uil');
        $this->logIn($client, $token);

        $client->request('GET', '/');

        self::assertSelectorTextContains('.site-header__player', 'Wijze Uil');
        $cookie = $this->responsePlayerCookie($client);
        self::assertSame($token, $cookie?->getValue());
        self::assertGreaterThan(strtotime('+399 days'), $cookie->getExpiresTime());
    }

    public function testInvalidCookieContinuesAsGuestAndIsCleared(): void
    {
        $client = static::createClient();
        $this->logIn($client, PlayerToken::generate());

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.site-header__player', 'Voortgang bewaren');
        $cookie = $this->responsePlayerCookie($client);
        self::assertNotNull($cookie);
        self::assertTrue($cookie->isCleared());
        self::assertSame('/', $cookie->getPath());
    }

    public function testNotFoundPageKeepsTheCookie(): void
    {
        $client = static::createClient();
        [, $token] = $this->createPlayer();
        $this->logIn($client, $token);

        $client->request('GET', '/bestaat-niet');

        self::assertResponseStatusCodeSame(404);
        self::assertNull($this->responsePlayerCookie($client));
        self::assertSame($token, $client->getCookieJar()->get(PlayerCookie::NAME)?->getValue());
    }

    public function testPlayerPageShowsProgress(): void
    {
        $client = static::createClient();
        [$player, $token] = $this->createPlayer();
        $this->addResult($player, 6, 45_000);
        $this->addResult($player, 7, 90_000);
        $this->addResult($player, 7, 75_000);
        $this->addResult($player, TafelsMix::TwoToTen->value, 130_000);
        $this->addResult($player, 9, 50_000);
        $this->logIn($client, $token);

        $client->request('GET', '/speler');
        self::assertSelectorTextContains('.progress-list__item:nth-child(13)', 'Tafels 2 tot 5');
        self::assertSelectorTextContains('.progress-list__item:nth-child(14)', 'Tafels 2 tot 10');
        self::assertSelectorTextContains('.progress-list__item:nth-child(14) .progress-badge--done', 'Beste tijd 2:10');
        self::assertSelectorTextContains('.progress-list__item:nth-child(15)', 'Tafels 2 tot 12');
        self::assertSelectorCount(15, '.progress-list__item');
        self::assertSelectorTextContains('.progress-list__item:nth-child(9) .progress-badge--passed', 'Binnen een minuut! Beste tijd 0:50');

        self::assertSelectorTextContains('.progress-list__item:nth-child(6) .progress-badge--passed', 'Beste tijd 0:45');
        self::assertSelectorTextContains('.progress-list__item:nth-child(7) .progress-badge--done', 'Beste tijd 1:15');
        self::assertSelectorTextContains('.progress-list__item:nth-child(1)', 'Nog niet geoefend');
    }

    public function testHeaderShowsBadgesAndMinutes(): void
    {
        $client = static::createClient();
        [$player, $token] = $this->createPlayer();
        $this->addResult($player, 6, 45_000);
        $this->addResult($player, 6, 70_000);                    // the same badge again
        $this->addResult($player, 7, 90_000);                    // ✓ counts too
        $this->addResult($player, 32, 50_000, Game::ErbijEraf);
        $entityManager = $this->entityManager();
        $entityManager->persist(new PracticeDay($player, PracticeTime::today(), 400));
        $entityManager->flush();
        $this->logIn($client, $token);

        $client->request('GET', '/rekenen');

        self::assertSelectorTextContains('.site-header__stats', '⭐ 3');
        self::assertSelectorTextContains('.site-header__stats', '⏱️ 6 min');
        self::assertSelectorTextContains('.site-header__player .visually-hidden', '3 badges, deze week 6 minuten geoefend');
    }

    public function testGuestHeaderHasNoStats(): void
    {
        $client = static::createClient();
        $client->request('GET', '/rekenen');

        self::assertSelectorNotExists('.site-header__stats');
    }

    public function testPlayerPageShowsPracticeTime(): void
    {
        $client = static::createClient();
        [$player, $token] = $this->createPlayer();
        $playerId = $player->getId();
        // Every request reboots the kernel, so look the player up in the current entity manager.
        $addDay = function (string $daysAgo, int $seconds) use ($playerId): void {
            $entityManager = $this->entityManager();
            $player = $entityManager->find(Player::class, $playerId);
            self::assertNotNull($player);
            $entityManager->persist(new PracticeDay($player, PracticeTime::today()->modify($daysAgo), $seconds));
            $entityManager->flush();
        };
        $this->logIn($client, $token);

        $client->request('GET', '/speler');
        self::assertSelectorTextContains('.practice-time', 'Deze week heb je nog geen hele minuut geoefend.');

        $addDay('-0 days', 70);
        $client->request('GET', '/speler');
        self::assertSelectorTextContains('.practice-time', 'Deze week heb je 1 minuut geoefend.');

        $addDay('-3 days', 1_000);
        $addDay('-10 days', 6_000);
        $client->request('GET', '/speler');
        self::assertSelectorTextContains('.practice-time', 'Deze week heb je 17 minuten geoefend.');
    }

    public function testPlayerPageShowsErbijErafProgress(): void
    {
        $client = static::createClient();
        [$player, $token] = $this->createPlayer();
        $this->addResult($player, 32, 45_000, Game::ErbijEraf);   // tot 20 met brug, eraf
        $this->addResult($player, 63, 130_000, Game::ErbijEraf);  // tot 100 met brug, door elkaar
        $this->logIn($client, $token);

        $client->request('GET', '/speler');

        self::assertSelectorCount(6, '.progress-table tbody tr');
        self::assertSelectorTextContains('.progress-table thead', 'Erbij');
        self::assertSelectorTextContains('.progress-table tbody tr:nth-child(3) th', 'Tot 20 met brug');
        self::assertSelectorTextContains('.progress-table tbody tr:nth-child(3) td:nth-of-type(2) .progress-badge--passed', 'Binnen een minuut! Beste tijd 0:45');
        self::assertSelectorTextContains('.progress-table tbody tr:nth-child(6) td:nth-of-type(3) .progress-badge--done', 'Gehaald. Beste tijd 2:10');
        self::assertSelectorTextContains('.progress-table tbody tr:nth-child(1) td:nth-of-type(1)', 'Nog niet geoefend');
        self::assertSelectorCount(2, '.progress-table .progress-badge');
        self::assertSelectorExists('a[href="/rekenen/erbij-eraf"]');
    }

    public function testLogoutForgetsPlayerInThisBrowser(): void
    {
        $client = static::createClient();
        [, $token] = $this->createPlayer();
        $this->logIn($client, $token);

        $client->request('GET', '/speler');
        $client->submitForm('Uitloggen');

        self::assertResponseRedirects('/');
        $cookie = $this->responsePlayerCookie($client);
        self::assertNotNull($cookie);
        self::assertTrue($cookie->isCleared());
        self::assertSame('/', $cookie->getPath());

        $client->followRedirect();
        self::assertSelectorTextContains('.site-header__player', 'Voortgang bewaren');
    }

    public function testLogoutRequiresCsrfToken(): void
    {
        $client = static::createClient();
        [, $token] = $this->createPlayer();
        $this->logIn($client, $token);

        $client->request('POST', '/speler/uitloggen', ['_csrf_token' => 'invalid']);

        self::assertResponseStatusCodeSame(403);
        self::assertFalse($this->responsePlayerCookie($client)?->isCleared() ?? false);
    }
}
