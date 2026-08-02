<?php

namespace App\Service;

use App\Entity\BankTransaction;
use App\Entity\User;
use App\Repository\BankTransactionRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Twig\Environment;

final class BankExportService
{
    public function __construct(
        private readonly BankTransactionRepository $transactionRepository,
        private readonly BankTransactionManager $transactionManager,
        private readonly Environment $twig,
    ) {
    }

    public function csv(User $user, \DateTimeInterface $from, \DateTimeInterface $to): StreamedResponse
    {
        $transactions = $this->transactionRepository->findForPeriod($user, $from, $to);
        $filename = sprintf('bank-%s-%s.csv', $from->format('Ymd'), $to->format('Ymd'));

        $response = new StreamedResponse(function () use ($transactions): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Date', 'Libelle', 'Categorie', 'Montant', 'Statut', 'Verification', 'Source'], ';');
            foreach ($transactions as $transaction) {
                fputcsv($handle, $this->row($transaction), ';');
            }
            fclose($handle);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename));

        return $response;
    }

    public function xlsx(User $user, \DateTimeInterface $from, \DateTimeInterface $to): Response
    {
        $transactions = $this->transactionRepository->findForPeriod($user, $from, $to);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Date', 'Libelle', 'Categorie', 'Montant', 'Statut', 'Verification', 'Source'], null, 'A1');

        $line = 2;
        foreach ($transactions as $transaction) {
            $sheet->fromArray($this->row($transaction), null, 'A' . $line);
            ++$line;
        }

        $response = new StreamedResponse(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        });
        $filename = sprintf('bank-%s-%s.xlsx', $from->format('Ymd'), $to->format('Ymd'));
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename));

        return $response;
    }

    public function pdf(User $user, \DateTimeInterface $from, \DateTimeInterface $to): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->twig->render('bank/export_pdf.html.twig', [
            'transactions' => $this->transactionRepository->findForPeriod($user, $from, $to),
            'from' => $from,
            'to' => $to,
            'transactionManager' => $this->transactionManager,
        ]));
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }

    /** @return list<string|float> */
    private function row(BankTransaction $transaction): array
    {
        return [
            $transaction->getDate()->format('Y-m-d'),
            $this->transactionManager->label($transaction),
            $transaction->getCategory()?->getName() ?? '',
            number_format($transaction->getAmount(), 2, '.', ''),
            $transaction->getStatus(),
            $transaction->isVerified() ? 'verifiee' : 'non_verifiee',
            $transaction->getSource(),
        ];
    }
}
