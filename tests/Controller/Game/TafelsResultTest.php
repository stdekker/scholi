<?php

declare(strict_types=1);

namespace App\Tests\Controller\Game;

use App\Entity\TafelsResult;
use App\Game\Tafels;
use App\Tests\Support\PlayerTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TafelsResultTest extends WebTestCase
{
    use PlayerTestTrait;

    /**
     * @return iterable<string, array{int, bool}>
     */
    public static function durations(): iterable
    {
        yield 'within the minute' => [45_000, true];
        yield 'exactly one minute' => [60_000, true];
        yield 'too slow' => [75_000, false];
    }

    #[DataProvider('durations')]
    public function testPlayerResultIsStoredAndJudgedByTheServer(int $durationMs, bool $passed): void
    {
        $client = static::createClient();
        [$player, $token] = $this->createPlayer();
        $this->logIn($client, $token);

        $this->postJson($client, '/rekenen/tafel/6/resultaat', ['durationMs' => $durationMs]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonStringEqualsJsonString(json_encode(['passed' => $passed], \JSON_THROW_ON_ERROR), (string) $client->getResponse()->getContent());

        $results = $this->entityManager()->getRepository(TafelsResult::class)->findBy(['player' => $player]);
        self::assertCount(1, $results);
        self::assertSame(6, $results[0]->getTableNumber());
        self::assertSame($durationMs, $results[0]->getDurationMs());
        self::assertSame($passed, $results[0]->isPassed());
    }

    /**
     * @return iterable<string, array{int, bool}>
     */
    public static function alleTafelsDurations(): iterable
    {
        yield 'within two minutes' => [110_000, true];
        yield 'exactly two minutes' => [120_000, true];
        yield 'too slow' => [125_000, false];
    }

    #[DataProvider('alleTafelsDurations')]
    public function testAlleTafelsResultGetsTwoMinutes(int $durationMs, bool $passed): void
    {
        $client = static::createClient();
        [$player, $token] = $this->createPlayer();
        $this->logIn($client, $token);

        $this->postJson($client, '/rekenen/alle-tafels/resultaat', ['durationMs' => $durationMs]);

        self::assertResponseStatusCodeSame(201);
        $results = $this->entityManager()->getRepository(TafelsResult::class)->findBy(['player' => $player]);
        self::assertCount(1, $results);
        self::assertSame(Tafels::ALL, $results[0]->getTableNumber());
        self::assertSame($passed, $results[0]->isPassed());
    }

    public function testGuestCannotSaveAlleTafelsResults(): void
    {
        $client = static::createClient();

        $this->postJson($client, '/rekenen/alle-tafels/resultaat', ['durationMs' => 45_000]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testGuestCannotSaveResults(): void
    {
        $client = static::createClient();

        $this->postJson($client, '/rekenen/tafel/6/resultaat', ['durationMs' => 45_000]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testOnlyJsonIsAccepted(): void
    {
        $client = static::createClient();
        [, $token] = $this->createPlayer();
        $this->logIn($client, $token);

        $client->request('POST', '/rekenen/tafel/6/resultaat', ['durationMs' => 45_000]);

        self::assertResponseStatusCodeSame(415);
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

    public function testInvalidTableIsNotFound(): void
    {
        $client = static::createClient();
        [, $token] = $this->createPlayer();
        $this->logIn($client, $token);

        $this->postJson($client, '/rekenen/tafel/11/resultaat', ['durationMs' => 45_000]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testPickerShowsBadgesForPlayer(): void
    {
        $client = static::createClient();
        [$player, $token] = $this->createPlayer();
        $this->addResult($player, 6, 48_200);
        $this->addResult($player, 7, 75_000);
        $this->logIn($client, $token);

        $client->request('GET', '/rekenen');

        self::assertSelectorTextContains('.table-picker li:nth-child(5) .progress-badge--passed', '0:49');
        self::assertSelectorTextContains('.table-picker li:nth-child(6) .progress-badge--done', '1:15');
        self::assertSelectorCount(2, '.progress-badge');
    }

    public function testPickerShowsAlleTafelsBadge(): void
    {
        $client = static::createClient();
        [$player, $token] = $this->createPlayer();
        $this->addResult($player, Tafels::ALL, 95_000);
        $this->logIn($client, $token);

        $client->request('GET', '/rekenen');

        self::assertSelectorTextContains('.table-picker__item--all .progress-badge--passed', 'Binnen twee minuten! Beste tijd 1:35');
        self::assertSelectorCount(1, '.progress-badge');
    }

    public function testAlleTafelsGamePageKnowsWhereToSave(): void
    {
        $client = static::createClient();
        [, $token] = $this->createPlayer();
        $this->logIn($client, $token);

        $client->request('GET', '/rekenen/alle-tafels');

        self::assertSelectorExists('[data-tafels-save-url-value="/rekenen/alle-tafels/resultaat"]');
    }

    public function testGamePageKnowsWhereToSave(): void
    {
        $client = static::createClient();
        [, $token] = $this->createPlayer();
        $this->logIn($client, $token);

        $client->request('GET', '/rekenen/tafel/6');

        self::assertSelectorExists('[data-tafels-save-url-value="/rekenen/tafel/6/resultaat"][data-tafels-time-limit-value="60000"]');
    }

    public function testGuestGamePageDoesNotSave(): void
    {
        $client = static::createClient();
        $client->request('GET', '/rekenen/tafel/6');

        self::assertSelectorExists('[data-tafels-save-url-value=""]');
        self::assertSelectorTextContains('.round-complete', 'Wil je je sterren bewaren?');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postJson(KernelBrowser $client, string $url, array $payload): void
    {
        $client->request('POST', $url, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload, \JSON_THROW_ON_ERROR));
    }
}
