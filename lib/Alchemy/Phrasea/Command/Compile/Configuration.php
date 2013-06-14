<?php

/*
 * This file is part of Phraseanet
 *
 * (c) 2005-2013 Alchemy
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Alchemy\Phrasea\Command\Compile;

use Alchemy\Phrasea\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Configuration extends Command
{
    public function __construct()
    {
        parent::__construct('compile:configuration');
        $this->setDescription('Compile configuration');
    }

    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        $this->container['phraseanet.configuration']->compileAndWrite();
        $output->writeln("Confguration compiled.");
        
        return 0;
    }
}