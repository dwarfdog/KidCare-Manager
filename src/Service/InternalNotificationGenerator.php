<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserNotification;
use App\Repository\CareAbsenceRepository;
use App\Repository\CareRepository;
use App\Repository\MonthlyPaymentRepository;
use App\Repository\NannyDocumentRepository;
use App\Repository\NannyRepository;
use App\Repository\UserNotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class InternalNotificationGenerator
{
    public function __construct(
        private readonly CareRepository $cares,
        private readonly CareAbsenceRepository $absences,
        private readonly NannyDocumentRepository $documents,
        private readonly MonthlyPaymentRepository $payments,
        private readonly NannyRepository $nannies,
        private readonly UserNotificationRepository $notifications,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urls,
        private readonly BankAlertService $bankAlerts,
    ) {}

    public function generateFor(User $user): int
    {
        $created = 0;
        $day = (new \DateTimeImmutable())->format('Y-m-d');
        $next = $this->cares->findNextCare($user);
        if ($next && $next->getStartDateTime() <= new \DateTimeImmutable('+48 hours')) {
            $created += $this->add($user, 'care_upcoming:'.$next->getPublicId().':'.$day, 'calendar', 'Garde imminente', sprintf('Une garde avec %s débute le %s à %s.', $next->getNannyDisplayName(), $next->getDate()->format('d/m/Y'), $next->getStartTime()->format('H:i')), $this->urls->generate('app_care_index', ['publicId' => $next->getNanny()?->getPublicId()]));
        }
        foreach ($this->documents->findExpiring($user, new \DateTimeImmutable('+30 days')) as $document) {
            $created += $this->add($user, 'document_expiry:'.$document->getPublicId().':'.$day, 'document', 'Document arrivant à échéance', sprintf('Un document de %s arrive à échéance le %s.', $document->getNanny()?->getFullName(), $document->getExpiresAt()?->format('d/m/Y')), $this->urls->generate('app_nanny_documents', ['publicId' => $document->getNanny()?->getPublicId()]));
        }
        foreach ($this->payments->findUnpaidBeforeMonth($user, (new \DateTimeImmutable())->format('Y-m')) as $payment) {
            $created += $this->add($user, 'payment_overdue:'.$payment->getPublicId().':'.$day, 'payment', 'Paiement en retard', sprintf('Le paiement de %s pour %s est toujours en attente.', $payment->getNannyDisplayName(), $payment->getMonth()), $this->urls->generate('app_monthly_payment_show', ['publicId' => $payment->getPublicId()]));
        }
        $to = new \DateTimeImmutable('+90 days');
        foreach ($this->nannies->findAssignedToUser($user) as $nanny) {
            foreach ($this->absences->findBetween($user, $nanny, new \DateTimeImmutable(), $to) as $absence) {
                foreach ($this->cares->findBetween($user, $nanny, $absence->getStartsAt(), $absence->getEndsAt()) as $care) {
                    if (!$care->isCancelled() && $care->getStartDateTime() < $absence->getEndsAt() && $care->getEndDateTime() > $absence->getStartsAt()) {
                        $created += $this->add($user, 'care_conflict:'.$care->getPublicId().':'.$absence->getPublicId().':'.$day, 'warning', 'Conflit de planning', sprintf('La garde du %s chevauche « %s ».', $care->getDate()->format('d/m/Y'), $absence->getTypeLabel()), $this->urls->generate('app_care_index', ['publicId' => $nanny->getPublicId(), 'currentDate' => $care->getDate()->format('Y-m-d')]));
                    }
                }
            }
        }
        foreach ($this->bankAlerts->evaluate($user) as $alert) {
            $created += $this->add($user, $alert['key'], $alert['kind'], $alert['title'], $alert['message'], $alert['link']);
        }
        if ($created > 0) { $this->entityManager->flush(); }
        return $created;
    }

    private function add(User $user, string $key, string $kind, string $title, string $message, ?string $link): int
    {
        if ($this->notifications->findOneByDedupe($user, $key)) { return 0; }
        $notification = (new UserNotification())->setUser($user)->setDedupeKey($key)->setKind($kind)->setTitle($title)->setMessage($message)->setLink($link);
        $this->entityManager->persist($notification);
        return 1;
    }
}
