<?php

namespace Drutiny\Console\Command;

use Drutiny\LanguageManager;
use Drutiny\PolicyFactory;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Drutiny\PolicySource\PushablePolicySourceInterface;

/**
 *
 */
class PolicySourcesCommand extends DrutinyBaseCommand
{
    use LanguageCommandTrait;

    public function __construct(
      protected PolicyFactory $policyFactory,
      protected LanguageManager $languageManager
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
        ->setName('policy:sources')
        ->setDescription('Show all policy sources.');
        $this->configureLanguage();
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        foreach ($this->policyFactory->getSources() as $source) {
          $pushable = ($source->getDriver() instanceof PushablePolicySourceInterface) ? 'Yes' : 'No';
          $rows[] = [$source->getName(), get_class($source->getDriver()), $source->getWeight(), $pushable];
        }

        $io = new SymfonyStyle($input, $output);
        $headers = ['Source', 'Class', 'Weight', 'Pushable'];
        $io->table($headers, $rows);

        return 0;
    }

  /**
   *
   */
    protected function formatDescription($text)
    {
        $lines = explode(PHP_EOL, $text);
        $text = implode(' ', $lines);
        return wordwrap($text, 50);
    }
}
