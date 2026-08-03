<?php

namespace App\Service;

use App\Entity\Care;
use App\Entity\MonthlyPayment;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Twig\Environment;

final class PaymentExportService
{
    public function __construct(
        private readonly Environment $twig,
        private readonly ApplicationSettings $settings,
    ) {}

    /** @param Care[] $cares */
    public function csv(MonthlyPayment $payment, array $cares): StreamedResponse
    {
        $filename = sprintf('paiement-%s-%s.csv', $payment->getMonth(), $payment->getPublicId());
        $response = new StreamedResponse(function () use ($payment, $cares): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Mois', $payment->getMonth()], ';');
            fputcsv($handle, ['Nounou', $payment->getNannyDisplayName()], ';');
            fputcsv($handle, ['Total', number_format($payment->getTotalAmount(), 2, '.', ''), $this->settings->currency()], ';');
            fputcsv($handle, [], ';');
            fputcsv($handle, ['Date', 'Debut', 'Fin', 'Heures', 'Repas'], ';');

            foreach ($cares as $care) {
                fputcsv($handle, [
                    $care->getDate()->format('Y-m-d'),
                    $care->getStartTime()->format('H:i'),
                    $care->getEndTime()->format('H:i'),
                    number_format($care->getHoursCount(), 2, '.', ''),
                    $care->getMealsCount(),
                ], ';');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename));

        return $response;
    }

    /** @param Care[] $cares */
    public function pdf(MonthlyPayment $payment, array $cares): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->twig->render('monthly_paiement/export_pdf.html.twig', [
            'payment' => $payment,
            'cares' => $cares,
            'currency' => $this->settings->currency(),
        ]));
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }
}
