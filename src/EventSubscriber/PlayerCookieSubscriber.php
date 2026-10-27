<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Security\PlayerCookie;
use App\Security\PlayerCookieAuthenticator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps the player cookie alive while the player is active (sliding expiry) and removes
 * a cookie whose token no longer matches a player. Only acts when the authenticator ran.
 */
final class PlayerCookieSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onResponse'];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $valid = $request->attributes->get(PlayerCookieAuthenticator::RESULT_ATTRIBUTE);

        if (!$event->isMainRequest() || !\is_bool($valid)) {
            return;
        }

        $response = $event->getResponse();
        foreach ($response->headers->getCookies() as $cookie) {
            if (PlayerCookie::NAME === $cookie->getName()) {
                return; // A controller or the logout already set or cleared it.
            }
        }

        $response->headers->setCookie($valid
            ? PlayerCookie::create($request, $request->cookies->getString(PlayerCookie::NAME))
            : PlayerCookie::clear($request));
    }
}
