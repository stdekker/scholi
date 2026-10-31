<?php

declare(strict_types=1);

namespace App\Controller\Game;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Rekenen: the games of this subject. Each game has its own controller (TafelsController,
 * ErbijErafController).
 */
final class RekenenController extends AbstractController
{
    #[Route('/rekenen', name: 'app_game_rekenen', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('game/rekenen/index.html.twig');
    }
}
