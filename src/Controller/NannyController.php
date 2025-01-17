<?php
namespace App\Controller;

use App\Entity\Nanny;
use App\Form\NannyType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/nanny/', name: 'app_nanny_')]
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class NannyController extends AbstractController
{
    #[Route('index', name: 'index')]
    public function index(): Response
    {
        $user = $this->getUser();
        $nannies = $user->getNannies();

        if ($nannies->isEmpty()) {
            $this->addFlash('warning', 'Vous n\'avez pas encore ajouté de nounou.');
        }

        return $this->render('nanny/index.html.twig', [
            'nannies' => $nannies,
        ]);
    }

    #[Route('new', name: 'new')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $nanny = new Nanny();
        $form = $this->createForm(NannyType::class, $nanny);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $nanny->addUser($this->getUser());

            $entityManager->persist($nanny);
            $entityManager->flush();

            $this->addFlash('success', 'La nounou a été ajoutée avec succès.');

            return $this->redirectToRoute('app_nanny_index');
        }

        return $this->render('nanny/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('edit/{slug}', name: 'edit')]
    public function edit(Request $request, Nanny $nanny, EntityManagerInterface $entityManager): Response
    {
    // Vérifier si l'utilisateur actuel a accès à cette nounou
    if (!$nanny->getUsers()->contains($this->getUser())) {
        $this->addFlash('danger', 'Vous n\'avez pas accès à cette nounou.');
        return $this->redirectToRoute('app_nanny_index');
    }

    $form = $this->createForm(NannyType::class, $nanny);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $entityManager->flush();
        $this->addFlash('success', 'Les informations de la nounou ont été mises à jour avec succès.');
        return $this->redirectToRoute('app_nanny_index');
    }

    return $this->render('nanny/edit.html.twig', [
        'nanny' => $nanny,
        'form' => $form->createView(),
    ]);
    }
}