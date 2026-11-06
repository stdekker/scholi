<?php

declare(strict_types=1);

namespace App\Tests\Controller\Game;

use App\Entity\GameResult;
use App\Game\Game;
use App\Game\TafelsMix;
use App\Player\GuestProgress;
use App\Tests\Support\PlayerTestTrait;
use App\Tests\Support\PostJsonTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Cookie;

final class TafelsResultTest extends WebTestCase
{
    use PlayerTestTrait;
    use PostJsonTrait;

    public function testGuestResultIsKeptInASignedCookie(): void
    {
        $client = static::createClient();

        $this->postJson($client, '/rekenen/tafel/6/resultaat', ['durationMs' => 45_000]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonStringEqualsJsonString('{"passed":true}', (string) $client->getResponse()->getContent());
        $cookie = $this->responseGuestCookie($client);
        self::assertNotNull($cookie);
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame(Cookie::SAMESITE_LAX, $cookie->getSameSite());
        self::assertSame('/', $cookie->getPath());
        self::assertGreaterThan(strtotime('+399 days'), $cookie->getExpiresTime());
        self::assertSame([6 => 45_000], $this->bestTimes((string) $cookie->getValue()));
        self::assertSame(0, $this->entityManager()->getRepository(GameResult::class)->count(), 'Nothing is stored on the server for guests');
    }

    public function testGuestCookieKeepsTheBestTimePerTable(): void
    {
        $client = static::createClient();
        $this->setGuestProgress($client, (new GuestProgress())->withResult(Game::Tafels, 6, 50_000, new \DateTimeImmutable()));

        $this->postJson($client, '/rekenen/tafel/6/resultaat', ['durationMs' => 70_000]);
        self::assertJsonStringEqualsJsonString('{"passed":false}', (string) $client->getResponse()->getContent());
        $this->postJson($client, '/rekenen/alle-tafels/2-tot-10/resultaat', ['durationMs' => 110_000]);
        $this->postJson($client, '/rekenen/tafel/3/resultaat', ['durationMs' => 40_000]);
        $this->postJson($client, '/rekenen/tafel/3/resultaat', ['durationMs' => 30_000]);

        self::assertSame([TafelsMix::TwoToTen->value => 110_000, 3 => 30_000, 6 => 50_000], $this->bestTimes((string) $this->responseGuestCookie($client)?->getValue()));
    }

    public function testTamperedGuestCookieIsReplaced(): void
    {
        $client = static::createClient();
        $this->setGuestCookie($client, 'eyJ2IjoxLCJyIjp7IjYiOlsxLDBdfX0.bm90LXNpZ25lZA');

        $this->postJson($client, '/rekenen/tafel/7/resultaat', ['durationMs' => 45_000]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame([7 => 45_000], $this->bestTimes((string) $this->responseGuestCookie($client)?->getValue()));
    }

    public function testPlayerGetsNoGuestCookie(): void
    {
        $client = static::createClient();
        [, $token] = $this->createPlayer();
        $this->logIn($client, $token);

        $this->postJson($client, '/rekenen/tafel/6/resultaat', ['durationMs' => 45_000]);

        self::assertResponseStatusCodeSame(201);
        self::assertNull($this->responseGuestCookie($client));
    }

    public function testGuestPickerShowsBadgesFromTheCookie(): void
    {
        $client = static::createClient();
        $this->setGuestProgress($client, (new GuestProgress())
            ->withResult(Game::Tafels, 6, 48_200, new \DateTimeImmutable())
            ->withResult(Game::Tafels, TafelsMix::TwoToTen->value, 125_000, new \DateTimeImmutable()));

        $client->request('GET', '/rekenen/tafels');

        self::assertSelectorTextContains('.table-picker li:nth-child(6) .progress-badge--passed', '0:49');
        self::assertSelectorCount(1, '.progress-badge');
        self::assertNull($this->responseGuestCookie($client), 'A valid cookie is left alone');

        $client->request('GET', '/rekenen/alle-tafels');

        self::assertSelectorTextContains('.table-picker li:nth-child(2) .progress-badge--done', '2:05');
        self::assertSelectorCount(1, '.progress-badge');
    }

    public function testGuestPickerIgnoresAndClearsATamperedCookie(): void
    {
        $client = static::createClient();
        $value = $this->guestCookie()->encode((new GuestProgress())->withResult(Game::Tafels, 6, 90_000, new \DateTimeImmutable()));
        [$payload, $mac] = explode('.', $value);
        // Same signature, payload changed to claim a faster time.
        $forged = str_replace('90000', '10000', (string) base64_decode(strtr($payload, '-_', '+/'), true));
        $this->setGuestCookie($client, rtrim(strtr(base64_encode($forged), '+/', '-_'), '=').'.'.$mac);

        $client->request('GET', '/rekenen/tafels');

        self::assertSelectorCount(0, '.progress-badge');
        self::assertTrue((bool) $this->responseGuestCookie($client)?->isCleared());
    }

    public function testPlayerPickerIgnoresTheGuestCookie(): void
    {
        $client = static::createClient();
        [, $token] = $this->createPlayer();
        $this->logIn($client, $token);
        $this->setGuestProgress($client, (new GuestProgress())->withResult(Game::Tafels, 6, 48_200, new \DateTimeImmutable()));

        $client->request('GET', '/rekenen/tafels');

        self::assertSelectorCount(0, '.progress-badge');
        self::assertNull($this->responseGuestCookie($client));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidPayloads(): iterable
    {
        yield 'negative' => [['durationMs' => -5]];
        yield 'zero' => [['durationMs' => 0]];
        yield 'more than an hour' => [['durationMs' => 3_600_001]];
        yield 'missing' => [[]];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidPayloads')]
    public function testInvalidPayloadIsRejected(array $payload): void
    {
        $client = static::createClient();
        [, $token] = $this->createPlayer();
        $this->logIn($client, $token);

        $this->postJson($client, '/rekenen/tafel/6/resultaat', $payload);

        self::assertResponseStatusCodeSame(422);
    }

    public function testPickerShowsBadgesForPlayer(): void
    {
        $client = static::createClient();
        [$player, $token] = $this->createPlayer();
        $this->addResult($player, 6, 48_200);
        $this->addResult($player, 7, 75_000);
        $this->logIn($client, $token);

        $client->request('GET', '/rekenen/tafels');

        self::assertSelectorTextContains('.table-picker li:nth-child(6) .progress-badge--passed', '0:49');
        self::assertSelectorTextContains('.table-picker li:nth-child(7) .progress-badge--done', '1:15');
        self::assertSelectorCount(2, '.progress-badge');
    }

    public function testMixPickerShowsABadgePerMix(): void
    {
        $client = static::createClient();
        [$player, $token] = $this->createPlayer();
        $this->addResult($player, TafelsMix::TwoToFive->value, 95_000);
        $this->addResult($player, TafelsMix::TwoToTwelve->value, 130_000);
        $this->logIn($client, $token);

        $client->request('GET', '/rekenen/tafels');
        self::assertSelectorCount(0, '.progress-badge');

        $client->request('GET', '/rekenen/alle-tafels');

        self::assertSelectorTextContains('.table-picker li:nth-child(1) .progress-badge--passed', 'Binnen twee minuten! Beste tijd 1:35');
        self::assertSelectorTextContains('.table-picker li:nth-child(3) .progress-badge--done', 'Gehaald. Beste tijd 2:10');
        self::assertSelectorCount(2, '.progress-badge');
    }

    public function testMixGamePageKnowsWhereToSave(): void
    {
        $client = static::createClient();
        [, $token] = $this->createPlayer();
        $this->logIn($client, $token);

        $client->request('GET', '/rekenen/alle-tafels/2-tot-5');

        self::assertSelectorExists('[data-tafels-save-url-value="/rekenen/alle-tafels/2-tot-5/resultaat"]');
    }

    public function testGamePageKnowsWhereToSave(): void
    {
        $client = static::createClient();
        [, $token] = $this->createPlayer();
        $this->logIn($client, $token);

        $client->request('GET', '/rekenen/tafel/6');

        self::assertSelectorExists('[data-tafels-save-url-value="/rekenen/tafel/6/resultaat"][data-tafels-time-limit-value="60000"]');
    }

    public function testGuestGamePageSavesInTheBrowser(): void
    {
        $client = static::createClient();
        $client->request('GET', '/rekenen/tafel/6');

        self::assertSelectorExists('[data-tafels-save-url-value="/rekenen/tafel/6/resultaat"]');
        self::assertSelectorNotExists('[data-tafels-target="savedMessage"]', 'Saving is silent; only a failure is shown');
        self::assertSelectorExists('[data-tafels-target="saveFailedMessage"]');
    }

    /**
     * @return array<int, int> best time per table in a guest cookie
     */
    private function bestTimes(string $cookieValue): array
    {
        $progress = $this->guestCookie()->decode($cookieValue);
        self::assertNotNull($progress);

        return array_map(static fn (array $result): int => $result['bestMs'], $progress->results['tafels'] ?? []);
    }
}
