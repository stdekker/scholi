<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Player;
use App\Form\PlayerType;
use App\Game\Tafels;
use App\Player\FunNameGenerator;
use App\Repository\PlayerRepository;
use App\Repository\TafelsResultRepository;
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
 */
final class PlayerController extends AbstractController
{
    #[Route('/speler', name: 'app_player', methods: ['GET'])]
    public function show(
        Request $request,
        TafelsResultRepository $results,
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
            'progress' => $results->progressFor($player),
        ]);
    }

    #[Route('/speler/nieuw', name: 'app_player_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        FunNameGenerator $names,
        EntityManagerInterface $entityManager,
        #[CurrentUser] ?Player $player = null,
    ): Response {
        if (null !== $player) {
            return $this->redirectToRoute('app_player');
        }

        $form = $this->createForm(PlayerType::class, ['name' => $names->generate()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $name = $form->get('name')->getData();
            \assert(\is_string($name));

            $token = PlayerToken::generate();
            $entityManager->persist(new Player($name, PlayerToken::hash($token), new \DateTimeImmutable()));
            $entityManager->flush();

            $response = $this->redirectToRoute('app_player', ['nieuw' => 1]);
            $response->headers->setCookie(PlayerCookie::create($request, $token));

            return $response;
        }

        return $this->render('player/new.html.twig', ['form' => $form]);
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
