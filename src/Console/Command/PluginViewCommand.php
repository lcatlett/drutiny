<?php

namespace Drutiny\Console\Command;

use Drutiny\Settings;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 */
class PluginViewCommand extends Command
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
        ->setName('plugin:view')
        ->setDescription('View configuration of a particular plugin.')
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

        $config = $plugin->load();
        foreach (array_keys($config) as $key) {
          $rows[] = [$key, $config[$key]];
        }
        $io->table(['Name', 'Value'], $rows);

        return 0;
    }
}
