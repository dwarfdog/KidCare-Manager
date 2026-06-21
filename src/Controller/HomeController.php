<?php

namespace App\Controller;

use App\Repository\CareRepository;
use App\Repository\MonthlyPaymentRepository;
use App\Repository\NannyRepository;
use App\Service\ApplicationSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/', name: 'app_')]
class HomeController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(
        MonthlyPaymentRepository $monthlyPaymentRepository,
        CareRepository $careRepository,
        NannyRepository $nannyRepository,
        ApplicationSettings $settings,
    ): Response {
        if ($this->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            $user = $this->getUser();

            return $this->render('home/index_logged.html.twig', [
                'previousPayments' => $monthlyPaymentRepository->findAllExceptCurrentMonth($user),
                'currentPayments' => $monthlyPaymentRepository->findCurrentMonth($user),
                'nextCare' => $careRepository->findNextCare($user),
                'nannies' => $nannyRepository->findAssignedToUser($user),
                'currency' => $settings->currency(),
            ]);
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
