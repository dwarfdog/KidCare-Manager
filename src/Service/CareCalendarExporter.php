<?php

namespace App\Service;

use App\Entity\Care;
use App\Entity\CareAbsence;
use App\Entity\Nanny;
use App\Entity\User;
use App\Repository\CareAbsenceRepository;
use App\Repository\CareRepository;
use App\Repository\NannyRateRepository;

final class CareCalendarExporter
{
    public function __construct(private readonly CareRepository $cares, private readonly CareAbsenceRepository $absences, private readonly NannyRateRepository $rates, private readonly ApplicationSettings $settings) {}

    public function export(User $user, Nanny $nanny): string
    {
        $from = new \DateTimeImmutable('-1 year');
        $to = new \DateTimeImmutable('+2 years');
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//KidCare Manager//FR', 'CALSCALE:GREGORIAN', 'X-WR-CALNAME:KidCare - '.$this->escape($nanny->getFullName())];
        foreach ($this->cares->findBetween($user, $nanny, $from, $to) as $care) { $lines = array_merge($lines, $this->careEvent($user, $nanny, $care)); }
        foreach ($this->absences->findBetween($user, $nanny, $from, $to) as $absence) { $lines = array_merge($lines, $this->absenceEvent($absence)); }
        $lines[] = 'END:VCALENDAR';
        return implode("\r\n", $lines)."\r\n";
    }

    /** @return string[] */
    private function careEvent(User $user, Nanny $nanny, Care $care): array
    {
        $rate = $this->rates->findApplicableRate($user, $nanny, $care->getDate());
        $amount = $rate ? ($care->getHoursCount() * $rate->getHourlyRate()) + ($care->getMealsCount() * $rate->getMealRate()) : null;
        $status = $care->isCancelled() ? 'Annulée' : ($care->isRealized() ? 'Réalisée' : 'Prévue');
        $description = [$status, number_format($care->getHoursCount(), 2, ',', ' ').' h', $care->getMealsCount().' repas'];
        if ($amount !== null) { $description[] = number_format($amount, 2, ',', ' ').' '.$this->settings->currency(); }
        if ($care->getNonRealizationReason()) { $description[] = $care->getNonRealizationReason(); }
        if ($care->getNotes()) { $description[] = $care->getNotes(); }
        return ['BEGIN:VEVENT', 'UID:care-'.$care->getPublicId().'@kidcare.local', 'DTSTAMP:'.gmdate('Ymd\THis\Z'), 'DTSTART:'.$care->getStartDateTime()->format('Ymd\THis'), 'DTEND:'.$care->getEndDateTime()->format('Ymd\THis'), 'SUMMARY:'.$this->escape('Garde - '.$nanny->getFullName()), 'DESCRIPTION:'.$this->escape(implode(' | ', $description)), 'END:VEVENT'];
    }

    /** @return string[] */
    private function absenceEvent(CareAbsence $absence): array
    {
        $description = [$absence->getTypeLabel(), $absence->isRemunerated() ? 'Rémunérée' : 'Non rémunérée'];
        if ($absence->getNotes()) { $description[] = $absence->getNotes(); }
        return ['BEGIN:VEVENT', 'UID:absence-'.$absence->getPublicId().'@kidcare.local', 'DTSTAMP:'.gmdate('Ymd\THis\Z'), 'DTSTART:'.$absence->getStartsAt()->format('Ymd\THis'), 'DTEND:'.$absence->getEndsAt()->format('Ymd\THis'), 'SUMMARY:'.$this->escape($absence->getTypeLabel()), 'DESCRIPTION:'.$this->escape(implode(' | ', $description)), 'END:VEVENT'];
    }

    private function escape(string $value): string { return str_replace(["\\", ";", ",", "\r", "\n"], ["\\\\", '\\;', '\\,', '', '\\n'], $value); }
}
