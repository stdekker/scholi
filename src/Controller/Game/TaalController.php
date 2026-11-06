<?php

declare(strict_types=1);

namespace App\Controller\Game;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Taal: the games of this subject. Each game has its own controller (DicteeController).
 */
final class TaalController extends AbstractController
{
    #[Route('/taal', name: 'app_game_taal', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('game/taal/index.html.twig');
    }
}
