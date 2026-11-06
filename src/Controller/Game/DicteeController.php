<?php

declare(strict_types=1);

namespace App\Controller\Game;

use App\Entity\Player;
use App\Game\Dictee;
use App\Game\DicteeGroup;
use App\Game\DicteeLevel;
use App\Game\Game;
use App\Game\RoundResultInput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Taal › Dictee: the picker and one round. The game speaks a word, the pupil writes it. The
 * groep decides which words, the level how much of the word they see.
 */
final class DicteeController extends AbstractBubbleGameController
{
    private const array REQUIREMENTS = ['group' => DicteeGroup::SLUG_PATTERN, 'level' => DicteeLevel::SLUG_PATTERN];

    /** Pick a groep (?groep=, the first one by default), then one of the levels. */
    #[Route('/taal/dictee', name: 'app_game_taal_dictee', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] ?Player $player = null): Response
    {
        [$progress, $invalidCookie] = $this->progress($request, $player, Game::Dictee);

        $response = $this->render('game/taal/dictee/index.html.twig', [
            'groups' => DicteeGroup::cases(),
            'group' => DicteeGroup::fromSlug('groep-'.$request->query->getString('groep')) ?? DicteeGroup::cases()[0],
            'grid' => Dictee::grid(),
            'progress' => $progress,
        ]);

        return $this->clearInvalidCookie($request, $response, $invalidCookie);
    }

    #[Route('/taal/dictee/{group}/{level}', name: 'app_game_taal_dictee_spel', requirements: self::REQUIREMENTS, methods: ['GET'])]
    public function spel(string $group, string $level): Response
    {
        [$dicteeGroup, $dicteeLevel] = $this->find($group, $level);
        $words = Dictee::words($dicteeGroup);

        return $this->render('game/taal/dictee/spel.html.twig', [
            'group' => $dicteeGroup,
            'level' => $dicteeLevel,
            'answer_max_length' => Dictee::ANSWER_MAX_LENGTH,
            // Logical AssetMapper paths of the recordings, keyed by word; the template turns them into URLs.
            'audio_paths' => array_combine($words, array_map(Dictee::audioPath(...), $words)),
        ] + $this->bubbleGame(
            $dicteeLevel->timeLimitMs(),
            $this->generateUrl('app_game_taal_dictee_result', ['group' => $group, 'level' => $level]),
            // Back to the picker with the same groep chosen.
            $this->generateUrl('app_game_taal_dictee', ['groep' => $dicteeGroup->value]),
            $this->next($dicteeGroup, $dicteeLevel),
        ));
    }

    #[Route('/taal/dictee/{group}/{level}/resultaat', name: 'app_game_taal_dictee_result', requirements: self::REQUIREMENTS, methods: ['POST'], format: 'json')]
    public function saveResult(
        Request $request,
        string $group,
        string $level,
        #[MapRequestPayload(acceptFormat: 'json')] RoundResultInput $input,
        #[CurrentUser] ?Player $player = null,
    ): JsonResponse {
        return $this->storeResult($request, $player, Game::Dictee, Dictee::storedLevel(...$this->find($group, $level)), $input);
    }

    /**
     * The next level of the same groep, recommended after passing this one; none after the last.
     *
     * @return array{url: string, title: TranslatableMessage}|null
     */
    private function next(DicteeGroup $group, DicteeLevel $level): ?array
    {
        $next = $level->next();
        if (null === $next) {
            return null;
        }

        return [
            'url' => $this->generateUrl('app_game_taal_dictee_spel', ['group' => $group->slug(), 'level' => $next->slug()]),
            'title' => new TranslatableMessage('game.taal.dictee.title', [
                '%group%' => new TranslatableMessage('game.taal.dictee.groups.'.$group->key()),
                '%level%' => new TranslatableMessage('game.taal.dictee.levels.'.$next->key().'.name'),
            ]),
        ];
    }

    /**
     * @return array{DicteeGroup, DicteeLevel}
     */
    private function find(string $group, string $level): array
    {
        return [
            DicteeGroup::fromSlug($group) ?? throw $this->createNotFoundException(),
            DicteeLevel::fromSlug($level) ?? throw $this->createNotFoundException(),
        ];
    }
}
