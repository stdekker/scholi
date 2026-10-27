<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Player;
use App\Repository\PlayerRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Logs the player in on every request that carries the player cookie (stateless firewall).
 * Failures never block the request: the visitor simply continues as a guest and
 * PlayerCookieSubscriber removes the invalid cookie.
 */
final class PlayerCookieAuthenticator extends AbstractAuthenticator
{
    /** Request attribute: true when the cookie authenticated, false when it was invalid. */
    public const string RESULT_ATTRIBUTE = '_player_cookie_valid';

    public function __construct(private readonly PlayerRepository $players)
    {
    }

    public function supports(Request $request): bool
    {
        return $request->cookies->has(PlayerCookie::NAME);
    }

    public function authenticate(Request $request): Passport
    {
        $token = $request->cookies->getString(PlayerCookie::NAME);

        return new SelfValidatingPassport(new UserBadge(
            PlayerToken::hash($token),
            fn (): ?Player => $this->players->findOneByToken($token),
        ));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $request->attributes->set(self::RESULT_ATTRIBUTE, true);

        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->attributes->set(self::RESULT_ATTRIBUTE, false);

        return null;
    }
}
