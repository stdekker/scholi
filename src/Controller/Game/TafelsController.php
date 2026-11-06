<?php

declare(strict_types=1);

namespace App\Controller\Game;

use App\Entity\Player;
use App\Game\Game;
use App\Game\NextLevelLink;
use App\Game\RoundResultInput;
use App\Game\Tafels;
use App\Game\TafelsMix;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Rekenen › Tafels: the table picker, one table, and the "alle tafels" mixes.
 */
final class TafelsController extends AbstractBubbleGameController
{
    #[Route('/rekenen/tafels', name: 'app_game_rekenen_tafels', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] ?Player $player = null): Response
    {
        return $this->renderPicker($request, $player, Game::Tafels, 'game/rekenen/tafels.html.twig', [
            'tables' => Tafels::tables(),
        ]);
    }

    #[Route('/rekenen/tafel/{table}', name: 'app_game_rekenen_tafel', requirements: ['table' => Tafels::TABLE_PATTERN], methods: ['GET'])]
    public function tafel(int $table): Response
    {
        return $this->renderGame([
            'table' => $table,
            'tables' => [$table],
        ] + $this->bubbleGame(
            Tafels::timeLimitMs($table),
            $this->generateUrl('app_game_rekenen_tafel_result', ['table' => $table]),
            $this->generateUrl('app_game_rekenen_tafels'),
            // After the last table come the mixes.
            $table < Tafels::MAX_TABLE ? new NextLevelLink(
                $this->generateUrl('app_game_rekenen_tafel', ['table' => $table + 1]),
                new TranslatableMessage('game.rekenen.tafels.title', ['%table%' => $table + 1]),
            ) : $this->nextMix(TafelsMix::TwoToFive),
        ));
    }

    /** Picks which tables an "alle tafels" round mixes. */
    #[Route('/rekenen/alle-tafels', name: 'app_game_rekenen_alle', methods: ['GET'])]
    public function alleTafels(Request $request, #[CurrentUser] ?Player $player = null): Response
    {
        return $this->renderPicker($request, $player, Game::Tafels, 'game/rekenen/alle_tafels.html.twig', static function (array $progress): array {
            $practised = Tafels::practisedTables($progress);

            return [
                'mixes' => TafelsMix::cases(),
                'practised' => $practised,
                'practised_open' => \count($practised) >= Tafels::MIN_PRACTISED_TABLES,
            ];
        });
    }

    /** "Geoefende tafels": every table with a finished round. Never saved, its tables change. */
    #[Route('/rekenen/alle-tafels/geoefend', name: 'app_game_rekenen_geoefend', methods: ['GET'])]
    public function geoefend(Request $request, #[CurrentUser] ?Player $player = null): Response
    {
        $practised = Tafels::practisedTables($this->progress($request, $player, Game::Tafels)[0]);
        if (\count($practised) < Tafels::MIN_PRACTISED_TABLES) {
            return $this->redirectToRoute('app_game_rekenen_alle');
        }

        return $this->renderGame([
            'practised' => true,
            'tables' => $practised,
        ] + $this->bubbleGame(Tafels::ALL_TIME_LIMIT_MS, '', $this->generateUrl('app_game_rekenen_alle')));
    }

    #[Route('/rekenen/alle-tafels/{mix}', name: 'app_game_rekenen_mix', requirements: ['mix' => TafelsMix::SLUG_PATTERN], methods: ['GET'])]
    public function mix(string $mix): Response
    {
        $tafelsMix = $this->findMix($mix);

        return $this->renderGame([
            'mix' => $tafelsMix,
            'tables' => $tafelsMix->tables(),
        ] + $this->bubbleGame(
            Tafels::timeLimitMs($tafelsMix->value),
            $this->generateUrl('app_game_rekenen_mix_result', ['mix' => $mix]),
            $this->generateUrl('app_game_rekenen_alle'),
            $this->nextMix($tafelsMix->next()),
        ));
    }

    #[Route('/rekenen/tafel/{table}/resultaat', name: 'app_game_rekenen_tafel_result', requirements: ['table' => Tafels::TABLE_PATTERN], methods: ['POST'], format: 'json')]
    public function saveResult(
        Request $request,
        int $table,
        #[MapRequestPayload(acceptFormat: 'json')] RoundResultInput $input,
        #[CurrentUser] ?Player $player = null,
    ): JsonResponse {
        return $this->storeResult($request, $player, Game::Tafels, $table, $input);
    }

    #[Route('/rekenen/alle-tafels/{mix}/resultaat', name: 'app_game_rekenen_mix_result', requirements: ['mix' => TafelsMix::SLUG_PATTERN], methods: ['POST'], format: 'json')]
    public function saveMixResult(
        Request $request,
        string $mix,
        #[MapRequestPayload(acceptFormat: 'json')] RoundResultInput $input,
        #[CurrentUser] ?Player $player = null,
    ): JsonResponse {
        return $this->storeResult($request, $player, Game::Tafels, $this->findMix($mix)->value, $input);
    }

    private function nextMix(?TafelsMix $mix): ?NextLevelLink
    {
        return null === $mix ? null : new NextLevelLink(
            $this->generateUrl('app_game_rekenen_mix', ['mix' => $mix->slug()]),
            new TranslatableMessage('game.rekenen.mixes.range', ['%last%' => $mix->lastTable()]),
        );
    }

    private function findMix(string $slug): TafelsMix
    {
        return TafelsMix::fromSlug($slug) ?? throw $this->createNotFoundException();
    }

    /**
     * Renders the bubble game: for a single table (table), a fixed mix (mix) or the practised tables.
     *
     * @param array<string, mixed> $game table, mix or practised, tables, and the variables of bubbleGame()
     */
    private function renderGame(array $game): Response
    {
        return $this->render('game/rekenen/tafel.html.twig', $game + [
            'table' => null,
            'mix' => null,
            'practised' => false,
        ]);
    }
}
