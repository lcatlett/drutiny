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
        $registry = $this->settings->get('plugin.registry');
        
        if (!isset($registry[$namespace])) {
            $io->error("No such plugin found: $namespace.");
            return 1;
        }

        $plugin = $this->container->get($registry[$namespace]);

        if (!$plugin->isInstalled()) {
            $io->error('Plugin is not installed. Run `plugin:setup '.$namespace.'` to install it.');
            return 1;
        }

        if (!$io->confirm("Are you sure you want to uninstall the '$namespace' plugin?")) {
            return 0;
        }

        $plugin->delete();
        $io->success("Plugin configuration removed.");

        return 0;
    }
}
