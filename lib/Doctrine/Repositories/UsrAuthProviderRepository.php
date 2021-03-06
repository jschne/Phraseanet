<?php

namespace Repositories;

use Doctrine\ORM\EntityRepository;

/**
 * UsrAuthProvider
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class UsrAuthProviderRepository extends EntityRepository
{
    public function findByUser(\User_Adapter $user)
    {
        $dql = 'SELECT u
                FROM Entities\UsrAuthProvider u
                WHERE u.usr_id = :usrId';

        $params = array('usrId' => $user->get_id());

        $query = $this->_em->createQuery($dql);
        $query->setParameters($params);

        return $query->getResult();
    }

    public function findWithProviderAndId($providerId, $distantId)
    {
        $dql = 'SELECT u
                FROM Entities\UsrAuthProvider u
                WHERE u.provider = :providerId AND u.distant_id = :distantId';

        $params = array('providerId' => $providerId, 'distantId' => $distantId);

        $query = $this->_em->createQuery($dql);
        $query->setParameters($params);

        return $query->getOneOrNullResult();
    }
}
