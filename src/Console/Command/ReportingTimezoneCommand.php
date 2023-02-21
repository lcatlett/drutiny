<?php

namespace Drutiny\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 *
 */
class ReportingTimezoneCommand extends DrutinyBaseCommand
{

  /**
   * @inheritdoc
   */
    protected function configure()
    {
        $this
        ->setName('reporting:timezones')
        ->setDescription('List all available timezone identifiers.')
        ->addArgument(
            'country_code',
            InputArgument::OPTIONAL,
            'The country code to list timezones for.'
        );
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $mode = $input->getArgument('country_code') ? \DateTimeZone::PER_COUNTRY : \DateTimeZone::ALL;

        $rows = array_map(fn ($r) => [$r], \DateTimeZone::listIdentifiers($mode, $input->getArgument('country_code')));
        $io->table(['Timezone'], $rows);
        return 0;
    }
}
