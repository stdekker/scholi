<?php

declare(strict_types=1);

namespace App\Controller\Game;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RekenenController extends AbstractController
{
    #[Route('/rekenen', name: 'app_game_rekenen', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('game/rekenen/index.html.twig');
    }
}
