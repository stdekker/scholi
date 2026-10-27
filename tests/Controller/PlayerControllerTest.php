<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Player;
use App\Game\Tafels;
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
        self::assertNotSame('', $crawler->filter('#player_name')->attr('value'), 'A fun name is suggested');

        $client->submitForm('Maak mijn speler', ['player[name]' => 'Rekenkanon']);

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
        self::assertSame(PlayerToken::hash($token), $player->getTokenHash());
        self::assertNotSame($token, $player->getTokenHash(), 'The raw token is never stored');

        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Hoi Rekenkanon!');
        self::assertSelectorTextContains('.notice--success', 'Je speler is gemaakt!');
        self::assertSelectorExists(\sprintf('#player-link[value="http://localhost/speler/%s"]', $token));
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
        $this->addResult($player, Tafels::ALL, 130_000);
        $this->addResult($player, 9, 50_000);
        $this->logIn($client, $token);

        $client->request('GET', '/speler');
        self::assertSelectorTextContains('.progress-list__item:nth-child(10)', 'Alle tafels');
        self::assertSelectorTextContains('.progress-list__item:nth-child(10) .progress-badge--done', 'Beste tijd 2:10');
        self::assertSelectorTextContains('.progress-list__item:nth-child(8) .progress-badge--passed', 'Binnen een minuut! Beste tijd 0:50');

        self::assertSelectorTextContains('.progress-list__item:nth-child(5) .progress-badge--passed', 'Beste tijd 0:45');
        self::assertSelectorTextContains('.progress-list__item:nth-child(6) .progress-badge--done', 'Beste tijd 1:15');
        self::assertSelectorTextContains('.progress-list__item:nth-child(1)', 'Nog niet geoefend');
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
