<?php

namespace App\Controller;

use App\Entity\Care;
use App\Entity\MonthlyPayment;
use App\Repository\CareRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/monthly-payment/', name: 'app_monthly_payment_')]
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class MonthlyPaiementController extends AbstractController
{
    #[Route('mark-as-paid/{slug}', name: 'mark_as_paid', methods: ['GET'])]
    public function markAsPaid(
        MonthlyPayment $payment,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();

        if ($payment->getUser() !== $user) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas accéder à ce paiement');
        }

        // Mettre à jour le statut
        $payment->setPaid(true);
        $payment->setPaidAt(new \DateTime());

        // Sauvegarder en base de données
        $entityManager->flush();

        // Message flash de confirmation
        $this->addFlash('success', 'Le paiement a été marqué comme payé');

        // Rediriger vers le détail du paiement
        return $this->redirectToRoute('app_monthly_payment_show', ['slug' => $payment->getSlug()]);
    }

    #[Route('show/{slug}', name: 'show', methods: ['GET'])]
    public function show(
        MonthlyPayment $payment,
        CareRepository $careRepository
    ): Response {
        $user = $this->getUser();

        if ($payment->getUser() !== $user) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas accéder à ce paiement');
        }

        // Récupérer toutes les gardes du mois associées à ce paiement (même mois, même nounou)
        $cares = $careRepository->createQueryBuilder('c')
            ->where('c.user = :user')
            ->andWhere('c.nanny = :nanny')
            ->andWhere('c.month = :month')
            ->setParameter('user', $user)
            ->setParameter('nanny', $payment->getNanny())
            ->setParameter('month', $payment->getMonth())
            ->orderBy('c.date', 'ASC')
            ->addOrderBy('c.startTime', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('monthly_paiement/show.html.twig', [
            'payment' => $payment,
            'cares' => $cares,
        ]);
    }

    #[Route('mark-as-unpaid/{slug}', name: 'mark_as_unpaid', methods: ['GET'])]
    public function markAsUnpaid(
        MonthlyPayment $payment,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();

        if ($payment->getUser() !== $user) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas accéder à ce paiement');
        }

        // Mettre à jour le statut
        $payment->setPaid(false);
        $payment->setPaidAt(null);

        // Sauvegarder en base de données
        $entityManager->flush();

        // Message flash de confirmation
        $this->addFlash('warning', 'Le paiement a été marqué comme non payé');

        // Rediriger vers le détail du paiement
        return $this->redirectToRoute('app_monthly_payment_show', ['slug' => $payment->getSlug()]);
    }
}
