<?php

declare(strict_types=1);

namespace App\Controller\Game;

use App\Entity\Player;
use App\Entity\TafelsResult;
use App\Game\Tafels;
use App\Game\TafelsResultInput;
use App\Player\GuestProgress;
use App\Repository\TafelsResultRepository;
use App\Security\GuestProgressCookie;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class RekenenController extends AbstractController
{
    public function __construct(
        private readonly GuestProgressCookie $guestCookie,
    ) {
    }

    #[Route('/rekenen', name: 'app_game_rekenen', methods: ['GET'])]
    public function index(Request $request, TafelsResultRepository $results, #[CurrentUser] ?Player $player = null): Response
    {
        $guestProgress = null === $player ? $this->guestCookie->read($request) : null;

        $response = $this->render('game/rekenen/index.html.twig', [
            'tables' => Tafels::tables(),
            'all_tables' => Tafels::ALL,
            'progress' => null !== $player ? $results->progressFor($player) : ($guestProgress?->progress() ?? []),
        ]);

        if (null === $player && null === $guestProgress) {
            $response->headers->setCookie($this->guestCookie->clear($request));
        }

        return $response;
    }

    #[Route('/rekenen/tafel/{table}', name: 'app_game_rekenen_tafel', requirements: ['table' => Tafels::TABLE_PATTERN], methods: ['GET'])]
    public function tafel(int $table): Response
    {
        return $this->renderGame($table, $this->generateUrl('app_game_rekenen_tafel_result', ['table' => $table]));
    }

    #[Route('/rekenen/alle-tafels', name: 'app_game_rekenen_alle', methods: ['GET'])]
    public function alleTafels(): Response
    {
        return $this->renderGame(Tafels::ALL, $this->generateUrl('app_game_rekenen_alle_result'));
    }

    /**
     * Stores a completed round: in the database for a player, in the signed guest cookie for a
     * guest. JSON only: together with the SameSite=Lax cookies this protects the endpoint
     * against cross-site posts.
     */
    #[Route('/rekenen/tafel/{table}/resultaat', name: 'app_game_rekenen_tafel_result', requirements: ['table' => Tafels::TABLE_PATTERN], methods: ['POST'], format: 'json')]
    public function saveResult(
        Request $request,
        int $table,
        #[MapRequestPayload(acceptFormat: 'json')] TafelsResultInput $input,
        EntityManagerInterface $entityManager,
        #[CurrentUser] ?Player $player = null,
    ): JsonResponse {
        return $this->storeResult($request, $table, $input, $entityManager, $player);
    }

    #[Route('/rekenen/alle-tafels/resultaat', name: 'app_game_rekenen_alle_result', methods: ['POST'], format: 'json')]
    public function saveAlleTafelsResult(
        Request $request,
        #[MapRequestPayload(acceptFormat: 'json')] TafelsResultInput $input,
        EntityManagerInterface $entityManager,
        #[CurrentUser] ?Player $player = null,
    ): JsonResponse {
        return $this->storeResult($request, Tafels::ALL, $input, $entityManager, $player);
    }

    /**
     * @param int $table 2–10, or Tafels::ALL
     */
    private function renderGame(int $table, string $saveUrl): Response
    {
        $timeLimitMs = Tafels::timeLimitMs($table);

        return $this->render('game/rekenen/tafel.html.twig', [
            'table' => $table,
            'is_all' => Tafels::ALL === $table,
            'time_limit_ms' => $timeLimitMs,
            'time_limit' => Tafels::formatTime($timeLimitMs),
            'save_url' => $saveUrl,
        ]);
    }

    private function storeResult(Request $request, int $table, TafelsResultInput $input, EntityManagerInterface $entityManager, ?Player $player): JsonResponse
    {
        if (null === $player) {
            // A tampered or outdated cookie is simply replaced.
            $progress = ($this->guestCookie->read($request) ?? new GuestProgress())
                ->withResult($table, $input->durationMs, new \DateTimeImmutable());

            $response = $this->json(['passed' => Tafels::isPassed($table, $input->durationMs)]);
            $response->headers->setCookie($this->guestCookie->create($request, $progress));

            return $response;
        }

        $result = new TafelsResult($player, $table, $input->durationMs, new \DateTimeImmutable());
        $entityManager->persist($result);
        $entityManager->flush();

        return $this->json(['passed' => $result->isPassed()], Response::HTTP_CREATED);
    }
}
