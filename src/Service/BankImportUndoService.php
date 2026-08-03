<?php

namespace App\Service;

use App\Entity\BankImportBatch;
use App\Entity\BankTransaction;
use App\Entity\User;
use App\Repository\BankTransactionRepository;
use App\Repository\BankTransactionSplitRepository;

final class BankImportUndoService
{
    public function __construct(private readonly BankTransactionRepository $transactions, private readonly BankTransactionSplitRepository $splits, private readonly BankAuditLogger $audit) {}
    public function fingerprint(BankTransaction $t): string
    {
        return hash('sha256', implode('|', [$t->getDate()->format('Y-m-d'), $t->getEncryptedLabel(), number_format($t->getAmount(), 2, '.', ''), $t->getCategory()?->getPublicId() ?? '', $t->getStatus(), $t->getAccount()?->getPublicId() ?? '']));
    }
    /** @return array{archived:int,protected:int} */ public function undo(User $user, BankImportBatch $batch): array
    {
        $archived = 0;
        $protected = 0;
        foreach ($this->transactions->findForImportBatch($user, $batch) as $t) {
            $untouched = $t->getImportedStateHash() !== null && hash_equals($t->getImportedStateHash(), $this->fingerprint($t)) && !$t->isVerified() && !$t->isReconciled() && !$this->splits->hasSplits($t);
            if ($untouched) {
                $t->archive();
                ++$archived;
            } else {
                ++$protected;
            }
        }
        $batch->markUndone();
        $this->audit->record($user, 'bank.import.undo', 'bank_import_batch', $batch->getPublicId(), compact('archived', 'protected'));
        return compact('archived', 'protected');
    }
}
