<?php

namespace App\Service\Course;

use App\Entity\CourseProduct;
use App\Entity\User;
use App\Service\BankEncryptionService;
use Doctrine\ORM\EntityManagerInterface;

final class CourseProductManager
{
    public function __construct(
        private readonly BankEncryptionService $encryption,
        private readonly EntityManagerInterface $entityManager,
        private readonly CourseAuditLogger $auditLogger,
    ) {
    }

    public function save(User $user, CourseProduct $product, array $data): CourseProduct
    {
        $isNew = $product->getId() === null;
        $product
            ->setUser($user)
            ->setEncryptedName($this->encryption->encrypt((string) ($data['name'] ?? '')) ?? '')
            ->setCategory($data['category'] ?? null)
            ->setDefaultUnit((string) ($data['defaultUnit'] ?? CourseProduct::UNIT_PIECE))
            ->setPackageQuantity($data['packageQuantity'] ?? null);

        $this->entityManager->persist($product);
        $this->auditLogger->record($user, $isNew ? 'courses.product.create' : 'courses.product.update', 'course_product', $product->getPublicId());

        return $product;
    }

    public function archive(User $user, CourseProduct $product): void
    {
        $product->archive();
        $this->auditLogger->record($user, 'courses.product.archive', 'course_product', $product->getPublicId());
    }

    public function name(CourseProduct $product): string
    {
        return $this->encryption->decrypt($product->getEncryptedName());
    }
}
