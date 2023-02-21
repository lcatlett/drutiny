<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Drutiny\Console\Command;

use Drutiny\Settings;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Command\Command;

/**
 * A console command for autowiring information.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 *
 * @internal
 */
class ConfigGetCommand extends Command
{
    public function __construct(protected Settings $settings)
    {
        parent::__construct();
    }
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('config:get')
            ->setDescription('Lists configuration')
            ->setHelp('List all the configuraiton inside Drutiny')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Configuration');
        $io->text('The following configuration can be customised:');

        $config = $this->settings->getAll();
        ksort($config);

        $rows = [];
        foreach ($config as $key => $value) {
          $rows[] = [$key, Yaml::dump($value)];
        }

        $io->table(['Key', 'Value (YAML formatted)'], $rows);

        return 0;
    }
}
