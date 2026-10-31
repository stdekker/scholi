<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Game\Game;
use App\Game\TafelsMix;
use App\Player\GuestProgress;
use App\Player\PracticeTime;
use App\Security\GuestProgressCookie;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

final class GuestProgressCookieTest extends TestCase
{
    private const string SECRET = 'test-secret';

    public function testRoundTrip(): void
    {
        $cookie = new GuestProgressCookie(self::SECRET);
        $progress = (new GuestProgress())
            ->withResult(Game::Tafels, TafelsMix::TwoToTen->value, 110_000, new \DateTimeImmutable('@1793000000'))
            ->withResult(Game::Tafels, TafelsMix::TwoToFive->value, 80_000, new \DateTimeImmutable('@1793000000'))
            ->withResult(Game::Tafels, TafelsMix::TwoToTwelve->value, 115_000, new \DateTimeImmutable('@1793000000'))
            ->withResult(Game::Tafels, 6, 48_200, new \DateTimeImmutable('@1792000000'));

        $decoded = $cookie->decode($cookie->encode($progress));

        self::assertNotNull($decoded);
        // Negative JSON keys ("-12") come back as ints too.
        self::assertSame([-12, -5, 0, 6], array_keys($decoded->results['tafels']));
        self::assertSame(110_000, $decoded->results['tafels'][TafelsMix::TwoToTen->value]['bestMs']);
        self::assertSame(1_792_000_000, $decoded->results['tafels'][6]['completedAt']->getTimestamp());
    }

    public function testOnlyAlleTafelsStaysAnObject(): void
    {
        $cookie = new GuestProgressCookie(self::SECRET);
        $value = $cookie->encode((new GuestProgress())->withResult(Game::Tafels, TafelsMix::TwoToTen->value, 90_000, new \DateTimeImmutable()));

        self::assertStringContainsString('"r":{"tafels":{"0":[90000,', $this->payload($value));
        self::assertSame([TafelsMix::TwoToTen->value], array_keys($cookie->decode($value)->results['tafels'] ?? []));
    }

    public function testEmptyProgressRoundTrips(): void
    {
        $cookie = new GuestProgressCookie(self::SECRET);

        self::assertTrue($cookie->decode($cookie->encode(new GuestProgress()))?->isEmpty());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'empty' => [''];
        yield 'no signature' => ['eyJ2IjoxLCJyIjp7fX0'];
        yield 'wrong signature' => ['eyJ2IjoxLCJyIjp7fX0.bm90LXNpZ25lZA'];
        yield 'too many parts' => ['a.b.c'];
    }

    #[DataProvider('invalidValues')]
    public function testRejectsUnsignedValues(string $value): void
    {
        self::assertNull((new GuestProgressCookie(self::SECRET))->decode($value));
    }

    public function testRejectsAChangedPayload(): void
    {
        $cookie = new GuestProgressCookie(self::SECRET);
        [, $mac] = explode('.', $cookie->encode((new GuestProgress())->withResult(Game::Tafels, 6, 90_000, new \DateTimeImmutable())));

        self::assertNull($cookie->decode(self::base64Url('{"v":1,"r":{"6":[10000,0]}}').'.'.$mac));
    }

    public function testRejectsAnotherSecret(): void
    {
        $value = (new GuestProgressCookie('other-secret'))->encode((new GuestProgress())->withResult(Game::Tafels, 6, 1_000, new \DateTimeImmutable()));

        self::assertNull((new GuestProgressCookie(self::SECRET))->decode($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function signedButInvalid(): iterable
    {
        yield 'not base64' => ['!!!'];
        yield 'not json' => [self::base64Url('nope')];
        yield 'other version' => [self::base64Url('{"v":3,"r":{}}')];
        yield 'no results' => [self::base64Url('{"v":1}')];
        yield 'too deep' => [self::base64Url('{"v":2,"r":{"tafels":{"6":[[1],0]}}}')];
    }

    #[DataProvider('signedButInvalid')]
    public function testRejectsSignedGarbage(string $payload): void
    {
        self::assertNull((new GuestProgressCookie(self::SECRET))->decode($this->sign($payload)));
    }

    public function testSkipsEntriesOutsideTheRules(): void
    {
        $payload = self::base64Url(json_encode(['v' => 1, 'r' => [
            '-1' => [5_000, 0],        // no table -1
            '13' => [5_000, 0],        // no table 13
            '3' => [0, 0],             // too fast
            '4' => [3_600_001, 0],     // more than an hour
            '5' => ['5000', 0],        // not an int
            '7' => [5_000, -1],        // before 1970
            '8' => [5_000],            // incomplete
            '9' => [45_000, 1_792_000_000],
        ]], \JSON_THROW_ON_ERROR));

        $progress = (new GuestProgressCookie(self::SECRET))->decode($this->sign($payload));

        self::assertSame([9], array_keys($progress->results['tafels'] ?? []));
    }

    public function testReadsVersionOneAsTafels(): void
    {
        // Cookies from before erbij en eraf only had tafels.
        $payload = self::base64Url('{"v":1,"r":{"0":[110000,1792000000],"6":[45000,1792000000]}}');

        $progress = (new GuestProgressCookie(self::SECRET))->decode($this->sign($payload));

        self::assertSame(['tafels'], array_keys($progress->results ?? []));
        self::assertSame([0, 6], array_keys($progress?->progress(Game::Tafels) ?? []));
    }

    public function testKeepsGamesApart(): void
    {
        $cookie = new GuestProgressCookie(self::SECRET);
        $value = $cookie->encode((new GuestProgress())
            ->withResult(Game::Tafels, 6, 45_000, new \DateTimeImmutable())
            ->withResult(Game::ErbijEraf, 32, 50_000, new \DateTimeImmutable()));

        $decoded = $cookie->decode($value);

        self::assertSame([6], array_keys($decoded?->progress(Game::Tafels) ?? []));
        self::assertSame([32], array_keys($decoded?->progress(Game::ErbijEraf) ?? []));
    }

    public function testSkipsUnknownGamesAndLevels(): void
    {
        $payload = self::base64Url(json_encode(['v' => 2, 'r' => [
            'schaken' => ['1' => [5_000, 0]],
            'erbij-eraf' => ['14' => [5_000, 0], '11' => [45_000, 0]],
            'tafels' => ['13' => [5_000, 0]],
        ]], \JSON_THROW_ON_ERROR));

        $progress = (new GuestProgressCookie(self::SECRET))->decode($this->sign($payload));

        self::assertSame(['erbij-eraf' => [11]], array_map(array_keys(...), $progress->results ?? []));
    }

    public function testKeepsPracticeDaysOfTheWindow(): void
    {
        $cookie = new GuestProgressCookie(self::SECRET);
        $today = PracticeTime::today()->format('Y-m-d');
        $sixDaysAgo = PracticeTime::today()->modify('-6 days')->format('Y-m-d');
        $value = $cookie->encode((new GuestProgress())
            ->withPractice($sixDaysAgo, 300, $sixDaysAgo)
            ->withPractice($today, 90, $sixDaysAgo));

        self::assertSame([$sixDaysAgo => 300, $today => 90], $cookie->decode($value)?->practice);
    }

    public function testSkipsPracticeDaysOutsideTheRules(): void
    {
        $today = PracticeTime::today()->format('Y-m-d');
        $payload = self::base64Url(json_encode(['v' => 2, 'r' => [], 'p' => [
            PracticeTime::today()->modify('-7 days')->format('Y-m-d') => 60,   // outside the window
            PracticeTime::today()->modify('+1 day')->format('Y-m-d') => 60,    // in the future
            'gisteren' => 60,                                                  // not a day
            $today => 90_000,                                                   // more than a day
        ]], \JSON_THROW_ON_ERROR));

        self::assertSame([], (new GuestProgressCookie(self::SECRET))->decode($this->sign($payload))?->practice);
    }

    public function testVersionTwoWithoutPracticeIsStillValid(): void
    {
        $payload = self::base64Url('{"v":2,"r":{"tafels":{"6":[45000,1792000000]}}}');

        $progress = (new GuestProgressCookie(self::SECRET))->decode($this->sign($payload));

        self::assertNotNull($progress);
        self::assertSame([], $progress->practice);
        self::assertSame([6], array_keys($progress->progress(Game::Tafels)));
    }

    public function testClampsDatesInTheFuture(): void
    {
        $payload = self::base64Url('{"v":1,"r":{"6":[45000,4102444800]}}');

        $progress = (new GuestProgressCookie(self::SECRET))->decode($this->sign($payload));

        self::assertLessThanOrEqual(time(), $progress?->results['tafels'][6]['completedAt']->getTimestamp());
    }

    public function testReadsTheRequestCookie(): void
    {
        $cookie = new GuestProgressCookie(self::SECRET);
        $value = $cookie->encode((new GuestProgress())->withResult(Game::Tafels, 6, 45_000, new \DateTimeImmutable()));

        self::assertTrue($cookie->read(new Request())?->isEmpty(), 'No cookie: empty progress');
        self::assertSame([6], array_keys($cookie->read(new Request(cookies: [GuestProgressCookie::NAME => $value]))->results['tafels'] ?? []));
        self::assertNull($cookie->read(new Request(cookies: [GuestProgressCookie::NAME => 'forged'])), 'Invalid cookie: null');
    }

    public function testCookieIsHttpOnlyAndLax(): void
    {
        $cookie = (new GuestProgressCookie(self::SECRET))->create(Request::create('https://scholi.nl/'), new GuestProgress());

        self::assertSame(GuestProgressCookie::NAME, $cookie->getName());
        self::assertTrue($cookie->isHttpOnly());
        self::assertTrue($cookie->isSecure());
        self::assertSame(Cookie::SAMESITE_LAX, $cookie->getSameSite());
        self::assertSame('/', $cookie->getPath());
    }

    public function testRefusesAnEmptySecret(): void
    {
        $this->expectException(\LogicException::class);

        new GuestProgressCookie('');
    }

    /** Signs like GuestProgressCookie does: HMAC-SHA256 with a key derived for this purpose. */
    private function sign(string $payload): string
    {
        $key = hash_hmac('sha256', 'scholi-guest-progress', self::SECRET, true);

        return $payload.'.'.self::base64Url(hash_hmac('sha256', $payload, $key, true));
    }

    private function payload(string $value): string
    {
        return (string) base64_decode(strtr(explode('.', $value)[0], '-_', '+/'), true);
    }

    private static function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
