<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;

/**
 * Class Bet
 * @package App\Repository
 */
class Bet extends EntityRepository
{
    /**
     * @param $event
     * @return array
     */
    public function getBetsByEvent($event)
    {
        return $this->findBy(['event_id' => $event]);
    }

    /**
     * @param $user
     * @return array
     */
    public function getBetsByUser($user)
    {
        return $this->findBy(['user_id' => $user]);
    }

    public function getBetByUserAndEvent($user, $event)
    {
        return $this->findOneBy([
            'user_id' => $user,
            'event_id' => $event
        ]);
    }

    /**
     * @param User $user
     * @param array $events
     * @return array
     */
    public function getBetByUserAndEvents(User $user, array $events)
    {
        return $this->findBy([
            'user_id' => $user,
            'event_id' => $events
        ]);
    }

    public function getBetsByEventOrderByPoints($event)
    {
        return $this->findBy(
            ['event_id' => $event],
            ['pointSummary' => 'DESC']
        );
    }

    /**
     * @return mixed
     */
    public function getTopRaceBets()
    {
        return $this->createQueryBuilder('bet')
            ->innerJoin('App:Race', 'race', Join::WITH, 'bet.event_id = race.id')
            ->orderBy('bet.pointSummary', 'DESC')
            ->getQuery()
            ->setMaxResults(3)
            ->getResult();
    }

    /**
     * @return mixed
     */
    public function getTopQualifyBets()
    {
        return $this->createQueryBuilder('bet')
            ->innerJoin('App:Qualify', 'qualify', Join::WITH, 'bet.event_id = qualify.id')
            ->orderBy('bet.pointSummary', 'DESC')
            ->getQuery()
            ->setMaxResults(3)
            ->getResult();
    }

    /**
     * @param Event $event
     * @return mixed
     */
    public function clearBetPointsByEvent(Event $event)
    {
        return $this->createQueryBuilder('bet')
            ->update('App:Bet', 'b')
            ->set('b.pointSummary', 'NULL')
            ->where('b.event_id = :e')
            ->setParameter('e', $event->getId())
            ->getQuery()
            ->execute();
    }
}
