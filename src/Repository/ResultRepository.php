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

    public function getStats(?int $userId): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select(
                'COUNT(r.id) AS count',
                'MIN(r.result) AS min',
                'MAX(r.result) AS max',
                'AVG(r.result) AS avg'
            );

        if (null !== $userId) {
            $qb->join('r.user', 'u')
            ->andWhere('u.id = :uid')
            ->setParameter('uid', $userId);
        }

        $row = $qb->getQuery()->getSingleResult();

        return [
            'count' => (int) ($row['count'] ?? 0),
            'min'   => (null === $row['min']) ? null : (int) $row['min'],
            'max'   => (null === $row['max']) ? null : (int) $row['max'],
            'avg'   => (null === $row['avg']) ? null : (float) $row['avg'],
        ];
    }

    public function findBestResultsGlobal(): array
    {
        $maxResult = $this->createQueryBuilder('r')
            ->select('MAX(r.result)')
            ->getQuery()
            ->getSingleScalarResult();

        if ($maxResult === null) {
            return [];
        }

        return $this->createQueryBuilder('r')
            ->addSelect('u')
            ->join('r.user', 'u')
            ->andWhere('r.result = :max')
            ->setParameter('max', (int) $maxResult)
            ->orderBy('r.time', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
