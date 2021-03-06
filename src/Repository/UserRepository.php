<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository
{
    /**
     * UserRepository constructor.
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * @return User[]
     */
    public function getUsersPointsSummaryDesc()
    {
        return $this->findBy([], ['pointSummary' => 'DESC', 'name' => 'ASC']);
    }

    /**
     * @return User[]
     */
    public function getAlternativeChampionshipUsers()
    {
        return $this->findBy(
            ['isAlterChamps' => true],
            ['alternativePointSummary' => 'DESC']
        );
    }
}
