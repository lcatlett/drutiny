<?php

namespace Drutiny\Console\Command;

use Drutiny\Plugin\FieldType;
use Drutiny\Settings;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

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
        )
        ->addOption(
            'show',
            null,
            InputOption::VALUE_NONE,
            'Print credential values to the terminal.'
        )
        ;
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $namespace = $input->getArgument('namespace');
        $registry = $this->settings->get('plugin.registry');

        if (!$registry[$namespace]) {
            $io->error("No such plugin found: $namespace.");
            return 1;
        }

        $plugin = $this->container->get($registry[$namespace]);

        if (!$plugin->isInstalled()) {
            $io->error('Plugin is not installed. Run `plugin:setup '.$namespace.'` to install it.');
            return 1;
        }

        foreach ($plugin->getFieldAttributes() as $name => $field) {
            $value = Yaml::dump($plugin->{$name});
            if ($field->type == FieldType::CREDENTIAL && !$input->getOption('show')) {
                $value = str_pad('', strlen($value), '*');
            }
            $rows[] = [$name, $field->type->key(), $value];
        }
        $io->table(['Name', 'Type', 'Value'], $rows);
        return 0;
    }
}
