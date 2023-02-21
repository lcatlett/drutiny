<?php

namespace Drutiny\Console\Command;

use Drutiny\DomainSource;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Application;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Command\Command;

/**
 * Run a profile and generate a report.
 */
class DomainSourceListCommand extends Command
{
    protected $domainSourceOptions;


    public function __construct(
        protected DomainSource $domainSource, 
        protected LoggerInterface $logger
    )
    {
        parent::__construct();
    }

  /**
   * @inheritdoc
   */
    protected function configure()
    {
        parent::configure();

        $this
        ->setName('domain-source:list')
        ->setHidden()
        ->setDescription('List domains from a given source.');
        // Build a way for the command line to specify the options to derive
        // domains from their sources.
        foreach ($this->domainSource->getSources() as $driver => $properties) {
            foreach ($properties as $name => $description) {
                $this->domainSourceOptions[] = $driver . '-' . $name;
                $this->addOption(
                    $driver . '-' . $name,
                    null,
                    InputOption::VALUE_OPTIONAL,
                    $description
                );
            }
        }
    }

  /**
   * {@inheritdoc}
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $console = new SymfonyStyle($input, $output);

        $domains = [];
        $unique_domains = [];
        foreach ($this->parseDomainSourceOptions($input) as $source => $options) {
            $this->logger->notice("Loading domains from $source.");
            foreach ($this->domainSource->getDomains($source, $options) as $domain) {
              $domains[] = [$source, $domain, isset($unique_domains[$domain]) ? implode(',', $unique_domains[$domain]) : ''];
              $unique_domains[$domain][] = $source;
            }
        }

        $console->table(['Source', 'Domain', 'Other sources'], $domains);
        $console->success(sprintf("Domain sources returned %s domains of which %s are unique.", count($domains), count($unique_domains)));

        return 0;
    }

    protected function parseDomainSourceOptions(InputInterface $input):array
    {
      // Load additional uris from domain-source
        $sources = [];
        foreach ($this->domainSourceOptions as $param) {
            $value = $input->getOption($param);

            if ($value === null) {
                continue;
            }

            if (strpos($param, '-') === false) {
                continue;
            }
            list($source, $name) = explode('-', $param, 2);
            $sources[$source][$name] = $value;
        }
        return $sources;
    }
}
