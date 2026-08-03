<?php

namespace App\Controller;

use App\Entity\Nanny;
use App\Repository\NannyRepository;
use App\Service\ApplicationSettings;
use App\Service\CareStatusSynchronizer;
use App\Service\KidCareReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reports/kidcare', name: 'app_kidcare_report')]
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
final class KidCareReportController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function index(Request $request, NannyRepository $nannies, KidCareReportService $reports, CareStatusSynchronizer $statuses, ApplicationSettings $settings): Response
    {
        [$from,$to,$nanny] = $this->filters($request,$nannies); $statuses->synchronize($this->getUser(),$nanny); return $this->render('report/kidcare.html.twig',['report'=>$reports->build($this->getUser(),$from,$to,$nanny),'from'=>$from,'to'=>$to,'selected_nanny'=>$nanny,'nannies'=>$nannies->findAssignedToUser($this->getUser()),'currency'=>$settings->currency()]);
    }
    #[Route('/export/{format}', name: '_export', requirements: ['format'=>'csv|xlsx|pdf'], methods: ['GET'])]
    public function export(string $format, Request $request, NannyRepository $nannies, KidCareReportService $reports): Response
    {
        [$from,$to,$nanny]=$this->filters($request,$nannies); $report=$reports->build($this->getUser(),$from,$to,$nanny); $filename='kidcare-'.$from->format('Ymd').'-'.$to->format('Ymd');
        if ($format==='csv') return $reports->csv($report,$filename); if ($format==='xlsx') return $reports->xlsx($report,$filename);
        $response=new Response($reports->pdf($report,$from,$to,$nanny)); $response->headers->set('Content-Type','application/pdf'); $response->headers->set('Content-Disposition',$response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT,$filename.'.pdf')); return $response;
    }
    /** @return array{\DateTimeImmutable, \DateTimeImmutable, Nanny|null} */
    private function filters(Request $request,NannyRepository $nannies): array { $from=\DateTimeImmutable::createFromFormat('!Y-m-d',(string)$request->query->get('from')) ?: new \DateTimeImmutable('first day of january'); $to=\DateTimeImmutable::createFromFormat('!Y-m-d',(string)$request->query->get('to')) ?: new \DateTimeImmutable('today'); if($to<$from){[$from,$to]=[$to,$from];} $nanny=null; $id=(string)$request->query->get('nanny'); if($id!==''){ $candidate=$nannies->findOneByPublicId($id); if($candidate?->isAssignedTo($this->getUser())){$nanny=$candidate;} } return [$from,$to,$nanny]; }
}
