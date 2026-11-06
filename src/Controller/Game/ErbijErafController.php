<?php

declare(strict_types=1);

namespace App\Controller\Game;

use App\Entity\Player;
use App\Game\ErbijEraf;
use App\Game\ErbijErafLevel;
use App\Game\ErbijErafOperation;
use App\Game\Game;
use App\Game\NextLevelLink;
use App\Game\RoundResultInput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Rekenen › Erbij en eraf: the level picker and one round of a level.
 */
final class ErbijErafController extends AbstractBubbleGameController
{
    private const array REQUIREMENTS = ['level' => ErbijErafLevel::SLUG_PATTERN, 'operation' => ErbijErafOperation::PATTERN];

    /** Erbij, eraf or door elkaar (?soort=, erbij by default), then one of the levels. */
    #[Route('/rekenen/erbij-eraf', name: 'app_game_rekenen_erbij_eraf', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] ?Player $player = null): Response
    {
        return $this->renderPicker($request, $player, Game::ErbijEraf, 'game/rekenen/erbij_eraf/index.html.twig', [
            'operations' => ErbijErafOperation::cases(),
            'operation' => ErbijErafOperation::tryFrom($request->query->getString('soort')) ?? ErbijErafOperation::Erbij,
            'grid' => ErbijEraf::grid(),
        ]);
    }

    #[Route('/rekenen/erbij-eraf/{level}/{operation}', name: 'app_game_rekenen_erbij_eraf_spel', requirements: self::REQUIREMENTS, methods: ['GET'])]
    public function spel(string $level, string $operation): Response
    {
        [$erbijErafLevel, $erbijErafOperation] = $this->find($level, $operation);

        return $this->render('game/rekenen/erbij_eraf/spel.html.twig', [
            'level' => $erbijErafLevel,
            'operation' => $erbijErafOperation,
        ] + $this->bubbleGame(
            $erbijErafLevel->timeLimitMs(),
            $this->generateUrl('app_game_rekenen_erbij_eraf_result', ['level' => $level, 'operation' => $operation]),
            // Back to the picker with the same choice made.
            $this->generateUrl('app_game_rekenen_erbij_eraf', ['soort' => $operation]),
            $this->next($erbijErafLevel, $erbijErafOperation),
        ));
    }

    #[Route('/rekenen/erbij-eraf/{level}/{operation}/resultaat', name: 'app_game_rekenen_erbij_eraf_result', requirements: self::REQUIREMENTS, methods: ['POST'], format: 'json')]
    public function saveResult(
        Request $request,
        string $level,
        string $operation,
        #[MapRequestPayload(acceptFormat: 'json')] RoundResultInput $input,
        #[CurrentUser] ?Player $player = null,
    ): JsonResponse {
        return $this->storeResult($request, $player, Game::ErbijEraf, ErbijEraf::storedLevel(...$this->find($level, $operation)), $input);
    }

    /**
     * The next level with the same operation; after the last level, the first level of the next
     * operation (erbij → eraf → door elkaar).
     */
    private function next(ErbijErafLevel $level, ErbijErafOperation $operation): ?NextLevelLink
    {
        $nextLevel = $level->next();
        $nextOperation = null === $nextLevel ? $operation->next() : $operation;
        if (null === $nextOperation) {
            return null;
        }
        $nextLevel ??= ErbijErafLevel::Tot10;

        return new NextLevelLink(
            $this->generateUrl('app_game_rekenen_erbij_eraf_spel', ['level' => $nextLevel->slug(), 'operation' => $nextOperation->value]),
            new TranslatableMessage('game.rekenen.erbij_eraf.title', [
                '%level%' => new TranslatableMessage('game.rekenen.erbij_eraf.levels.'.$nextLevel->key().'.name'),
                '%operation%' => new TranslatableMessage('game.rekenen.erbij_eraf.operations.'.$nextOperation->key()),
            ]),
        );
    }

    /**
     * @return array{ErbijErafLevel, ErbijErafOperation}
     */
    private function find(string $level, string $operation): array
    {
        return [
            ErbijErafLevel::fromSlug($level) ?? throw $this->createNotFoundException(),
            ErbijErafOperation::tryFrom($operation) ?? throw $this->createNotFoundException(),
        ];
    }
}
