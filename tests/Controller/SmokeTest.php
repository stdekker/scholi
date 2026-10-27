<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SmokeTest extends WebTestCase
{
    #[DataProvider('publicPages')]
    public function testPublicPageLoads(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('html[lang="nl"]');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function publicPages(): iterable
    {
        yield 'home' => ['/'];
        yield 'rekenen' => ['/rekenen'];
        yield 'tafel van 2' => ['/rekenen/tafel/2'];
        yield 'tafel van 10' => ['/rekenen/tafel/10'];
        yield 'alle tafels' => ['/rekenen/alle-tafels'];
    }
}
