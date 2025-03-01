<?php

namespace Bolt\Extension\MichaelMezger\Espocrm\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Bolt\Extension\MichaelMezger\Espocrm\Importer\EspocrmImporter;
use GuzzleHttp\Client;
use Bolt\Nut\BaseCommand;
use Symfony\Component\Console\Logger\ConsoleLogger;


class EspocrmImportCommand extends BaseCommand
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('espocrm:import') // Der Nut-Befehl
            ->setDescription('Import Contents von EspoCRM');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $guzzleClient = new Client([
            'base_uri' => $this->config['espocrm_url'],
            'timeout'  => 5.0,
            'headers'  => [
                'X-Api-Key' => $this->config['api_key'],
                'Accept' => 'application/json',
            ]
        ]);

        $espocrmImporter = new EspocrmImporter(
            $guzzleClient,
            $this->app['storage'],
            $this->app['filesystem'],
            new ConsoleLogger($output)
        );
        $espocrmImporter->import();

        return 0; // Erfolgreiche Ausführung
    }
}
