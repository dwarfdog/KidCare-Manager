<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordType;
use App\Form\DeleteAccountType;
use App\Form\ProfileType;
use App\Service\ActivityLogger;
use App\Service\EmailVerificationService;
use App\Service\UserAccountManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/account', name: 'app_account_')]
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class AccountController extends AbstractController
{
    #[Route('', name: 'profile', methods: ['GET', 'POST'])]
    public function profile(
        Request $request,
        EntityManagerInterface $entityManager,
        EmailVerificationService $verificationService,
        ActivityLogger $activityLogger,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $previousEmail = $user->getEmail();
        $form = $this->createForm(ProfileType::class, $user, [
            'email_required' => $verificationService->isRequired(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $token = null;
            if ($verificationService->isRequired() && $previousEmail !== $user->getEmail()) {
                try {
                    $verificationService->assertConfigured();
                } catch (\DomainException $exception) {
                    $form->get('email')->addError(new FormError($exception->getMessage()));

                    return $this->renderAccount($verificationService->isRequired(), $form->createView());
                }
                $user->setEmailVerified(false);
                $token = $verificationService->prepareVerification($user);
            }

            $entityManager->flush();
            if ($token !== null) {
                $verificationService->sendVerificationEmail($user, $token);
            }
            $activityLogger->record($user, 'account.profile_update', 'Profil utilisateur modifie.');
            $entityManager->flush();
            $this->addFlash('success', 'Profil mis a jour.');

            return $this->redirectToRoute('app_account_profile');
        }

        return $this->renderAccount($verificationService->isRequired(), $form->createView());
    }

    #[Route('/password', name: 'password', methods: ['POST'])]
    public function password(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger,
        EmailVerificationService $verificationService,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ChangePasswordType::class, null, [
            'action' => $this->generateUrl('app_account_password'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$passwordHasher->isPasswordValid($user, (string) $form->get('currentPassword')->getData())) {
                $form->get('currentPassword')->addError(new FormError('Mot de passe actuel incorrect.'));

                return $this->renderAccount($verificationService->isRequired(), null, $form->createView());
            }

            $user->setPassword($passwordHasher->hashPassword($user, (string) $form->get('newPassword')->getData()));
            $activityLogger->record($user, 'account.password_update', 'Mot de passe utilisateur modifie.');
            $entityManager->flush();
            $this->addFlash('success', 'Mot de passe mis a jour.');
        }

        return $this->redirectToRoute('app_account_profile');
    }

    #[Route('/delete', name: 'delete', methods: ['POST'])]
    public function delete(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        UserAccountManager $accountManager,
        TokenStorageInterface $tokenStorage,
        EmailVerificationService $verificationService,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(DeleteAccountType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderAccount($verificationService->isRequired(), null, null, $form->createView());
        }

        if (!$passwordHasher->isPasswordValid($user, (string) $form->get('currentPassword')->getData())) {
            $form->get('currentPassword')->addError(new FormError('Mot de passe actuel incorrect.'));

            return $this->renderAccount($verificationService->isRequired(), null, null, $form->createView());
        }

        $accountManager->deleteAccount($user);
        $tokenStorage->setToken(null);
        $request->getSession()->invalidate();
        $this->addFlash('success', 'Votre compte a ete supprime.');

        return $this->redirectToRoute('app_home');
    }

    private function renderAccount(bool $emailRequired, mixed $profileForm = null, mixed $passwordForm = null, mixed $deleteForm = null): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('account/profile.html.twig', [
            'profileForm' => $profileForm ?? $this->createForm(ProfileType::class, $user, ['email_required' => $emailRequired])->createView(),
            'passwordForm' => $passwordForm ?? $this->createForm(ChangePasswordType::class, null, [
                'action' => $this->generateUrl('app_account_password'),
            ])->createView(),
            'deleteForm' => $deleteForm ?? $this->createForm(DeleteAccountType::class, null, [
                'action' => $this->generateUrl('app_account_delete'),
            ])->createView(),
            'emailRequired' => $emailRequired,
        ]);
    }
}
