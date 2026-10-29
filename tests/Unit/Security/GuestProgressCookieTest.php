<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Game\Tafels;
use App\Player\GuestProgress;
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
            ->withResult(Tafels::ALL, 110_000, new \DateTimeImmutable('@1793000000'))
            ->withResult(6, 48_200, new \DateTimeImmutable('@1792000000'));

        $decoded = $cookie->decode($cookie->encode($progress));

        self::assertNotNull($decoded);
        self::assertSame([Tafels::ALL, 6], array_keys($decoded->results));
        self::assertSame(110_000, $decoded->results[Tafels::ALL]['bestMs']);
        self::assertSame(1_792_000_000, $decoded->results[6]['completedAt']->getTimestamp());
    }

    public function testOnlyAlleTafelsStaysAnObject(): void
    {
        $cookie = new GuestProgressCookie(self::SECRET);
        $value = $cookie->encode((new GuestProgress())->withResult(Tafels::ALL, 90_000, new \DateTimeImmutable()));

        self::assertStringContainsString('"r":{"0":[90000,', $this->payload($value));
        self::assertSame([Tafels::ALL], array_keys($cookie->decode($value)->results ?? []));
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
        [, $mac] = explode('.', $cookie->encode((new GuestProgress())->withResult(6, 90_000, new \DateTimeImmutable())));

        self::assertNull($cookie->decode(self::base64Url('{"v":1,"r":{"6":[10000,0]}}').'.'.$mac));
    }

    public function testRejectsAnotherSecret(): void
    {
        $value = (new GuestProgressCookie('other-secret'))->encode((new GuestProgress())->withResult(6, 1_000, new \DateTimeImmutable()));

        self::assertNull((new GuestProgressCookie(self::SECRET))->decode($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function signedButInvalid(): iterable
    {
        yield 'not base64' => ['!!!'];
        yield 'not json' => [self::base64Url('nope')];
        yield 'other version' => [self::base64Url('{"v":2,"r":{}}')];
        yield 'no results' => [self::base64Url('{"v":1}')];
        yield 'too deep' => [self::base64Url('{"v":1,"r":{"6":[[1],0]}}')];
    }

    #[DataProvider('signedButInvalid')]
    public function testRejectsSignedGarbage(string $payload): void
    {
        self::assertNull((new GuestProgressCookie(self::SECRET))->decode($this->sign($payload)));
    }

    public function testSkipsEntriesOutsideTheRules(): void
    {
        $payload = self::base64Url(json_encode(['v' => 1, 'r' => [
            '1' => [5_000, 0],         // no table 1
            '11' => [5_000, 0],        // no table 11
            '3' => [0, 0],             // too fast
            '4' => [3_600_001, 0],     // more than an hour
            '5' => ['5000', 0],        // not an int
            '7' => [5_000, -1],        // before 1970
            '8' => [5_000],            // incomplete
            '9' => [45_000, 1_792_000_000],
        ]], \JSON_THROW_ON_ERROR));

        $progress = (new GuestProgressCookie(self::SECRET))->decode($this->sign($payload));

        self::assertSame([9], array_keys($progress->results ?? []));
    }

    public function testClampsDatesInTheFuture(): void
    {
        $payload = self::base64Url('{"v":1,"r":{"6":[45000,4102444800]}}');

        $progress = (new GuestProgressCookie(self::SECRET))->decode($this->sign($payload));

        self::assertLessThanOrEqual(time(), $progress?->results[6]['completedAt']->getTimestamp());
    }

    public function testReadsTheRequestCookie(): void
    {
        $cookie = new GuestProgressCookie(self::SECRET);
        $value = $cookie->encode((new GuestProgress())->withResult(6, 45_000, new \DateTimeImmutable()));

        self::assertTrue($cookie->read(new Request())?->isEmpty(), 'No cookie: empty progress');
        self::assertSame([6], array_keys($cookie->read(new Request(cookies: [GuestProgressCookie::NAME => $value]))->results ?? []));
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
