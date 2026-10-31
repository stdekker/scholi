<?php

declare(strict_types=1);

namespace App\Controller\Game;

use App\Entity\Player;
use App\Entity\TafelsResult;
use App\Game\TableProgress;
use App\Game\Tafels;
use App\Game\TafelsMix;
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
        [$progress, $invalidCookie] = $this->progress($request, $results, $player);

        $response = $this->render('game/rekenen/index.html.twig', [
            'tables' => Tafels::tables(),
            'progress' => $progress,
        ]);

        return $this->clearInvalidCookie($request, $response, $invalidCookie);
    }

    #[Route('/rekenen/tafel/{table}', name: 'app_game_rekenen_tafel', requirements: ['table' => Tafels::TABLE_PATTERN], methods: ['GET'])]
    public function tafel(int $table): Response
    {
        return $this->renderGame([
            'table' => $table,
            'tables' => [$table],
            'time_limit_ms' => Tafels::timeLimitMs($table),
            'save_url' => $this->generateUrl('app_game_rekenen_tafel_result', ['table' => $table]),
            'back_url' => $this->generateUrl('app_game_rekenen'),
        ]);
    }

    /** Picks which tables an "alle tafels" round mixes. */
    #[Route('/rekenen/alle-tafels', name: 'app_game_rekenen_alle', methods: ['GET'])]
    public function alleTafels(Request $request, TafelsResultRepository $results, #[CurrentUser] ?Player $player = null): Response
    {
        [$progress, $invalidCookie] = $this->progress($request, $results, $player);
        $practised = Tafels::practisedTables($progress);

        $response = $this->render('game/rekenen/alle_tafels.html.twig', [
            'mixes' => TafelsMix::cases(),
            'progress' => $progress,
            'practised' => $practised,
            'practised_open' => \count($practised) >= Tafels::MIN_PRACTISED_TABLES,
        ]);

        return $this->clearInvalidCookie($request, $response, $invalidCookie);
    }

    /** "Geoefende tafels": every table with a finished round. Never saved, its tables change. */
    #[Route('/rekenen/alle-tafels/geoefend', name: 'app_game_rekenen_geoefend', methods: ['GET'])]
    public function geoefend(Request $request, TafelsResultRepository $results, #[CurrentUser] ?Player $player = null): Response
    {
        $practised = Tafels::practisedTables($this->progress($request, $results, $player)[0]);
        if (\count($practised) < Tafels::MIN_PRACTISED_TABLES) {
            return $this->redirectToRoute('app_game_rekenen_alle');
        }

        return $this->renderGame([
            'practised' => true,
            'tables' => $practised,
            'time_limit_ms' => Tafels::ALL_TIME_LIMIT_MS,
            'save_url' => '',
            'back_url' => $this->generateUrl('app_game_rekenen_alle'),
        ]);
    }

    #[Route('/rekenen/alle-tafels/{mix}', name: 'app_game_rekenen_mix', requirements: ['mix' => TafelsMix::SLUG_PATTERN], methods: ['GET'])]
    public function mix(string $mix): Response
    {
        $tafelsMix = $this->findMix($mix);

        return $this->renderGame([
            'mix' => $tafelsMix,
            'tables' => $tafelsMix->tables(),
            'time_limit_ms' => Tafels::timeLimitMs($tafelsMix->value),
            'save_url' => $this->generateUrl('app_game_rekenen_mix_result', ['mix' => $mix]),
            'back_url' => $this->generateUrl('app_game_rekenen_alle'),
        ]);
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

    #[Route('/rekenen/alle-tafels/{mix}/resultaat', name: 'app_game_rekenen_mix_result', requirements: ['mix' => TafelsMix::SLUG_PATTERN], methods: ['POST'], format: 'json')]
    public function saveMixResult(
        Request $request,
        string $mix,
        #[MapRequestPayload(acceptFormat: 'json')] TafelsResultInput $input,
        EntityManagerInterface $entityManager,
        #[CurrentUser] ?Player $player = null,
    ): JsonResponse {
        return $this->storeResult($request, $this->findMix($mix)->value, $input, $entityManager, $player);
    }

    private function findMix(string $slug): TafelsMix
    {
        return TafelsMix::fromSlug($slug) ?? throw $this->createNotFoundException();
    }

    /**
     * The best result per table and mix: from the database for a player, from the signed cookie
     * for a guest (empty, and flagged for clearing, when that cookie is not valid).
     *
     * @return array{array<int, TableProgress>, bool} progress keyed by table number, and whether the guest cookie is invalid
     */
    private function progress(Request $request, TafelsResultRepository $results, ?Player $player): array
    {
        if (null !== $player) {
            return [$results->progressFor($player), false];
        }

        $guestProgress = $this->guestCookie->read($request);

        return [$guestProgress?->progress() ?? [], null === $guestProgress];
    }

    private function clearInvalidCookie(Request $request, Response $response, bool $invalidCookie): Response
    {
        if ($invalidCookie) {
            $response->headers->setCookie($this->guestCookie->clear($request));
        }

        return $response;
    }

    /**
     * Renders the bubble game: for a single table (table), a fixed mix (mix) or the practised tables.
     *
     * @param array{table?: int, mix?: TafelsMix, practised?: bool, tables: list<int>, time_limit_ms: int, save_url: string, back_url: string} $game
     */
    private function renderGame(array $game): Response
    {
        return $this->render('game/rekenen/tafel.html.twig', $game + [
            'table' => null,
            'mix' => null,
            'practised' => false,
            'time_limit' => Tafels::formatTime($game['time_limit_ms']),
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
