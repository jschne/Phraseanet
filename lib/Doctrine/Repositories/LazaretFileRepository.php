<?php

namespace Repositories;

use Doctrine\ORM\EntityRepository;

/**
 * LazaretFileRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class LazaretFileRepository extends EntityRepository
{

    public function findPerPage(array $base_ids, $offset = 0, $perPage = 10)
    {
        $base_ids = implode(', ', array_map(function($int) {
                    return (int) $int;
                }, $base_ids));

        $dql = '
            SELECT f
            FROM Entities\LazaretFile f'
            . ('' === $base_ids ? '' : ' WHERE f.base_id IN  (' . $base_ids . ')')
            . ' ORDER BY f.id DESC';

        $query = $this->_em->createQuery($dql);
        $query->setFirstResult($offset)
            ->setMaxResults($perPage);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, true);

        return $paginator;
    }
}
