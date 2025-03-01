<?php

namespace Bolt\Extension\MichaelMezger\Espocrm;

use Silex\Application;
use Bolt\Extension\SimpleExtension;
use Bolt\Extension\MichaelMezger\Espocrm\Command\EspocrmImportCommand;

/**
 * EspocrmExtension extension class.
 *
 * @author Michael Mezger
 */
class EspocrmExtension extends SimpleExtension
{
    public function boot(Application $app)
    {
        $this->addConsoleCommand(new EspocrmImportCommand($this->getConfig()));
    }
}
