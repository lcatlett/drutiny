<?php

namespace Drutiny\Console\Command;

use Drutiny\LanguageManager;
use Drutiny\PolicyFactory;
use Drutiny\ProfileFactory;
use Drutiny\Report\FormatFactory;
use Drutiny\Report\ReportFactory;
use Drutiny\Target\TargetFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

/**
 *
 */
class PolicyAuditCommand extends DrutinyBaseCommand
{
  use ReportingCommandTrait;
  use LanguageCommandTrait;

  public function __construct(
    protected ProfileFactory $profileFactory,
    protected PolicyFactory $policyFactory,
    protected TargetFactory $targetFactory,
    protected ReportFactory $reportFactory,
    protected FormatFactory $formatFactory,
    protected LoggerInterface $logger,
    protected ProgressBar $progressBar,
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
        ->setName('policy:audit')
        ->setDescription('Run a single policy audit against a site.')
        ->addArgument(
            'policy',
            InputArgument::REQUIRED,
            'The name of the check to run.'
        )
        ->addArgument(
            'target',
            InputArgument::REQUIRED,
            'The target to run the check against.'
        )
        ->addOption(
            'set-parameter',
            'p',
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'Set parameters for the check.',
            []
        )
        ->addOption(
            'uri',
            'l',
            InputOption::VALUE_OPTIONAL,
            'Provide URLs to run against the target. Useful for multisite installs. Accepts multiple arguments.',
            false
        )
        ->addOption(
            'exit-on-severity',
            'x',
            InputOption::VALUE_OPTIONAL,
            'Send an exit code to the console if a policy of a given severity fails. Defaults to none (exit code 0). (Options: none, low, normal, high, critical)',
            FALSE
        );
        parent::configure();
        $this->configureReporting();
        $this->configureLanguage();
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->progressBar->start();
        $this->initLanguage($input);

        $name = $input->getArgument('policy');

        // Setup any parameters for the check.
        $parameters = [];
        foreach ($input->getOption('set-parameter') as $option) {
            list($key, $value) = explode('=', $option, 2);
            // Using Yaml::parse to ensure datatype is correct.
            $parameters[$key] = Yaml::parse($value);
        }

        $policy_definition = [$name => [
          'name' => $name,
          'parameters' => $parameters
        ]];

        $this->progressBar->setMessage("Loading profile...");

        $profile = $this->profileFactory->create([
          'title' => 'Policy audit: ' . $name,
          'name' => '_policy_audit',
          'uuid' => '_policy_audit',
          'source' => 'policy:audit',
          'description' => 'Wrapper profile for policy:audit',
          'policies' => $policy_definition,
          'format' => [
            'terminal' => [
              'content' => "
              {% block audit %}
                {{ policy_result(assessment.getPolicyResult('$name'), assessment) }}
              {% endblock %}"
            ]
          ]
        ]);

        $this->progressBar->setMessage("Loading target...");

        // Setup the target.
        $target = $this->targetFactory->create($input->getArgument('target'), $input->getOption('uri'));

        // Get the URLs.
        if ($uri = $input->getOption('uri')) {
          $target->setUri($uri);
        }

        $profile->setReportingPeriod($this->getReportingPeriodStart($input), $this->getReportingPeriodEnd($input));

        $policies = [];
        $this->progressBar->setMessage("Loading policy definitions...");
        foreach ($profile->policies as $definition) {
            $policies[] = $definition->getPolicy($this->policyFactory);
        }

        $this->progressBar->setMessage("Assessing target...");

        $report = $this->reportFactory->create($profile, $target);

        $this->progressBar->finish();
        $this->progressBar->clear();

        $style = new SymfonyStyle($input, $output);
        if ($report->results[$name]->isIrrelevant()) {
          $style->warning("Policy $name was evaluated as irrelevant for the target " . $target->getId());
          if (isset($report->results[$name]->tokens['exception'])) {
            $style->error($report->results[$name]->tokens['exception']);
          }
          return 0;
        }
        elseif ($report->results[$name]->hasError()) {
          $style->error("Policy $name has an error for the target " . $target->getId());
          $tokens = $report->results[$name]->tokens;
          $style->error($tokens['exception_type'] .': '.$tokens['exception'] . ' in ' . $tokens['file'] . ' on line ' . $tokens['line']);
          $style->error(implode(PHP_EOL, $tokens['trace']));
          return 1;
        }

        foreach ($this->getFormats($input, $profile, $this->formatFactory) as $format) {
            $format->setNamespace($this->getReportNamespace($input, $uri));
            $format->render($report);
            foreach ($format->write() as $location) {
              $output->writeln("Policy Audit written to $location.");
            }
        }
        $output->writeln("Policy Audit Complete.");

        // Do not use a non-zero exit code when no severity is set (Default).
        $exit_severity = $input->getOption('exit-on-severity');
        if ($exit_severity === FALSE) {
            return 0;
        }
        $this->logger->info("Exiting with max severity code.");

        // Return the max severity as the exit code.
        $exit_code = $report->severity->getWeight();

        return $exit_code >= $exit_severity ? $exit_code : 0;
    }
}
