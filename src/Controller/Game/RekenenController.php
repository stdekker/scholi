<?php

declare(strict_types=1);

namespace App\Controller\Game;

use App\Entity\Player;
use App\Entity\TafelsResult;
use App\Game\Tafels;
use App\Game\TafelsResultInput;
use App\Repository\TafelsResultRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class RekenenController extends AbstractController
{
    #[Route('/rekenen', name: 'app_game_rekenen', methods: ['GET'])]
    public function index(TafelsResultRepository $results, #[CurrentUser] ?Player $player = null): Response
    {
        return $this->render('game/rekenen/index.html.twig', [
            'tables' => Tafels::tables(),
            'progress' => null !== $player ? $results->progressFor($player) : [],
        ]);
    }

    #[Route('/rekenen/tafel/{table}', name: 'app_game_rekenen_tafel', requirements: ['table' => Tafels::TABLE_PATTERN], methods: ['GET'])]
    public function tafel(int $table): Response
    {
        return $this->render('game/rekenen/tafel.html.twig', [
            'table' => $table,
            'time_limit_ms' => Tafels::TIME_LIMIT_MS,
        ]);
    }

    /**
     * Stores a completed round for the logged-in player. JSON only: together with the
     * SameSite=Lax player cookie this protects the endpoint against cross-site posts.
     */
    #[Route('/rekenen/tafel/{table}/resultaat', name: 'app_game_rekenen_tafel_result', requirements: ['table' => Tafels::TABLE_PATTERN], methods: ['POST'], format: 'json')]
    public function saveResult(
        int $table,
        #[MapRequestPayload(acceptFormat: 'json')] TafelsResultInput $input,
        EntityManagerInterface $entityManager,
        #[CurrentUser] ?Player $player = null,
    ): JsonResponse {
        if (null === $player) {
            return $this->json(['error' => 'no_player'], Response::HTTP_UNAUTHORIZED);
        }

        $result = new TafelsResult($player, $table, $input->durationMs, new \DateTimeImmutable());
        $entityManager->persist($result);
        $entityManager->flush();

        return $this->json(['passed' => $result->isPassed()], Response::HTTP_CREATED);
    }
}
