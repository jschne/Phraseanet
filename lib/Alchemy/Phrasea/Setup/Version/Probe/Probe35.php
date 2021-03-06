<?php

/*
 * This file is part of Phraseanet
 *
 * (c) 2005-2013 Alchemy
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Alchemy\Phrasea\Setup\Version\Probe;

use Alchemy\Phrasea\Application;
use Alchemy\Phrasea\Setup\Version\Migration\Migration35;

class Probe35 implements ProbeInterface
{
    private $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * {@inheritdoc}
     */
    public function isMigrable()
    {
        $oldFilesExist = is_file(__DIR__ . "/../../../../../../config/connexion.inc")
            && is_file(__DIR__ . "/../../../../../../config/config.inc");

        if ($oldFilesExist) {
            if (!is_file(__DIR__ . "/../../../../../../config/config.yml")) {
                return true;
            }
            // previous upgrade did not rename these files
            rename(__DIR__ . "/../../../../../../config/connexion.inc", __DIR__ . "/../../../../../../config/connexion.inc.old");
            rename(__DIR__ . "/../../../../../../config/config.inc", __DIR__ . "/../../../../../../config/config.inc.old");

            return false;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getMigration()
    {
        return new Migration35($this->app);
    }
}
