<?php

namespace App\Service;

use App\Entity\Nanny;
use App\Entity\User;
use App\Repository\CareRepository;
use App\Repository\NannyRateRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Twig\Environment;

final class KidCareReportService
{
    public function __construct(private readonly CareRepository $cares, private readonly NannyRateRepository $rates, private readonly ApplicationSettings $settings, private readonly Environment $twig) {}

    /** @return array{rows: array<int, array<string, mixed>>, summary: array<string, float|int>, byMonth: array<string, array<string, float|int>>} */
    public function build(User $user, \DateTimeInterface $from, \DateTimeInterface $to, ?Nanny $nanny = null): array
    {
        $rows = []; $summary = ['hours' => 0.0, 'meals' => 0, 'amount' => 0.0]; $byMonth = [];
        foreach ($this->cares->findBillableForReport($user, $from, $to, $nanny) as $care) {
            $rate = $care->getNanny() ? $this->rates->findApplicableRate($user, $care->getNanny(), $care->getDate()) : null;
            $amount = $rate ? ($care->getHoursCount() * $rate->getHourlyRate()) + ($care->getMealsCount() * $rate->getMealRate()) : 0.0;
            $row = ['care' => $care, 'nanny' => $care->getNannyDisplayName(), 'amount' => round($amount, 2), 'status' => $care->isRealized() ? 'Réalisée' : 'Non réalisée rémunérée']; $rows[] = $row;
            $summary['hours'] += $care->getHoursCount(); $summary['meals'] += $care->getMealsCount(); $summary['amount'] += $amount;
            $month = (string) $care->getMonth(); $byMonth[$month] ??= ['hours' => 0.0, 'meals' => 0, 'amount' => 0.0];
            $byMonth[$month]['hours'] += $care->getHoursCount(); $byMonth[$month]['meals'] += $care->getMealsCount(); $byMonth[$month]['amount'] += $amount;
        }
        $summary['hours'] = round((float) $summary['hours'], 2); $summary['amount'] = round((float) $summary['amount'], 2);
        return compact('rows', 'summary', 'byMonth');
    }

    public function csv(array $report, string $filename): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($report): void { $h = fopen('php://output', 'w'); fwrite($h, "\xEF\xBB\xBF"); fputcsv($h, ['Date','Nounou','Début','Fin','Heures','Repas','État','Montant'], ';'); foreach ($report['rows'] as $row) { $c=$row['care']; fputcsv($h, [$c->getDate()->format('Y-m-d'),$row['nanny'],$c->getStartTime()->format('H:i'),$c->getEndTime()->format('H:i'),$c->getHoursCount(),$c->getMealsCount(),$row['status'],$row['amount']], ';'); } fclose($h); });
        $response->headers->set('Content-Type','text/csv; charset=UTF-8'); $response->headers->set('Content-Disposition',$response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT,$filename.'.csv')); return $response;
    }

    public function xlsx(array $report, string $filename): StreamedResponse
    {
        $spreadsheet = new Spreadsheet(); $sheet = $spreadsheet->getActiveSheet(); $sheet->fromArray(['Date','Nounou','Début','Fin','Heures','Repas','État','Montant'], null, 'A1'); $line=2;
        foreach ($report['rows'] as $row) { $c=$row['care']; $sheet->fromArray([$c->getDate()->format('Y-m-d'),$row['nanny'],$c->getStartTime()->format('H:i'),$c->getEndTime()->format('H:i'),$c->getHoursCount(),$c->getMealsCount(),$row['status'],$row['amount']], null, 'A'.$line++); }
        $response = new StreamedResponse(static function () use ($spreadsheet): void { (new Xlsx($spreadsheet))->save('php://output'); }); $response->headers->set('Content-Type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); $response->headers->set('Content-Disposition',$response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT,$filename.'.xlsx')); return $response;
    }

    public function pdf(array $report, \DateTimeInterface $from, \DateTimeInterface $to, ?Nanny $nanny): string
    {
        $options = new Options(); $options->set('isRemoteEnabled', false); $options->set('defaultFont','DejaVu Sans'); $pdf = new Dompdf($options);
        $pdf->loadHtml($this->twig->render('report/kidcare_pdf.html.twig', ['report'=>$report,'from'=>$from,'to'=>$to,'nanny'=>$nanny,'currency'=>$this->settings->currency()])); $pdf->setPaper('A4','landscape'); $pdf->render(); return $pdf->output();
    }
}
