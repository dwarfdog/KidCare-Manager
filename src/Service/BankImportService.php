<?php

namespace App\Service;

use App\Entity\BankAccount;
use App\Entity\BankImportBatch;
use App\Entity\BankImportProfile;
use App\Entity\BankTransaction;
use App\Entity\User;
use App\Repository\BankTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Reader;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class BankImportService
{
    public function __construct(
        private readonly BankEncryptionService $encryption,
        private readonly BankTransactionRepository $transactionRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly BankAuditLogger $auditLogger,
        private readonly BankCategoryRuleMatcher $ruleMatcher,
        private readonly BankImportUndoService $undoService,
    ) {
    }

    public function import(User $user, BankAccount $account, UploadedFile $file, array $config, ?BankImportProfile $profile = null): BankImportBatch
    {
        $delimiter = (string) ($config['delimiter'] ?? ';');
        $dateFormat = (string) ($config['dateFormat'] ?? 'd/m/Y');
        $mapping = $config['mapping'] ?? [];

        $csv = Reader::createFromString((string) file_get_contents($file->getPathname()));
        $csv->setDelimiter($delimiter);
        $csv->setHeaderOffset(0);

        $batch = (new BankImportBatch())
            ->setUser($user)
            ->setAccount($account)
            ->setProfile($profile)
            ->setOriginalFilename($file->getClientOriginalName());

        $errors = [];
        $imported = 0;
        $skipped = 0;

        foreach ($csv->getRecords() as $index => $record) {
            try {
                $date = $this->parseDate((string) ($record[$mapping['date'] ?? ''] ?? ''), $dateFormat);
                $label = trim((string) ($record[$mapping['label'] ?? ''] ?? ''));
                $amount = $this->extractAmount($record, $mapping);
                $hash = $this->importHash($account, $date, $amount, $label);

                if ($this->transactionRepository->findOneByImportHash($user, $account, $hash) !== null) {
                    ++$skipped;
                    continue;
                }

                $transaction = (new BankTransaction())
                    ->setUser($user)
                    ->setAccount($account)
                    ->setDate($date)
                    ->setEncryptedLabel($this->encryption->encrypt($label) ?? '')
                    ->setSignedAmount($amount)
                    ->setStatus(BankTransaction::STATUS_REAL)
                    ->setSource(BankTransaction::SOURCE_IMPORT)
                    ->setImportHash($hash)
                    ->setImportBatch($batch);

                $this->ruleMatcher->apply($user, $transaction);
                $transaction->setImportedStateHash($this->undoService->fingerprint($transaction));

                $this->entityManager->persist($transaction);
                ++$imported;
            } catch (\Throwable $exception) {
                $errors[] = sprintf('Ligne %d: %s', $index + 2, $exception->getMessage());
            }
        }

        $batch
            ->setImportedCount($imported)
            ->setSkippedCount($skipped)
            ->setErrorCount(count($errors))
            ->setErrors($errors);

        $this->entityManager->persist($batch);
        $this->auditLogger->record($user, 'bank.import.csv', 'bank_import_batch', $batch->getPublicId(), [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => count($errors),
        ]);

        return $batch;
    }

    public function importHash(BankAccount $account, \DateTimeInterface $date, float $amount, string $label): string
    {
        return hash('sha256', implode('|', [
            $account->getPublicId(),
            $date->format('Y-m-d'),
            number_format($amount, 2, '.', ''),
            mb_strtolower(trim($label)),
        ]));
    }

    private function parseDate(string $rawDate, string $format): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!' . $format, trim($rawDate));
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new \InvalidArgumentException('date invalide');
        }

        return $date;
    }

    private function extractAmount(array $record, array $mapping): float
    {
        if (!empty($mapping['amount'])) {
            return $this->normalizeAmount((string) ($record[$mapping['amount']] ?? '0'));
        }

        $debit = !empty($mapping['debit']) ? abs($this->normalizeAmount((string) ($record[$mapping['debit']] ?? '0'))) : 0.0;
        $credit = !empty($mapping['credit']) ? abs($this->normalizeAmount((string) ($record[$mapping['credit']] ?? '0'))) : 0.0;

        return $credit > 0 ? $credit : -$debit;
    }

    private function normalizeAmount(string $value): float
    {
        $value = trim(str_replace(["\xc2\xa0", ' '], '', $value));
        $value = str_replace(',', '.', $value);

        return round((float) $value, 2);
    }
}
