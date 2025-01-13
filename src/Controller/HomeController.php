<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CareRepository;
use App\Repository\MonthlyPaymentRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/', name: 'app_')]
class HomeController extends AbstractController
{
    #[Route('', name: 'home')]
    public function index(
        MonthlyPaymentRepository $monthlyPaymentRepository,
        CareRepository $careRepository
    ): Response
    {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            $allMontlyPaiements = $monthlyPaymentRepository->findAllExceptCurrentMonth($this->getUser());
            $month = date('Y-m');
            $actualMonth = $monthlyPaymentRepository->findOneBy(['user' => $this->getUser(), 'month' => $month]);
            $nextCare = $careRepository->findNextCare();

            return $this->render('home/index_logged.html.twig', [
                'allMontlyPaiements' => $allMontlyPaiements,
                'actualMonth' => $actualMonth,
                'nextCare' => $nextCare,
            ]);
        }
        return $this->render('home/index.html.twig', [
        ]);
    }

    #[Route('privacy-policy', name: 'privacy_policy')]
    public function privacyPolicy(): Response
    {
        return $this->render('home/privacy_policy.html.twig');
    }

    #[Route('terms-of-service', name: 'terms_of_service')]
    public function termsOfService(): Response
    {
        return $this->render('home/terms_of_service.html.twig');
    }
}
