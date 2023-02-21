<?php

namespace Drutiny\Console\Command;

use Monolog\Handler\StreamHandler;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *
 */
class LogsCommand extends DrutinyBaseCommand
{
  public function __construct(protected StreamHandler $logFile)
  {
    parent::__construct();
  }

  /**
   * @inheritdoc
   */
    protected function configure()
    {
        $this
        ->setName('logs')
        ->setDescription('Show recent logs from current day.')
        ->addOption(
          'tail',
          'f',
          InputOption::VALUE_NONE
        );
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('tail')) {
          passthru(sprintf('tail -f -n 20 %s', $this->logFile->getUrl()));
        }
        else {
          passthru(sprintf('cat %s', $this->logFile->getUrl()));
        }
        return 0;
    }
}
