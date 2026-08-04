<?php

namespace App\Controller;

use App\Entity\CourseCategory;
use App\Entity\CourseImportProfile;
use App\Form\CourseCategoryType;
use App\Form\CourseImportProfileType;
use App\Repository\BankCategoryRepository;
use App\Repository\CourseAttachmentRepository;
use App\Repository\CourseCategoryRepository;
use App\Repository\CourseImportProfileRepository;
use App\Repository\CourseReceiptRepository;
use App\Service\Course\CourseAttachmentManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Fonctionnalites secondaires du module Courses : categories, profils d'import CSV/XLSX et
 * telechargement des pieces jointes de tickets. Meme decoupage que App\Controller\BankFeatureController
 * (garder App\Controller\CourseController focalise sur les tickets/magasins/produits/listes de courses).
 */
#[Route('/courses', name: 'app_courses_')]
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class CourseFeatureController extends AbstractController
{
    #[Route('/categories', name: 'categories', methods: ['GET'])]
    public function categories(CourseCategoryRepository $categoryRepository): Response
    {
        return $this->render('courses/categories.html.twig', [
            'categories' => $categoryRepository->findActiveForUser($this->getUser()),
        ]);
    }

    #[Route('/categories/new', name: 'category_new', methods: ['GET', 'POST'])]
    public function categoryNew(Request $request, BankCategoryRepository $bankCategoryRepository, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CourseCategoryType::class, (new CourseCategory())->setColor('#ea580c')->setIcon('shopping-basket'), [
            'bank_category_choices' => $this->bankCategoryChoices($bankCategoryRepository),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $category = $form->getData();
            $category->setUser($this->getUser());
            $entityManager->persist($category);
            $entityManager->flush();
            $this->addFlash('success', 'La categorie a ete creee.');

            return $this->redirectToRoute('app_courses_categories');
        }

        return $this->render('courses/category_form.html.twig', ['title' => 'Nouvelle categorie', 'form' => $form]);
    }

    #[Route('/categories/{publicId}/edit', name: 'category_edit', methods: ['GET', 'POST'])]
    public function categoryEdit(string $publicId, Request $request, CourseCategoryRepository $categoryRepository, BankCategoryRepository $bankCategoryRepository, EntityManagerInterface $entityManager): Response
    {
        $category = $this->categoryOr404($publicId, $categoryRepository);
        $form = $this->createForm(CourseCategoryType::class, $category, [
            'bank_category_choices' => $this->bankCategoryChoices($bankCategoryRepository),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'La categorie a ete modifiee.');

            return $this->redirectToRoute('app_courses_categories');
        }

        return $this->render('courses/category_form.html.twig', ['title' => 'Modifier la categorie', 'form' => $form]);
    }

    #[Route('/categories/{publicId}/archive', name: 'category_archive', methods: ['POST'])]
    public function categoryArchive(string $publicId, Request $request, CourseCategoryRepository $categoryRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('courses_category_archive_' . $publicId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $this->categoryOr404($publicId, $categoryRepository)->archive();
        $entityManager->flush();
        $this->addFlash('warning', 'La categorie a ete archivee.');

        return $this->redirectToRoute('app_courses_categories');
    }

    #[Route('/import-profiles', name: 'import_profiles', methods: ['GET'])]
    public function importProfiles(CourseImportProfileRepository $profileRepository): Response
    {
        return $this->render('courses/import_profiles.html.twig', [
            'profiles' => $profileRepository->findAllForUser($this->getUser()),
        ]);
    }

    #[Route('/import-profiles/new', name: 'import_profile_new', methods: ['GET', 'POST'])]
    public function importProfileNew(Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CourseImportProfileType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $mapping = array_filter([
                'label' => $data['mappingLabel'] ?? '',
                'quantity' => $data['mappingQuantity'] ?? '',
                'unit' => $data['mappingUnit'] ?? '',
                'unitPrice' => $data['mappingUnitPrice'] ?? '',
                'total' => $data['mappingTotal'] ?? '',
            ], static fn (string $v) => $v !== '');

            $profile = (new CourseImportProfile())
                ->setUser($this->getUser())
                ->setName((string) $data['name'])
                ->setDelimiter((string) ($data['delimiter'] ?? ';'))
                ->setMapping($mapping);

            $entityManager->persist($profile);
            $entityManager->flush();
            $this->addFlash('success', 'Le profil d\'import a ete cree.');

            return $this->redirectToRoute('app_courses_import_profiles');
        }

        return $this->render('courses/import_profile_form.html.twig', ['form' => $form]);
    }

    #[Route('/import-profiles/{publicId}/delete', name: 'import_profile_delete', methods: ['POST'])]
    public function importProfileDelete(string $publicId, Request $request, CourseImportProfileRepository $profileRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('courses_import_profile_delete_' . $publicId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $profile = $profileRepository->findOneForUserByPublicId($this->getUser(), $publicId);
        if ($profile === null) {
            throw $this->createNotFoundException();
        }

        $entityManager->remove($profile);
        $entityManager->flush();
        $this->addFlash('warning', 'Le profil d\'import a ete supprime.');

        return $this->redirectToRoute('app_courses_import_profiles');
    }

    #[Route('/receipts/{receiptPublicId}/attachments/{publicId}/download', name: 'attachment_download', methods: ['GET'])]
    public function attachmentDownload(string $receiptPublicId, string $publicId, CourseAttachmentRepository $attachmentRepository, CourseReceiptRepository $receiptRepository, CourseAttachmentManager $attachmentManager): Response
    {
        $receipt = $receiptRepository->findOneForUserByPublicId($this->getUser(), $receiptPublicId);
        $attachment = $attachmentRepository->findOneForUserByPublicId($this->getUser(), $publicId);
        if ($receipt === null || $attachment === null || $attachment->getReceipt() !== $receipt || $attachment->isArchived()) {
            throw $this->createNotFoundException();
        }

        $filename = $attachmentManager->originalName($attachment);
        $response = new Response($attachment->getContent());
        $response->headers->set('Content-Type', $attachment->getMimeType());
        $response->headers->set('Content-Length', (string) $attachment->getSizeBytes());
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename !== '' ? $filename : 'ticket',
            $this->asciiFilenameFallback($filename !== '' ? $filename : 'ticket'),
        ));

        return $response;
    }

    private function categoryOr404(string $publicId, CourseCategoryRepository $repository): CourseCategory
    {
        $category = $repository->findOneForUserByPublicId($this->getUser(), $publicId);
        if (!$category instanceof CourseCategory) {
            throw $this->createNotFoundException();
        }

        return $category;
    }

    private function bankCategoryChoices(BankCategoryRepository $bankCategoryRepository): array
    {
        $choices = [];
        foreach ($bankCategoryRepository->findActiveForUser($this->getUser()) as $category) {
            $choices[$category->getName()] = $category;
        }

        return $choices;
    }

    private function asciiFilenameFallback(string $filename): string
    {
        $fallback = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename);
        $fallback = trim((string) $fallback, '._-');

        return $fallback !== '' ? $fallback : 'telechargement';
    }
}
