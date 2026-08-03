<?php

namespace App\Service;

use App\Entity\Nanny;
use App\Entity\NannyAssignment;
use App\Entity\User;
use App\Repository\CareRepository;
use App\Repository\NannyAssignmentRepository;
use App\Repository\NannyRepository;
use Doctrine\ORM\EntityManagerInterface;

final class NannyManager
{
    public function __construct(
        private readonly NannyRepository $nannyRepository,
        private readonly NannyAssignmentRepository $assignmentRepository,
        private readonly CareRepository $careRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityLogger $activityLogger,
        private readonly NannyOwnershipService $ownershipService,
    ) {
    }

    public function createAndAttach(User $user, Nanny $nanny): Nanny
    {
        if ($this->nannyRepository->findDuplicateIdentity($nanny) !== null) {
            throw new \DomainException('Une nounou avec la meme identite et adresse existe deja.');
        }

        $nanny->setCreatedBy($user);
        $this->entityManager->persist($nanny);
        $this->attach($user, $nanny);
        $this->activityLogger->record($user, 'nanny.create', 'Nounou creee.', [
            'nanny' => $nanny->getFullName(),
        ]);

        return $nanny;
    }

    public function attach(User $user, Nanny $nanny): NannyAssignment
    {
        $existing = $this->assignmentRepository->findOneBy(['user' => $user, 'nanny' => $nanny]);
        if ($existing !== null) {
            if ($existing->getDetachedAt() !== null) {
                throw new \DomainException('Ce rattachement est archivé et ne peut pas être restauré.');
            }

            return $existing;
        }

        $assignment = new NannyAssignment();
        $assignment->setUser($user);
        $assignment->setNanny($nanny);
        $this->entityManager->persist($assignment);
        $this->activityLogger->record($user, 'nanny.attach', 'Nounou rattachee.', [
            'nanny' => $nanny->getFullName(),
        ]);

        return $assignment;
    }

    public function detach(User $user, Nanny $nanny): void
    {
        if ($this->careRepository->hasFutureOrUnpaidCare($user, $nanny)) {
            throw new \DomainException('Detachement bloque : des gardes futures ou non payees existent encore.');
        }

        $assignment = $this->assignmentRepository->findActive($user, $nanny);
        if ($assignment === null) {
            return;
        }

        foreach ($nanny->getCareTemplates() as $template) {
            if ($template->getUser() === $user && !$template->isArchived()) {
                $template->archive();
            }
        }

        foreach ($nanny->getRates() as $rate) {
            if ($rate->getUser() === $user) {
                $rate->setNannySnapshotKey($nanny->getPublicId());
                $rate->setNanny(null);
            }
        }

        foreach ($nanny->getCares() as $care) {
            if ($care->getUser() === $user) {
                $care->setNannyDisplayName($nanny->getFullName());
                $care->setNannySnapshotKey($nanny->getPublicId());
                $care->setNanny(null);
            }
        }

        foreach ($nanny->getMonthlyPayments() as $payment) {
            if ($payment->getUser() === $user) {
                $payment->setNannyDisplayName($nanny->getFullName());
                $payment->setNannySnapshotKey($nanny->getPublicId());
                $payment->setNanny(null);
            }
        }

        $this->activityLogger->record($user, 'nanny.detach', 'Nounou detachee.', [
            'nanny' => $nanny->getFullName(),
        ]);
        $assignment->setDetachedAt(new \DateTime());
        if ($nanny->getCreatedBy() === $user) {
            $this->ownershipService->transferBeforeAccountDeletion($nanny, $user);
        }
    }
}
