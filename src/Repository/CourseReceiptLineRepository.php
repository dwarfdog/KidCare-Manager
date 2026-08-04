<?php

namespace App\Repository;

use App\Entity\CourseProduct;
use App\Entity\CourseReceipt;
use App\Entity\CourseReceiptLine;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CourseReceiptLine> */
class CourseReceiptLineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CourseReceiptLine::class);
    }

    public function findOneForUserByPublicId(User $user, string $publicId): ?CourseReceiptLine
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.user = :user')
            ->andWhere('l.publicId = :publicId')
            ->setParameter('user', $user)
            ->setParameter('publicId', $publicId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return CourseReceiptLine[] */
    public function findForReceipt(CourseReceipt $receipt): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.receipt = :receipt')
            ->setParameter('receipt', $receipt)
            ->orderBy('l.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Toutes les lignes des tickets confirmes (non archives) de l'utilisateur, avec le ticket/magasin/produit
     * charges en une seule requete pour l'agregation en PHP (evolution/comparatif de prix, statistiques).
     *
     * @return CourseReceiptLine[]
     */
    public function findConfirmedForUser(User $user): array
    {
        return $this->createQueryBuilder('l')
            ->select('l', 'r', 's', 'p')
            ->innerJoin('l.receipt', 'r')
            ->leftJoin('r.store', 's')
            ->leftJoin('l.product', 'p')
            ->andWhere('l.user = :user')
            ->andWhere('r.status = :status')
            ->andWhere('r.archivedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('status', CourseReceipt::STATUS_CONFIRMED)
            ->orderBy('r.purchaseDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return CourseReceiptLine[] */
    public function findConfirmedForProduct(User $user, CourseProduct $product): array
    {
        return $this->createQueryBuilder('l')
            ->select('l', 'r', 's')
            ->innerJoin('l.receipt', 'r')
            ->leftJoin('r.store', 's')
            ->andWhere('l.user = :user')
            ->andWhere('l.product = :product')
            ->andWhere('r.status = :status')
            ->andWhere('r.archivedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('product', $product)
            ->setParameter('status', CourseReceipt::STATUS_CONFIRMED)
            ->orderBy('r.purchaseDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
