<?php

namespace App\Service\Course;

use App\Entity\CourseStore;
use App\Entity\User;
use App\Service\BankEncryptionService;
use Doctrine\ORM\EntityManagerInterface;

final class CourseStoreManager
{
    public function __construct(
        private readonly BankEncryptionService $encryption,
        private readonly EntityManagerInterface $entityManager,
        private readonly CourseAuditLogger $auditLogger,
    ) {
    }

    public function save(User $user, CourseStore $store, array $data): CourseStore
    {
        $isNew = $store->getId() === null;
        $store
            ->setUser($user)
            ->setEncryptedName($this->encryption->encrypt((string) ($data['name'] ?? '')) ?? '')
            ->setChain($data['chain'] ?? null)
            ->setEncryptedAddress($this->encryption->encrypt($this->nullableString($data['address'] ?? null)));

        $this->entityManager->persist($store);
        $this->auditLogger->record($user, $isNew ? 'courses.store.create' : 'courses.store.update', 'course_store', $store->getPublicId());

        return $store;
    }

    public function archive(User $user, CourseStore $store): void
    {
        $store->archive();
        $this->auditLogger->record($user, 'courses.store.archive', 'course_store', $store->getPublicId());
    }

    public function name(CourseStore $store): string
    {
        return $this->encryption->decrypt($store->getEncryptedName());
    }

    public function address(CourseStore $store): ?string
    {
        $address = $store->getEncryptedAddress();

        return $address !== null ? $this->encryption->decrypt($address) : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
