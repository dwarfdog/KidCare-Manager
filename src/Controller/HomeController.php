<?php

namespace App\Controller;

use App\Service\HomeDashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/', name: 'app_')]
class HomeController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(HomeDashboardService $dashboard): Response
    {
        if ($this->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return $this->render('home/index_logged.html.twig', $dashboard->build($this->getUser()));
        }

        return $this->render('home/index.html.twig');
    }

    #[Route('privacy-policy', name: 'privacy_policy', methods: ['GET'])]
    public function privacyPolicy(): Response
    {
        return $this->render('home/privacy_policy.html.twig');
    }

    #[Route('terms-of-service', name: 'terms_of_service', methods: ['GET'])]
    public function termsOfService(): Response
    {
        return $this->render('home/terms_of_service.html.twig');
    }
}
