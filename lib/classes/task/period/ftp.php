<?php
/*
 * This file is part of Phraseanet
 *
 * (c) 2005-2013 Alchemy
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Alchemy\Phrasea\Exception\InvalidArgumentException;
use Alchemy\Phrasea\Notification\Mail\MailSuccessFTPSender;
use Alchemy\Phrasea\Notification\Receiver;

class task_period_ftp extends task_appboxAbstract
{
    protected $proxy;
    protected $proxyport;

    /**
     *
     * @return string
     */
    public function getName()
    {
        return(_("task::ftp:FTP Push"));
    }

    /**
     *
     * @return string
     */
    public function help()
    {
        return '';
    }

    /**
     *
     * @param  string $oldxml
     * @return string
     */
    public function graphic2xml($oldxml)
    {
        $request = http_request::getInstance();

        $parm2 = $request->get_parms('proxy', 'proxyport', 'period', 'syslog');
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        if ((@$dom->loadXML($oldxml)) != FALSE) {
            $xmlchanged = false;
            foreach (array('str:proxy', 'str:proxyport', 'str:period', 'pop:syslog') as $pname) {
                $ptype = substr($pname, 0, 3);
                $pname = substr($pname, 4);
                $pvalue = $parm2[$pname];
                if (($ns = $dom->getElementsByTagName($pname)->item(0)) != NULL) {
                    // le champ existait dans le xml, on supprime son ancienne valeur (tout le contenu)
                    while (($n = $ns->firstChild)) {
                        $ns->removeChild($n);
                    }
                } else {
                    // le champ n'existait pas dans le xml, on le cree
                    $ns = $dom->documentElement->appendChild($dom->createElement($pname));
                }
                // on fixe sa valeur
                switch ($ptype) {
                    case "str":
                    case "pop":
                        $ns->appendChild($dom->createTextNode($pvalue));
                        break;
                    case "boo":
                        $ns->appendChild($dom->createTextNode($pvalue ? '1' : '0'));
                        break;
                }
                $xmlchanged = true;
            }
        }

        return($dom->saveXML());
    }


    /**
     *
     * @return void
     */
    public function printInterfaceJS()
    {
        ?>
        <script type="text/javascript">
            function taskFillGraphic_<?php echo(get_class($this));?>(xml)
            {
                if (xml) {
                    xml = $.parseXML(xml);
                    xml = $(xml);

                    with(document.forms['graphicForm'])
                    {
                        proxy.value     = xml.find("proxy").text();
                        proxyport.value = xml.find("proxyport").text();
                        period.value    = xml.find("period").text();
                    }
                }
            }

            $(document).ready(function(){
                var limits = {
                    'period' :{'min':<?php echo self::MINPERIOD; ?>, 'max':<?php echo self::MAXPERIOD; ?>}
                } ;
                $(".formElem").change(function(){
                    fieldname = $(this).attr("name");
                    switch ((this.nodeName+$(this).attr("type")).toLowerCase()) {
                        case "inputtext":
                            if (typeof(limits[fieldname])!='undefined') {
                                var v = 0|this.value;
                                if(v < limits[fieldname].min)
                                    v = limits[fieldname].min;
                                else if(v > limits[fieldname].max)
                                    v = limits[fieldname].max;
                                this.value = v;
                            }
                            break;
                    }
                    setDirty();
                });
            });
        </script>
        <?php

        return;
    }

    /**
     *
     * @return string
     */
    public function getInterfaceHTML()
    {
        ob_start();
        ?>
        <form id="graphicForm" name="graphicForm" class="form-horizontal" onsubmit="return(false);" method="post">
            <div class="control-group">
                <label class="control-label"><?php echo _('task::ftp:proxy') ?></label>
                <div class="controls">
                    <input class="formElem" type="text" name="proxy" />
                </div>
            </div>
            <div class="control-group">
                <label class="control-label"><?php echo _('task::ftp:proxy port') ?></label>
                <div class="controls">
                    <input class="formElem" type="text" name="proxyport" />
                </div>
            </div>
            <div class="control-group">
                <label class="control-label"><?php echo _('task::_common_:periodicite de la tache') ?></label>
                <div class="controls">
                    <input class="formElem input-small" type="text" name="period" />
                    <span class="help-inline"><?php echo _('task::_common_:secondes (unite temporelle)') ?></span>
                </div>
            </div>
        </form>
        <?php

        return ob_get_clean();
    }

    public function saveChanges(connection_pdo $conn, $taskid, &$taskrow)
    {
        $request = http_request::getInstance();

        $parm = $request->get_parms(
            'xml'
            , 'name'
            , 'active'
            , 'proxy'
            , 'proxyport'
            , 'period'
            , 'debug'
        );

        if ($parm["xml"] === null) {
            // pas de xml 'raw' : on accepte les champs 'graphic view'
            $domTaskSettings = new DOMDocument();
            if ((@$domTaskSettings->loadXML($taskrow["settings"])) != FALSE) {
                $xmlchanged = false;
                foreach (array(
                'proxy'
                , 'proxyport'
                , 'period'
                ) as $f) {
                    if ($parm[$f] !== NULL) {
                        if (($ns = $domTaskSettings->getElementsByTagName($f)->item(0)) != NULL) {
                            // le champ existait dans le xml, on supprime son ancienne valeur (tout le contenu)
                            while (($n = $ns->firstChild)) {
                                $ns->removeChild($n);
                            }
                        } else {
                            // le champ n'existait pas dans le xml, on le cree
                            $ns = $domTaskSettings->documentElement->appendChild($domTaskSettings->createElement($f));
                        }
                        // on fixe sa valeur
                        $ns->appendChild($domTaskSettings->createTextNode($parm[$f]));
                        $xmlchanged = true;
                    }
                }
                if ($xmlchanged) {
                    $parm["xml"] = $domTaskSettings->saveXML();
                }
            }
        }

        // si on doit changer le xml, on verifie qu'il est valide
        $domdoc = new DOMDocument();
        if ($parm["xml"] && ! @$domdoc->loadXML($parm["xml"])) {
            return(false);
        }

        $sql = "";
        $params = array(':task_id' => $taskid);
        if ($parm["xml"] !== NULL) {
            $sql .= ( $sql ? " ," : "") . "settings = :settings";
            $params[':settings'] = $parm['xml'];
        }
        if ($parm["name"] !== NULL) {
            $sql .= ( $sql ? " ," : "") . "name = :name";
            $params[':name'] = $parm['name'];
        }
        if ($parm["active"] !== NULL) {
            $sql .= ( $sql ? " ," : "") . "active = :active";
            $params[':active'] = $parm['active'];
        }

        if ($sql) {
            try {
                $sql = "UPDATE task2 SET $sql WHERE task_id = :task_id";
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                $stmt->closeCursor();

                return true;
            } catch (Exception $e) {
                return false;
            }
        } else {
            return true;
        }
    }

    protected function loadSettings(SimpleXMLElement $sx_task_settings)
    {
        $this->proxy = (string) $sx_task_settings->proxy;
        $this->proxyport = (string) $sx_task_settings->proxyport;

        parent::loadSettings($sx_task_settings);
    }

    protected function retrieveContent(appbox $appbox)
    {
        $conn = $appbox->get_connection();

        $time2sleep = null;
        $ftp_exports = array();

        $period = $this->period;
        $time2sleep = (int) ($period);

        $sql = "SELECT id FROM ftp_export WHERE crash>=nbretry
            AND date < :date";

        $params = array(':date' => $this->dependencyContainer['date-formatter']->format_mysql(new DateTime('-30 days')));

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        foreach ($rs as $rowtask) {
            $sql = "DELETE FROM ftp_export WHERE id = :export_id";
            $stmt = $conn->prepare($sql);
            $stmt->execute(array(':export_id' => $rowtask['id']));
            $stmt->closeCursor();

            $sql = "DELETE FROM ftp_export_elements WHERE ftp_export_id = :export_id";
            $stmt = $conn->prepare($sql);
            $stmt->execute(array(':export_id' => $rowtask['id']));
            $stmt->closeCursor();
        }

        $sql = "SELECT * FROM ftp_export WHERE crash<nbretry ORDER BY id";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        foreach ($rs as $row) {
            $ftp_exports[$row["id"]] = array_merge(array('files' => array()), $row);
        }

        $sql = "SELECT e.* from ftp_export f
                    INNER JOIN ftp_export_elements e
            ON (f.id=e.ftp_export_id AND f.crash<f.nbretry
              AND (e.done = 0 or error=1))
                    ORDER BY f.id";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        foreach ($rs as $rowtask) {
            if (isset($ftp_exports[$rowtask["ftp_export_id"]])) {
                $ftp_exports[$rowtask["ftp_export_id"]]["files"][] = $rowtask;
            }
        }

        return $ftp_exports;
    }

    protected function processOneContent(appbox $appbox, Array $ftp_export)
    {
        $conn = $appbox->get_connection();

        $id = $ftp_export['id'];
        $ftp_export[$id]["crash"] = $ftp_export["crash"];
        $ftp_export[$id]["nbretry"] = $ftp_export["nbretry"] < 1 ? 3 : (int) $ftp_export["nbretry"];

        $state = "";
        $ftp_server = $ftp_export["addr"];
        $ftp_user_name = $ftp_export["login"];
        $ftp_user_pass = $ftp_export["pwd"];
        $usr_id = (int) $ftp_export["usr_id"];

        $ftpLog = $ftp_user_name . "@" . p4string::addEndSlash($ftp_server) . $ftp_export["destfolder"];

        if ($ftp_export["crash"] == 0) {
            $line = sprintf(
                _('task::ftp:Etat d\'envoi FTP vers le serveur' .
                    ' "%1$s" avec le compte "%2$s" et pour destination le dossier : "%3$s"') . PHP_EOL
                , $ftp_server
                , $ftp_user_name
                , $ftp_export["destfolder"]
            );
            $state .= $line;
            $this->logger->addDebug($line);
        }

        $state .= $line = sprintf(
                _("task::ftp:TENTATIVE no %s, %s")
                , $ftp_export["crash"] + 1
                , "  (" . date('r') . ")"
            ) . PHP_EOL;

        $this->logger->addDebug($line);

        try {
            $ssl = ($ftp_export['ssl'] == '1');
            $ftp_client = $this->dependencyContainer['phraseanet.ftp.client']($ftp_server, 21, 300, $ssl, $this->proxy, $this->proxyport);
            $ftp_client->login($ftp_user_name, $ftp_user_pass);

            if ($ftp_export["passif"] == "1") {
                try {
                    $ftp_client->passive(true);
                } catch (Exception $e) {
                    $this->logger->addDebug($e->getMessage());
                }
            }

            if (trim($ftp_export["destfolder"]) != '') {
                try {
                    $ftp_client->chdir($ftp_export["destfolder"]);
                    $ftp_export["destfolder"] = '/' . $ftp_export["destfolder"];
                } catch (Exception $e) {
                    $this->logger->addDebug($e->getMessage());
                }
            } else {
                $ftp_export["destfolder"] = '/';
            }

            if (trim($ftp_export["foldertocreate"]) != '') {
                try {
                    $ftp_client->mkdir($ftp_export["foldertocreate"]);
                } catch (Exception $e) {
                    $this->logger->addDebug($e->getMessage());
                }
                try {
                    $new_dir = $ftp_client->add_end_slash($ftp_export["destfolder"])
                        . $ftp_export["foldertocreate"];
                    $ftp_client->chdir($new_dir);
                } catch (Exception $e) {
                    $this->logger->addDebug($e->getMessage());
                }
            }

            $obj = array();

            $basefolder = '';
            if (!in_array(trim($ftp_export["destfolder"]), array('.', './', ''))) {
                $basefolder = p4string::addEndSlash($ftp_export["destfolder"]);
            }

            $basefolder .= $ftp_export["foldertocreate"];

            if (in_array(trim($basefolder), array('.', './', ''))) {
                $basefolder = '/';
            }

            foreach ($ftp_export['files'] as $fileid => $file) {
                $base_id = $file["base_id"];
                $record_id = $file["record_id"];
                $subdef = $file['subdef'];

                try {
                    $sbas_id = phrasea::sbasFromBas($this->dependencyContainer, $base_id);
                    $record = new record_adapter($this->dependencyContainer, $sbas_id, $record_id);

                    $sdcaption = $record->get_caption()->serialize(caption_record::SERIALIZE_XML, $ftp_export["businessfields"]);

                    $remotefile = $file["filename"];

                    if ($subdef == 'caption') {
                        $desc = $record->get_caption()->serialize(\caption_record::SERIALIZE_XML, $ftp_export["businessfields"]);

                        $localfile = $this->dependencyContainer['root.path'] . '/tmp/' . md5($desc . time() . mt_rand());
                        if (file_put_contents($localfile, $desc) === false) {
                            throw new Exception('Impossible de creer un fichier temporaire');
                        }
                    } elseif ($subdef == 'caption-yaml') {
                        $desc = $record->get_caption()->serialize(\caption_record::SERIALIZE_YAML, $ftp_export["businessfields"]);

                        $localfile = $this->dependencyContainer['root.path'] . '/tmp/' . md5($desc . time() . mt_rand());
                        if (file_put_contents($localfile, $desc) === false) {
                            throw new Exception('Impossible de creer un fichier temporaire');
                        }
                    } else {
                        $sd = $record->get_subdefs();

                        if (!$sd || !isset($sd[$subdef])) {
                            continue;
                        }

                        $localfile = $sd[$subdef]->get_pathfile();
                        if (!file_exists($localfile)) {
                            throw new Exception('Le fichier local n\'existe pas');
                        }
                    }

                    $current_folder = p4string::delEndSlash(str_replace('//', '/', $basefolder . $file['folder']));

                    if ($ftp_client->pwd() != $current_folder) {
                        try {
                            $ftp_client->chdir($current_folder);
                        } catch (Exception $e) {
                            $this->logger->addDebug($e->getMessage());
                        }
                    }

                    $ftp_client->put($remotefile, $localfile);

                    $obj[] = array(
                        "name"     => $subdef, "size"     => filesize($localfile),
                        "shortXml" => ($sdcaption ? $sdcaption : '')
                    );

                    if ($subdef == 'caption') {
                        unlink($localfile);
                    }

                    $sql = "UPDATE ftp_export_elements"
                        . " SET done='1', error='0' WHERE id = :file_id";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute(array(':file_id' => $file['id']));
                    $stmt->closeCursor();
                    $this->logexport($record, $obj, $ftpLog);
                } catch (Exception $e) {
                    $state .= $line = sprintf(_('task::ftp:File "%1$s" (record %2$s) de la base "%3$s"' .
                                ' (Export du Document) : Transfert cancelled (le document n\'existe plus)')
                            , basename($localfile), $record_id
                            , phrasea::sbas_labels(phrasea::sbasFromBas($this->dependencyContainer, $base_id), $this->dependencyContainer)) . "\n<br/>";

                    $this->logger->addDebug($line);

                    $done = $file['error'];

                    $sql = "UPDATE ftp_export_elements"
                        . " SET done = :done, error='1' WHERE id = :file_id";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute(array(':done'    => $done, ':file_id' => $file['id']));
                    $stmt->closeCursor();
                }
            }

            if ($ftp_export['logfile']) {
                $this->logger->addDebug("logfile ");

                $date = new DateTime();
                $remote_file = $date->format('U');

                $sql = 'SELECT filename, folder'
                    . ' FROM ftp_export_elements'
                    . ' WHERE ftp_export_id = :ftp_export_id'
                    . ' AND error = "0" AND done="1"';

                $stmt = $conn->prepare($sql);
                $stmt->execute(array(':ftp_export_id' => $id));
                $rs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $stmt->closeCursor();

                $buffer = '#transfert finished ' . $date->format(DATE_ATOM) . "\n\n";

                foreach ($rs as $row) {
                    $filename = $row['filename'];
                    $folder = $row['folder'];

                    $root = $ftp_export['foldertocreate'];

                    $buffer .= $root . '/' . $folder . $filename . "\n";
                }

                $tmpfile = $this->dependencyContainer['root.path'] . '/tmp/tmpftpbuffer' . $date->format('U') . '.txt';

                file_put_contents($tmpfile, $buffer);

                $remotefile = $date->format('U') . '-transfert.log';

                $ftp_client->chdir($ftp_export["destfolder"]);

                $ftp_client->put($remotefile, $tmpfile);

                unlink($tmpfile);
            }

            $ftp_client->close();
            unset($ftp_client);
        } catch (Exception $e) {
            $state .= $line = $e . "\n";

            $this->logger->addDebug($line);

            $sql = "UPDATE ftp_export SET crash=crash+1,date=now()"
                . " WHERE id = :export_id";
            $stmt = $conn->prepare($sql);
            $stmt->execute(array(':export_id' => $ftp_export['id']));
            $stmt->closeCursor();

            unset($ftp_client);
        }
        $this->finalize($appbox, $id);
    }

    protected function postProcessOneContent(appbox $appbox, Array $row)
    {
        return $this;
    }

    public function finalize(appbox $appbox, $id)
    {
        $conn = $appbox->get_connection();

        $sql = 'SELECT crash, nbretry FROM ftp_export WHERE id = :export_id';

        $stmt = $conn->prepare($sql);
        $stmt->execute(array(':export_id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($row && $row['crash'] >= $row['nbretry']) {
            $this->send_mails($appbox, $id);

            return $this;
        }

        $sql = 'SELECT count(id) as total, sum(error) as errors, sum(done) as done'
            . ' FROM ftp_export_elements WHERE ftp_export_id = :export_id';
        $stmt = $conn->prepare($sql);
        $stmt->execute(array(':export_id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($row && $row['done'] == (int) $row['total']) {
            $this->send_mails($appbox, $id);

            if ((int) $row['errors'] === 0) {
                $sql = 'DELETE FROM ftp_export WHERE id = :export_id';
                $stmt = $conn->prepare($sql);
                $stmt->execute(array(':export_id' => $id));
                $stmt->closeCursor();
                $sql = 'DELETE FROM ftp_export_elements WHERE ftp_export_id = :export_id';
                $stmt = $conn->prepare($sql);
                $stmt->execute(array(':export_id' => $id));
                $stmt->closeCursor();
            } else {
                $sql = 'UPDATE ftp_export SET crash = nbretry';
                $stmt = $conn->prepare($sql);
                $stmt->execute();
                $stmt->closeCursor();
                $sql = 'DELETE FROM ftp_export_elements WHERE ftp_export_id = :export_id AND error="0"';
                $stmt = $conn->prepare($sql);
                $stmt->execute(array(':export_id' => $id));
                $stmt->closeCursor();
            }

            return $this;
        }
    }

    public function send_mails(appbox $appbox, $id)
    {
        $conn = $appbox->get_connection();

        $sql = 'SELECT filename, base_id, record_id, subdef, error, done'
            . ' FROM ftp_export_elements WHERE ftp_export_id = :export_id';

        $transferts = array();

        $transfert_status = _('task::ftp:Tous les documents ont ete transferes avec succes');

        $stmt = $conn->prepare($sql);
        $stmt->execute(array(':export_id' => $id));
        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        foreach ($rs as $row) {
            if ($row['error'] == '0' && $row['done'] == '1') {
                $transferts[] =
                    '<li>' . sprintf(_('task::ftp:Record %1$s - %2$s de la base (%3$s - %4$s) - %5$s')
                        , $row["record_id"], $row["filename"]
                        , phrasea::sbas_labels(phrasea::sbasFromBas($this->dependencyContainer, $row["base_id"]), $this->dependencyContainer)
                        , phrasea::bas_labels($row['base_id'], $this->dependencyContainer), $row['subdef']) . ' : ' . _('Transfert OK') . '</li>';
            } else {
                $transferts[] =
                    '<li>' . sprintf(_('task::ftp:Record %1$s - %2$s de la base (%3$s - %4$s) - %5$s')
                        , $row["record_id"], $row["filename"]
                        , phrasea::sbas_labels(phrasea::sbasFromBas($this->dependencyContainer, $row["base_id"]), $this->dependencyContainer), phrasea::bas_labels($row['base_id'], $this->dependencyContainer)
                        , $row['subdef']) . ' : ' . _('Transfert Annule') . '</li>';
                $transfert_status = _('task::ftp:Certains documents n\'ont pas pu etre tranferes');
            }
        }

        $sql = 'SELECT addr, crash, nbretry, sendermail, mail, text_mail_sender, text_mail_receiver'
            . ' FROM ftp_export WHERE id = :export_id';

        $stmt = $conn->prepare($sql);
        $stmt->execute(array(':export_id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($row) {
            if ($row['crash'] >= $row['nbretry']) {
                $connection_status = _('Des difficultes ont ete rencontres a la connection au serveur distant');
            } else {
                $connection_status = _('La connection vers le serveur distant est OK');
            }

            $text_mail_sender = $row['text_mail_sender'];
            $text_mail_receiver = $row['text_mail_receiver'];
            $mail = $row['mail'];
            $sendermail = $row['sendermail'];
            $ftp_server = $row['addr'];
        }

        $message = "\n\n----------------------------------------\n\n";
        $message =  $connection_status . "\n";
        $message .= $transfert_status . "\n";
        $message .= _("task::ftp:Details des fichiers") . "\n\n";

        $message .= implode("\n", $transferts);

        $sender_message = $text_mail_sender . $message;
        $receiver_message = $text_mail_receiver . $message;

        $receiver = null;
        try {
            $receiver = new Receiver(null, $sendermail);
        } catch (InvalidArgumentException $e) {

        }

        if ($receiver) {
            $mail = MailSuccessFTPSender::create($this->dependencyContainer, $receiver, null, $sender_message);
            $mail->setServer($ftp_server);
            $this->dependencyContainer['notification.deliverer']->deliver($mail);
        }

        $receiver = null;
        try {
            $receiver = new Receiver(null, $mail);
        } catch (InvalidArgumentException $e) {

        }

        if ($receiver) {
            $mail = MailSuccessFTP::create($this->dependencyContainer, $receiver, null, $receiver_message);
            $mail->setServer($ftp_server);
            $this->dependencyContainer['notification.deliverer']->deliver($mail);
        }
    }

    public function logexport(record_adapter $record, $obj, $ftpLog)
    {
        foreach ($obj as $oneObj) {
            $this->app['phraseanet.logger']($record->get_databox())
                ->log($record, Session_Logger::EVENT_EXPORTFTP, $ftpLog, '');
        }

        return $this;
    }
}
