<?php

/*
 * This file is part of Phraseanet
 *
 * (c) 2005-2013 Alchemy
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Alchemy\Phrasea\Application;

/**
 *
 * @license     http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link        www.phraseanet.com
 */
class patch_370a5 implements patchInterface
{
    /**
     *
     * @var string
     */
    private $release = '3.7.0.a5';

    /**
     *
     * @var Array
     */
    private $concern = array(base::DATA_BOX);

    /**
     *
     * @return string
     */
    public function get_release()
    {
        return $this->release;
    }

    public function require_all_upgrades()
    {
        return false;
    }

    /**
     *
     * @return Array
     */
    public function concern()
    {
        return $this->concern;
    }

    public function apply(base $databox, Application $app)
    {

        $sql = 'SELECT id, src FROM metadatas_structure';
        $stmt = $databox->get_connection()->prepare($sql);
        $stmt->execute();
        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $update = array();

        foreach ($rs as $row) {
            $src = str_replace(
                array('/rdf:RDF/rdf:Description/PHRASEANET:', '/rdf:RDF/rdf:Description/'), array('Phraseanet:', ''), $row['src']
            );
            $update[] = array('id'  => $row['id'], 'src' => $src);
        }

        $sql = 'UPDATE metadatas_structure SET src = :src WHERE id = :id';
        $stmt = $databox->get_connection()->prepare($sql);

        foreach ($update as $row) {
            $stmt->execute(array(':src' => $row['src'], ':id'  => $row['id']));
        }

        $stmt->closeCursor();

        return true;
    }
}
