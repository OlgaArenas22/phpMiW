<?php

namespace App\Repository;

use App\Entity\Result;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Result::class);
    }

    /**
     * @return Result[]
     */
    public function findByUser(User $user, string $orderBy = 'id'): array
    {
        $allowed = [ 'id', 'result', 'time' ];
        if (!in_array($orderBy, $allowed, true)) {
            $orderBy = 'id';
        }

        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :u')
            ->setParameter('u', $user)
            ->orderBy('r.' . $orderBy, 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneById(int $id): ?Result
    {
        /** @var Result|null $result */
        $result = $this->find($id);
        return $result;
    }

    public function findTopResults(?int $userId, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('r')
            ->orderBy('r.result', 'DESC')
            ->addOrderBy('r.time', 'DESC')
            ->setMaxResults($limit);

        if (null !== $userId) {
            $qb->join('r.user', 'u')
            ->andWhere('u.id = :uid')
            ->setParameter('uid', $userId);
        }

        return $qb->getQuery()->getResult();
    }
}
