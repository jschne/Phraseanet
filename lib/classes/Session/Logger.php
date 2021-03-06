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
 * @package     Session
 * @license     http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link        www.phraseanet.com
 */
class Session_Logger
{
    /**
     *
     * @var int
     */
    protected $id;

    /**
     *
     * @var databox
     */
    protected $databox;
    protected $app;

    const EVENT_DELETE = 'delete';
    const EVENT_EDIT = 'edit';
    const EVENT_EXPORTDOWNLOAD = 'download';
    const EVENT_EXPORTFTP = 'ftp';
    const EVENT_EXPORTMAIL = 'mail';
    const EVENT_MOVE = 'collection';
    const EVENT_PRINT = 'print';
    const EVENT_PUSH = 'push';
    const EVENT_STATUS = 'status';
    const EVENT_SUBSTITUTE = 'substit';
    const EVENT_VALIDATE = 'validate';

    /**
     *
     * @param Application $app
     * @param databox     $databox
     * @param integer     $log_id
     *
     * @return Session_Logger
     */
    public function __construct(Application $app, databox $databox, $log_id)
    {
        $this->app = $app;
        $this->databox = $databox;
        $this->id = (int) $log_id;

        return $this;
    }

    /**
     *
     * @return int
     */
    public function get_id()
    {
        return $this->id;
    }

    public function log(record_adapter $record, $action, $final, $comment)
    {
        $sql = 'INSERT INTO log_docs
          (id, log_id, date, record_id, action, final, comment)
          VALUES (null, :log_id, NOW(), :record_id, :action, :final, :comm)';

        $stmt = $this->databox->get_connection()->prepare($sql);

        $params = array(
            ':log_id'    => $this->get_id()
            , ':record_id' => $record->get_record_id()
            , ':action'    => $action
            , ':final'     => $final
            , ':comm'      => $comment
        );

        $stmt->execute($params);
        $stmt->closeCursor();

        return $this;
    }

    /**
     *
     * @param Application $app
     * @param databox     $databox
     * @param Browser     $browser
     *
     * @return Session_Logger
     */
    public static function create(Application $app, databox $databox, Browser $browser)
    {
        $colls = array();

        if ($app['authentication']->getUser()) {
            $bases = $app['authentication']->getUser()->ACL()->get_granted_base(array(), array($databox->get_sbas_id()));
            foreach ($bases as $collection) {
                $colls[] = $collection->get_coll_id();
            }
        }

        $conn =  $databox->get_connection();

        $sql = "INSERT INTO log
              (id, date,sit_session, user, site, usrid, nav,
                version, os, res, ip, user_agent,appli, fonction,
                societe, activite, pays)
            VALUES
              (null,now() , :ses_id, :usr_login, :site_id, :usr_id
              , :browser, :browser_version,  :platform, :screen, :ip
              , :user_agent, :appli, :fonction, :company, :activity, :country)";

        $params = array(
            ':ses_id'          => $app['session']->get('session_id'),
            ':usr_login'       => $app['authentication']->getUser() ? $app['authentication']->getUser()->get_login() : null,
            ':site_id'         => $app['phraseanet.configuration']['main']['key'],
            ':usr_id'          => $app['authentication']->isAuthenticated() ? $app['authentication']->getUser()->get_id() : null,
            ':browser'         => $browser->getBrowser(),
            ':browser_version' => $browser->getExtendedVersion(),
            ':platform'        => $browser->getPlatform(),
            ':screen'          => $browser->getScreenSize(),
            ':ip'              => $browser->getIP(),
            ':user_agent'      => $browser->getUserAgent(),
            ':appli'           => serialize(array()),
            ':fonction' => $app['authentication']->getUser() ? $app['authentication']->getUser()->get_job() : null,
            ':company'  => $app['authentication']->getUser() ? $app['authentication']->getUser()->get_company() : null,
            ':activity' => $app['authentication']->getUser() ? $app['authentication']->getUser()->get_position() : null,
            ':country'  => $app['authentication']->getUser() ? $app['authentication']->getUser()->get_country() : null
        );

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $log_id = $conn->lastInsertId();
        $stmt->closeCursor();

        $sql = "INSERT INTO log_colls (id, log_id, coll_id) VALUES (null, :log_id, :coll_id)";
        $stmt = $conn->prepare($sql);

        foreach ($colls as $collId) {
            $stmt->execute(array(
                ':log_id'  => $log_id,
                ':coll_id' => $collId
            ));
        }

        $stmt->closeCursor();
        unset($stmt, $conn);

        return new Session_Logger($app, $databox, $log_id);
    }

    public static function load(Application $app, databox $databox)
    {
        if ( ! $app['authentication']->isAuthenticated()) {
            throw new Exception_Session_LoggerNotFound('Not authenticated');
        }

        $sql = 'SELECT id FROM log
            WHERE site = :site AND sit_session = :ses_id';

        $params = array(
            ':site'   => $app['phraseanet.configuration']['main']['key']
            , ':ses_id' => $app['session']->get('session_id')
        );

        $stmt = $databox->get_connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ( ! $row)
            throw new Exception_Session_LoggerNotFound('Logger not found');

        return new self($app, $databox, $row['id']);
    }
}
