<?php

namespace App\Controller;

use App\Repository\CareRepository;
use App\Repository\MonthlyPaymentRepository;
use App\Service\ActivityLogger;
use App\Service\ApplicationSettings;
use App\Service\MonthlyPaymentCalculator;
use App\Service\PaymentExportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/monthly-payment', name: 'app_monthly_payment_')]
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class MonthlyPaiementController extends AbstractController
{
    #[Route('/show/{publicId}', name: 'show', methods: ['GET'])]
    public function show(
        string $publicId,
        MonthlyPaymentRepository $paymentRepository,
        CareRepository $careRepository,
        ApplicationSettings $settings,
    ): Response {
        $payment = $paymentRepository->findOneForUserByPublicId($this->getUser(), $publicId);
        if ($payment === null) {
            throw $this->createNotFoundException();
        }

        return $this->render('monthly_paiement/show.html.twig', [
            'payment' => $payment,
            'cares' => $careRepository->findForPayment($this->getUser(), $payment->getNanny(), $payment->getMonth(), $payment->getNannySnapshotKey()),
            'currency' => $settings->currency(),
        ]);
    }

    #[Route('/export/{publicId}/csv', name: 'export_csv', methods: ['GET'])]
    public function exportCsv(
        string $publicId,
        MonthlyPaymentRepository $paymentRepository,
        CareRepository $careRepository,
        PaymentExportService $exportService,
        ActivityLogger $activityLogger,
        EntityManagerInterface $entityManager,
    ): Response {
        $payment = $paymentRepository->findOneForUserByPublicId($this->getUser(), $publicId);
        if ($payment === null) {
            throw $this->createNotFoundException();
        }

        if (!$payment->isPaid()) {
            $this->addFlash('error', 'Export indisponible tant que le paiement n\'est pas marque comme paye.');

            return $this->redirectToRoute('app_monthly_payment_show', ['publicId' => $payment->getPublicId()]);
        }

        $cares = $careRepository->findForPayment($this->getUser(), $payment->getNanny(), $payment->getMonth(), $payment->getNannySnapshotKey());
        $activityLogger->record($this->getUser(), 'payment.export_csv', 'Export CSV paiement.', [
            'payment' => $payment->getPublicId(),
            'month' => $payment->getMonth(),
        ]);
        $entityManager->flush();

        return $exportService->csv($payment, $cares);
    }

    #[Route('/export/{publicId}/pdf', name: 'export_pdf', methods: ['GET'])]
    public function exportPdf(
        string $publicId,
        MonthlyPaymentRepository $paymentRepository,
        CareRepository $careRepository,
        PaymentExportService $exportService,
        ActivityLogger $activityLogger,
        EntityManagerInterface $entityManager,
    ): Response {
        $payment = $paymentRepository->findOneForUserByPublicId($this->getUser(), $publicId);
        if ($payment === null) {
            throw $this->createNotFoundException();
        }

        if (!$payment->isPaid()) {
            $this->addFlash('error', 'Export indisponible tant que le paiement n\'est pas marque comme paye.');

            return $this->redirectToRoute('app_monthly_payment_show', ['publicId' => $payment->getPublicId()]);
        }

        $cares = $careRepository->findForPayment($this->getUser(), $payment->getNanny(), $payment->getMonth(), $payment->getNannySnapshotKey());
        $activityLogger->record($this->getUser(), 'payment.export_pdf', 'Export PDF paiement.', [
            'payment' => $payment->getPublicId(),
            'month' => $payment->getMonth(),
        ]);
        $entityManager->flush();

        return new Response($exportService->pdf($payment, $cares), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="paiement-%s-%s.pdf"', $payment->getMonth(), $payment->getPublicId()),
        ]);
    }

    #[Route('/mark-as-paid/{publicId}', name: 'mark_as_paid', methods: ['POST'])]
    public function markAsPaid(
        string $publicId,
        Request $request,
        MonthlyPaymentRepository $paymentRepository,
        MonthlyPaymentCalculator $calculator,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger,
    ): Response {
        if (!$this->isCsrfTokenValid('payment_paid_' . $publicId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $payment = $paymentRepository->findOneForUserByPublicId($this->getUser(), $publicId);
        if ($payment === null) {
            throw $this->createNotFoundException();
        }

        $calculator->recalculate($this->getUser(), $payment->getNanny(), $payment->getMonth(), true, $payment->getNannySnapshotKey());
        $payment->setPaid(true);
        $activityLogger->record($this->getUser(), 'payment.mark_paid', 'Paiement marque paye.', [
            'payment' => $payment->getPublicId(),
            'month' => $payment->getMonth(),
        ]);
        $entityManager->flush();
        $this->addFlash('success', 'Le paiement a ete marque comme paye.');

        return $this->redirectToRoute('app_monthly_payment_show', ['publicId' => $payment->getPublicId()]);
    }

    #[Route('/mark-as-unpaid/{publicId}', name: 'mark_as_unpaid', methods: ['POST'])]
    public function markAsUnpaid(
        string $publicId,
        Request $request,
        MonthlyPaymentRepository $paymentRepository,
        MonthlyPaymentCalculator $calculator,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger,
    ): Response {
        if (!$this->isCsrfTokenValid('payment_unpaid_' . $publicId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $payment = $paymentRepository->findOneForUserByPublicId($this->getUser(), $publicId);
        if ($payment === null) {
            throw $this->createNotFoundException();
        }

        $payment->setPaid(false);
        $calculator->recalculate($this->getUser(), $payment->getNanny(), $payment->getMonth(), true, $payment->getNannySnapshotKey());
        $activityLogger->record($this->getUser(), 'payment.mark_unpaid', 'Paiement remis en attente.', [
            'payment' => $payment->getPublicId(),
            'month' => $payment->getMonth(),
        ]);
        $entityManager->flush();
        $this->addFlash('warning', 'Le paiement a ete remis en attente et recalcule.');

        return $this->redirectToRoute('app_monthly_payment_show', ['publicId' => $payment->getPublicId()]);
    }
}
