<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/', name: 'app_')]
class RegistrationController extends AbstractController
{
    #[Route('register', name: 'register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        Security $security,
        EntityManagerInterface $entityManager,
        EmailVerificationService $verificationService,
    ): Response {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user, [
            'email_required' => $verificationService->isRequired(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $verificationService->assertConfigured();
            } catch (\DomainException $exception) {
                $this->addFlash('error', $exception->getMessage());

                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form,
                ]);
            }

            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            $token = null;
            if ($verificationService->isRequired()) {
                $token = $verificationService->prepareVerification($user);
            } else {
                $user->setEmailVerified(false);
            }

            $entityManager->persist($user);
            $entityManager->flush();

            if ($token !== null) {
                $verificationService->sendVerificationEmail($user, $token);
            }

            return $security->login($user, 'form_login', 'main');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('email/verify/{publicId}/{token}', name: 'verify_email', methods: ['GET'])]
    public function verifyEmail(
        string $publicId,
        string $token,
        UserRepository $userRepository,
        EmailVerificationService $verificationService,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $userRepository->findOneBy(['publicId' => $publicId]);
        if (!$user instanceof User || !$verificationService->verify($user, $token)) {
            $this->addFlash('error', 'Le lien de validation est invalide ou expire.');

            return $this->redirectToRoute('app_login');
        }

        $entityManager->flush();
        $this->addFlash('success', 'Votre email est valide.');

        return $this->redirectToRoute('app_home');
    }

    #[Route('email/verification-required', name: 'verification_required', methods: ['GET'])]
    public function verificationRequired(EmailVerificationService $verificationService): Response
    {
        return $this->render('registration/verification_required.html.twig', [
            'grace_days' => $verificationService->getGraceDays(),
        ]);
    }

    #[Route('email/resend-verification', name: 'resend_verification', methods: ['POST'])]
    public function resendVerification(
        Request $request,
        EntityManagerInterface $entityManager,
        EmailVerificationService $verificationService,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('resend_email_verification', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $verificationService->assertConfigured();
        $token = $verificationService->prepareVerification($user);
        $entityManager->flush();
        $verificationService->sendVerificationEmail($user, $token);

        $this->addFlash('success', 'Un nouvel email de validation a ete envoye.');

        return $this->redirectToRoute('app_verification_required');
    }
}
