<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Player;
use App\Game\PracticeTimeInput;
use App\Player\GuestProgress;
use App\Player\PracticeTime;
use App\Repository\PracticeDayRepository;
use App\Security\GuestProgressCookie;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Practice time: every game posts the seconds of a finished round here (see PracticeTime).
 */
final class PracticeController extends AbstractController
{
    /**
     * Adds the seconds to today: in the database for a player, in the signed guest cookie for a
     * guest. Answers with the whole minutes practised in the window, this round included.
     * JSON only: together with the SameSite=Lax cookies this protects it against cross-site posts.
     */
    #[Route('/oefentijd', name: 'app_practice', methods: ['POST'], format: 'json')]
    public function record(
        Request $request,
        #[MapRequestPayload(acceptFormat: 'json')] PracticeTimeInput $input,
        PracticeDayRepository $practiceDays,
        GuestProgressCookie $guestCookie,
        #[CurrentUser] ?Player $player = null,
    ): JsonResponse {
        if (null !== $player) {
            $practiceDays->addSeconds($player, $input->seconds);

            return $this->json(['minutes' => PracticeTime::minutes($practiceDays->secondsInWindow($player))], Response::HTTP_CREATED);
        }

        $windowStart = PracticeTime::windowStart()->format('Y-m-d');
        // A tampered or outdated cookie is simply replaced.
        $progress = ($guestCookie->read($request) ?? new GuestProgress())
            ->withPractice(PracticeTime::today()->format('Y-m-d'), $input->seconds, $windowStart);

        $response = $this->json(['minutes' => PracticeTime::minutes($progress->practiceSeconds($windowStart))]);
        $response->headers->setCookie($guestCookie->create($request, $progress));

        return $response;
    }
}
