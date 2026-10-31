<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\GameResult;
use App\Entity\Player;
use App\Form\PlayerType;
use App\Game\ErbijEraf;
use App\Game\Game;
use App\Game\Tafels;
use App\Game\TafelsMix;
use App\Player\FunNameGenerator;
use App\Repository\GameResultRepository;
use App\Repository\PlayerRepository;
use App\Security\GuestProgressCookie;
use App\Security\PlayerCookie;
use App\Security\PlayerToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Link-based player accounts: register with just a (fun) name, come back via the player
 * link /speler/{token}, which is remembered in a cookie. Logout is handled by the firewall.
 *
 * Results played as a guest move to the new player on registration only; opening an existing
 * link leaves them alone, so on a shared computer they never end up with another child.
 */
final class PlayerController extends AbstractController
{
    /** Names the "Andere naam" button cycles through without asking the server again. */
    private const int NAME_SUGGESTIONS = 20;

    #[Route('/speler', name: 'app_player', methods: ['GET'])]
    public function show(
        Request $request,
        GameResultRepository $results,
        #[CurrentUser] ?Player $player = null,
    ): Response {
        if (null === $player) {
            return $this->redirectToRoute('app_player_new');
        }

        return $this->render('player/show.html.twig', [
            'player' => $player,
            'link' => $this->generateUrl(
                'app_player_link',
                ['token' => $request->cookies->getString(PlayerCookie::NAME)],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
            'is_new' => $request->query->getBoolean('nieuw'),
            'tables' => Tafels::tables(),
            'mixes' => TafelsMix::cases(),
            'progress' => $results->progressFor($player, Game::Tafels),
            'erbij_eraf_grid' => ErbijEraf::grid(),
            'erbij_eraf_progress' => $results->progressFor($player, Game::ErbijEraf),
        ]);
    }

    #[Route('/speler/nieuw', name: 'app_player_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        FunNameGenerator $names,
        EntityManagerInterface $entityManager,
        GuestProgressCookie $guestCookie,
        #[CurrentUser] ?Player $player = null,
    ): Response {
        if (null !== $player) {
            return $this->redirectToRoute('app_player');
        }

        $guestProgress = $guestCookie->read($request);
        $suggestions = $names->suggestions(self::NAME_SUGGESTIONS);
        $form = $this->createForm(PlayerType::class, $suggestions[0]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $name = $form->get('name')->getData();
            $emoji = $form->get('emoji')->getData();
            \assert(\is_string($name) && \is_string($emoji));

            $token = PlayerToken::generate();
            $player = new Player($name, PlayerToken::hash($token), new \DateTimeImmutable(), $emoji);
            $entityManager->persist($player);
            foreach ($guestProgress?->entries() ?? [] as [$game, $level, $bestMs, $completedAt]) {
                $entityManager->persist(new GameResult($player, $game, $level, $bestMs, $completedAt));
            }
            $entityManager->flush();

            $response = $this->redirectToRoute('app_player', ['nieuw' => 1]);
            $response->headers->setCookie(PlayerCookie::create($request, $token));
            if ($request->cookies->has(GuestProgressCookie::NAME)) {
                $response->headers->setCookie($guestCookie->clear($request));
            }

            return $response;
        }

        return $this->render('player/new.html.twig', [
            'form' => $form,
            'name_suggestions' => $suggestions,
            'carries_over_progress' => !($guestProgress?->isEmpty() ?? true),
        ]);
    }

    #[Route('/speler/{token}', name: 'app_player_link', requirements: ['token' => PlayerToken::PATTERN], methods: ['GET'])]
    public function link(string $token, Request $request, PlayerRepository $players): Response
    {
        if (null === $players->findOneByToken($token)) {
            return $this->render('player/unknown_link.html.twig', [], new Response(status: Response::HTTP_NOT_FOUND));
        }

        $response = $this->redirectToRoute('app_player');
        $response->headers->setCookie(PlayerCookie::create($request, $token));

        return $response;
    }
}
