<?php

namespace App\Service;

use App\Entity\BankAsset;
use App\Entity\BankAssetValuation;
use App\Entity\User;
use App\Repository\BankAccountRepository;
use App\Repository\BankAssetRepository;
use App\Repository\BankAssetValuationRepository;
use Doctrine\ORM\EntityManagerInterface;

final class BankNetWorthService
{
    public function __construct(private readonly BankAccountRepository $accounts, private readonly BankAccountManager $accountManager, private readonly BankAssetRepository $assets, private readonly BankAssetValuationRepository $valuations, private readonly EntityManagerInterface $em) {}
    public function snapshot(User $user): array
    {
        $accountAssets = 0.0;
        $debts = 0.0;
        foreach ($this->accounts->findActiveForUser($user) as $a) {
            if ($a->isLoan()) {
                $debts += $a->getLoanRemainingCapital() ?? 0.0;
            } else {
                $value = $this->accountManager->balance($a);
                if ($value >= 0) $accountAssets += $value;
                else $debts += abs($value);
            }
        }
        $manualAssets = 0.0;
        $manualDebts = 0.0;
        foreach ($this->assets->findActiveForUser($user) as $a) {
            if ($a->getType() === BankAsset::TYPE_DEBT) $manualDebts += $a->getCurrentValue();
            else $manualAssets += $a->getCurrentValue();
        }
        return ['accountAssets' => round($accountAssets, 2), 'manualAssets' => round($manualAssets, 2), 'bankDebts' => round($debts, 2), 'manualDebts' => round($manualDebts, 2), 'net' => round($accountAssets + $manualAssets - $debts - $manualDebts, 2)];
    }
    public function record(BankAsset $asset, float $value, \DateTimeInterface $date): void
    {
        $asset->setCurrentValue($value);
        $valuation = (new BankAssetValuation())->setAsset($asset)->setValue($value)->setValuedAt($date);
        $this->em->persist($valuation);
    }
    public function history(array $assets): array
    {
        $series = [];
        foreach ($assets as $asset) {
            foreach ($this->valuations->findForAsset($asset) as $v) {
                $month = $v->getValuedAt()->format('Y-m');
                $series[$month] = ($series[$month] ?? 0.0) + ($asset->getType() === BankAsset::TYPE_DEBT ? -$v->getValue() : $v->getValue());
            }
        }
        ksort($series);
        return ['labels' => array_keys($series), 'values' => array_values(array_map(static fn($v) => round($v, 2), $series))];
    }
}
