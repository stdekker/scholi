<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\PracticeDay;
use App\Player\GuestProgress;
use App\Player\PracticeTime;
use App\Tests\Support\PlayerTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PracticeControllerTest extends WebTestCase
{
    use PlayerTestTrait;

    public function testPlayerSecondsAddUpToday(): void
    {
        $client = static::createClient();
        [$player, $token] = $this->createPlayer();
        $this->logIn($client, $token);

        $this->postJson($client, ['seconds' => 45]);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(['minutes' => 0], $this->json($client));

        $this->postJson($client, ['seconds' => 80]);
        self::assertSame(['minutes' => 2], $this->json($client), '45 + 80 seconds is 2 whole minutes');

        $days = $this->entityManager()->getRepository(PracticeDay::class)->findBy(['player' => $player]);
        self::assertCount(1, $days);
        self::assertSame(PracticeTime::today()->format('Y-m-d'), $days[0]->getDay()->format('Y-m-d'));
        self::assertSame(125, $days[0]->getSeconds());
    }

    public function testOnlyTheLastSevenDaysCountAndOlderDaysAreDeleted(): void
    {
        $client = static::createClient();
        [$player, $token] = $this->createPlayer();
        $entityManager = $this->entityManager();
        $entityManager->persist(new PracticeDay($player, PracticeTime::today()->modify('-6 days'), 600));
        $entityManager->persist(new PracticeDay($player, PracticeTime::today()->modify('-7 days'), 900));
        $entityManager->flush();
        $this->logIn($client, $token);

        $this->postJson($client, ['seconds' => 60]);

        self::assertSame(['minutes' => 11], $this->json($client), 'Six days ago still counts, seven days ago does not');
        $entityManager->clear();
        self::assertSame(2, $entityManager->getRepository(PracticeDay::class)->count(['player' => $player]), 'The day outside the window is gone');
    }

    public function testGuestSecondsGoInTheCookie(): void
    {
        $client = static::createClient();
        $this->setGuestProgress($client, (new GuestProgress())->withPractice(PracticeTime::today()->format('Y-m-d'), 100, PracticeTime::windowStart()->format('Y-m-d')));

        $this->postJson($client, ['seconds' => 30]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['minutes' => 2], $this->json($client));
        self::assertSame(0, $this->entityManager()->getRepository(PracticeDay::class)->count(), 'Nothing is stored on the server for guests');
        $progress = $this->guestCookie()->decode((string) $this->responseGuestCookie($client)?->getValue());
        self::assertSame([PracticeTime::today()->format('Y-m-d') => 130], $progress?->practice);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidPayloads(): iterable
    {
        yield 'zero seconds' => [['seconds' => 0]];
        yield 'longer than an hour' => [['seconds' => 3_601]];
        yield 'no seconds' => [[]];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidPayloads')]
    public function testInvalidPayloadIsRejected(array $payload): void
    {
        $client = static::createClient();

        $this->postJson($client, $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->responseGuestCookie($client));
    }

    public function testOnlyJsonIsAccepted(): void
    {
        $client = static::createClient();
        $client->request('POST', '/oefentijd', ['seconds' => 30]);

        self::assertResponseStatusCodeSame(415);
    }

    public function testGamesPostThePracticeTimeHere(): void
    {
        $client = static::createClient();
        $client->request('GET', '/rekenen/tafel/6');

        self::assertSelectorExists('[data-tafels-practice-url-value="/oefentijd"]');
        // The minutes show in the header, not in the completion panel.
        self::assertSelectorNotExists('[data-tafels-target="practiceMessage"]');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postJson(KernelBrowser $client, array $payload): void
    {
        $client->request('POST', '/oefentijd', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return mixed the decoded JSON response
     */
    private function json(KernelBrowser $client): mixed
    {
        return json_decode((string) $client->getResponse()->getContent(), true);
    }
}
