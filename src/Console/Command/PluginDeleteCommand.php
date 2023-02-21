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
class PluginDeleteCommand extends Command
{
    public function __construct(protected ContainerInterface $container, protected Settings $settings)
    {
        parent::__construct();
    }

  /**
   * @inheritdoc
   */
    protected function configure()
    {
        $this
        ->setName('plugin:delete')
        ->setDescription('Delete the configuration of a plugin.')
        ->addArgument(
            'namespace',
            InputArgument::REQUIRED,
            'The plugin name.',
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

        $plugin->delete();
        $io->success("Plugin configuration removed.");

        return 0;
    }
}
