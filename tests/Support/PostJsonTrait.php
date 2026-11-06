<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Posts a JSON body, as the games' scripts do (the endpoints accept JSON only).
 */
trait PostJsonTrait
{
    /**
     * @param array<string, mixed> $payload
     */
    private function postJson(KernelBrowser $client, string $url, array $payload): void
    {
        $client->request('POST', $url, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload, \JSON_THROW_ON_ERROR));
    }
}
