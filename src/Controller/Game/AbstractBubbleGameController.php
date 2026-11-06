<?php

declare(strict_types=1);

namespace App\Controller\Game;

use App\Entity\GameResult;
use App\Entity\Player;
use App\Game\Game;
use App\Game\LevelProgress;
use App\Game\NextLevelLink;
use App\Game\RoundResultInput;
use App\Game\RoundTime;
use App\Player\GuestProgress;
use App\Repository\GameResultRepository;
use App\Security\GuestProgressCookie;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * What the bubble games' controllers share: reading progress, storing a finished round and the
 * variables of templates/game/bubble_game.html.twig.
 */
abstract class AbstractBubbleGameController extends AbstractController
{
    public function __construct(
        private readonly GuestProgressCookie $guestCookie,
        private readonly GameResultRepository $results,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * The best result per level: from the database for a player, from the signed cookie for a
     * guest (empty, and flagged for clearing, when that cookie is not valid).
     *
     * @return array{array<int, LevelProgress>, bool} progress keyed by level, and whether the guest cookie is invalid
     */
    protected function progress(Request $request, ?Player $player, Game $game): array
    {
        if (null !== $player) {
            return [$this->results->progressFor($player, $game), false];
        }

        $guestProgress = $this->guestCookie->read($request);

        return [$guestProgress?->progress($game) ?? [], null === $guestProgress];
    }

    /**
     * Renders a picker page with the player's (or guest's) progress as `progress`, and clears the
     * guest cookie when it is not valid.
     *
     * @param array<string, mixed>|\Closure(array<int, LevelProgress>): array<string, mixed> $vars template variables, or a closure that makes them from the progress
     */
    protected function renderPicker(Request $request, ?Player $player, Game $game, string $template, array|\Closure $vars = []): Response
    {
        [$progress, $invalidCookie] = $this->progress($request, $player, $game);
        $response = $this->render($template, ['progress' => $progress] + ($vars instanceof \Closure ? $vars($progress) : $vars));

        if ($invalidCookie) {
            $response->headers->setCookie($this->guestCookie->clear($request));
        }

        return $response;
    }

    /**
     * Stores a completed round: in the database for a player, in the signed guest cookie for a
     * guest. Routes calling this accept JSON only (#[MapRequestPayload(acceptFormat: 'json')]):
     * together with the SameSite=Lax cookies this protects them against cross-site posts.
     */
    protected function storeResult(Request $request, ?Player $player, Game $game, int $level, RoundResultInput $input): JsonResponse
    {
        if (null === $player) {
            // A tampered or outdated cookie is simply replaced.
            $progress = ($this->guestCookie->read($request) ?? new GuestProgress())
                ->withResult($game, $level, $input->durationMs, new \DateTimeImmutable());

            $response = $this->json(['passed' => $game->isPassed($level, $input->durationMs)]);
            $response->headers->setCookie($this->guestCookie->create($request, $progress));

            return $response;
        }

        $result = new GameResult($player, $game, $level, $input->durationMs, new \DateTimeImmutable());
        $this->entityManager->persist($result);
        $this->entityManager->flush();

        return $this->json(['passed' => $result->isPassed()], Response::HTTP_CREATED);
    }

    /**
     * Variables the bubble game layout needs besides the game's own texts and values.
     *
     * @param string         $saveUrl '' when the round is not saved
     * @param ?NextLevelLink $next    the level recommended after passing
     *
     * @return array{time_limit_ms: int, time_limit: string, within_key: string, save_url: string, back_url: string, next: ?NextLevelLink}
     */
    protected function bubbleGame(int $timeLimitMs, string $saveUrl, string $backUrl, ?NextLevelLink $next = null): array
    {
        return [
            'time_limit_ms' => $timeLimitMs,
            'time_limit' => RoundTime::format($timeLimitMs),
            'within_key' => RoundTime::withinKey($timeLimitMs),
            'save_url' => $saveUrl,
            'back_url' => $backUrl,
            'next' => $next,
        ];
    }
}
