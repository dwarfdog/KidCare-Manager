<?php

namespace App\Repository;

use App\Entity\CourseShoppingList;
use App\Entity\CourseShoppingListItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CourseShoppingListItem> */
class CourseShoppingListItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CourseShoppingListItem::class);
    }

    /** @return CourseShoppingListItem[] */
    public function findForList(CourseShoppingList $list): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.shoppingList = :list')
            ->setParameter('list', $list)
            ->orderBy('i.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
