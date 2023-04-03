<?php

namespace Drutiny\Console\Command;

use Monolog\Handler\StreamHandler;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

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
    protected function execute(InputInterface $input, OutputInterface $output):int
    {
        if ($input->getOption('tail')) {
          $process = Process::fromShellCommandline(sprintf('tail -f -n 20 %s', $this->logFile->getUrl()));
          // Set timeout till log rotates (by day).
          $process->setTimeout(strtotime('tomorrow') - time());
          $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
          });
        }
        else {
          $process = Process::fromShellCommandline(sprintf('cat %s', $this->logFile->getUrl()));
          $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
          });
        }
        return 0;
    }
}
