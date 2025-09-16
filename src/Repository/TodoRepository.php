<?php

namespace App\Repository;

use App\Entity\Todo;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TodoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Todo::class);
    }

    public function findByOwner(User $owner): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.owner = :o')
            ->setParameter('o', $owner)
            ->orderBy('t.id', 'DESC')
            ->getQuery()->getResult();
    }
}
