<?php

namespace Drutiny\Console\Command;

use Drutiny\Settings;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 */
class PluginSetupCommand extends Command
{
    public function __construct(protected Settings $settings, protected ContainerInterface $container)
    {
        parent::__construct();
    }

  /**
   * @inheritdoc
   */
    protected function configure()
    {
        $this
        ->setName('plugin:setup')
        ->setDescription('Register credentials against an API drutiny integrates with.')
        ->addArgument(
            'namespace',
            InputArgument::REQUIRED,
            'The service to authenticate against.',
        );
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $namespace = $input->getArgument('namespace');

        foreach ($this->settings->get('plugin.registry') as $id) {
            $plugin = $this->container->get($id);
            if ($plugin->getName() == $namespace) {
              break;
            }
        }

        if ($plugin->getName() != $namespace) {
            $io->error("No such plugin found: $namespace.");
            return 1;
        }

        $plugin->setup();

        $io->success("Credentials for $namespace have been saved.");
        return 0;
    }
}
