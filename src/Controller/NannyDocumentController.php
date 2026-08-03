<?php

namespace App\Controller;

use App\Entity\Nanny;
use App\Entity\NannyDocument;
use App\Repository\NannyDocumentRepository;
use App\Repository\NannyRepository;
use App\Service\NannyDocumentManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/nanny', name: 'app_nanny_')]
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
final class NannyDocumentController extends AbstractController
{
    #[Route('/{publicId}/documents', name: 'documents', methods: ['GET', 'POST'])]
    public function documents(string $publicId, Request $request, NannyRepository $nannies, NannyDocumentRepository $documents, NannyDocumentManager $manager, EntityManagerInterface $entityManager): Response
    {
        $nanny = $this->nanny($publicId, $nannies);
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('nanny_document_add_'.$publicId, (string) $request->request->get('_token'))) { throw $this->createAccessDeniedException(); }
            try {
                $file = $request->files->get('document');
                if (!$file) { throw new \InvalidArgumentException('Sélectionnez un document PDF.'); }
                $expiresAt = trim((string) $request->request->get('expires_at')) !== '' ? new \DateTime((string) $request->request->get('expires_at')) : null;
                $manager->add($this->getUser(), $nanny, $file, (string) $request->request->get('type'), $expiresAt);
                $entityManager->flush(); $this->addFlash('success', 'Document privé chiffré et ajouté.');
                return $this->redirectToRoute('app_nanny_documents', ['publicId' => $publicId]);
            } catch (\Throwable $exception) { $this->addFlash('error', $exception->getMessage()); }
        }
        $rows = array_map(fn (NannyDocument $document): array => ['document' => $document, 'name' => $manager->name($document)], $documents->findActiveFor($this->getUser(), $nanny));
        return $this->render('nanny/documents.html.twig', ['nanny' => $nanny, 'rows' => $rows, 'types' => NannyDocument::TYPES]);
    }

    #[Route('/documents/{documentId}/download', name: 'document_download', methods: ['GET'])]
    public function download(string $documentId, NannyDocumentRepository $documents, NannyDocumentManager $manager): Response
    {
        $document = $documents->findOneForUser($this->getUser(), $documentId); if (!$document) { throw $this->createNotFoundException(); }
        $response = new Response($manager->content($document)); $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $manager->name($document)));
        return $response;
    }

    #[Route('/documents/{documentId}/archive', name: 'document_archive', methods: ['POST'])]
    public function archive(string $documentId, Request $request, NannyDocumentRepository $documents, NannyDocumentManager $manager, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('nanny_document_archive_'.$documentId, (string) $request->request->get('_token'))) { throw $this->createAccessDeniedException(); }
        $document = $documents->findOneForUser($this->getUser(), $documentId); if (!$document) { throw $this->createNotFoundException(); }
        $nannyId = $document->getNanny()?->getPublicId(); $manager->archive($this->getUser(), $document); $entityManager->flush();
        return $this->redirectToRoute('app_nanny_documents', ['publicId' => $nannyId]);
    }

    private function nanny(string $publicId, NannyRepository $repository): Nanny { $nanny = $repository->findOneByPublicId($publicId); if (!$nanny || !$nanny->isAssignedTo($this->getUser())) { throw $this->createNotFoundException(); } return $nanny; }
}
