<?php

declare(strict_types=1);

namespace App\Controller\Game;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RekenenController extends AbstractController
{
    private const int MIN_TABLE = 2;
    private const int MAX_TABLE = 10;

    #[Route('/rekenen', name: 'app_game_rekenen', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('game/rekenen/index.html.twig', [
            'tables' => range(self::MIN_TABLE, self::MAX_TABLE),
        ]);
    }

    #[Route('/rekenen/tafel/{table}', name: 'app_game_rekenen_tafel', requirements: ['table' => '[2-9]|10'], methods: ['GET'])]
    public function tafel(int $table): Response
    {
        return $this->render('game/rekenen/tafel.html.twig', [
            'table' => $table,
        ]);
    }
}
