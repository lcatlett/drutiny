<?php

namespace Drutiny\Console\Command;

use Drutiny\Target\TargetFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

/**
 *
 */
class TargetMetadataCommand extends DrutinyBaseCommand
{
  public function __construct(
    protected TargetFactory $targetFactory,
    protected LoggerInterface $logger,
    protected ProgressBar $progressBar
  )
  {
    parent::__construct();
  }

  /**
   * @inheritdoc
   */
    protected function configure()
    {
        $this
        ->setName('target:info')
        ->setDescription('Display metatdata about a target.')
        ->addArgument(
            'target',
            InputArgument::REQUIRED,
            'A target reference.'
        )
        ->addOption(
            'uri',
            'l',
            InputOption::VALUE_OPTIONAL,
            'Provide URLs to run against the target. Useful for multisite installs. Accepts multiple arguments.',
            false
        );
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->progressBar->start();
        $this->progressBar->setMessage("Loading target..");
        $this->progressBar->advance();
        
        $target = $this->targetFactory->create(
          $input->getArgument('target'), 
          $input->getOption('uri')
        );

        $this->progressBar->advance();

        $io = new SymfonyStyle($input, $output);

        $rows = [];

        foreach ($target->getPropertyList() as $key) {
          $value = $target->getProperty($key);
          $value = is_object($value) ? '<object> (' . get_class($value) . ')'  : '<'.gettype($value) . '> ' . Yaml::dump($value, 8, 2);
          if (strlen($value) > 1024) {
            $value = substr($value, 0, 1024) . '...';
          }
          $rows[] = [$key, $value];
        }

        $this->progressBar->finish();
        $io->table(['Property', 'Value'], $rows);

        return 0;
    }
}
