<?php

namespace Drutiny\Console\Command;

use Drutiny\Assessment;
use Drutiny\LanguageManager;
use Drutiny\PolicyFactory;
use Drutiny\ProfileFactory;
use Drutiny\Report\FormatFactory;
use Drutiny\Target\TargetFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 *
 */
class KeywordAuditCommand extends DrutinyBaseCommand
{
  use ReportingCommandTrait;
  use LanguageCommandTrait;

  public function __construct(
    protected ProfileFactory $profileFactory,
    protected PolicyFactory $policyFactory,
    protected TargetFactory $targetFactory,
    protected Assessment $assessment,
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
        ->setName('keyword:audit')
        ->setDescription('Run a single policy audit against a site.')
        ->addArgument(
            'target',
            InputArgument::REQUIRED,
            'The target to run the check against.'
        )
        ->addOption(
            'keyword',
            'k',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'A keyword to collect policies by.'
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

        $profile = $this->profileFactory->create([
          'title' => 'Keyword audit: ',
          'name' => '_keyword_audit',
          'uuid' => '_keyword_audit',
          'description' => 'Wrapper profile for keyword:audit',
          'format' => [
            'terminal' => [
              'content' => "
              {% block audit %}
                {% for response in assessment.getResults %}
                    {{ policy_result(response, assessment) }}
                {% endfor %}
              {% endblock %}"
            ]
          ]
        ]);

        $list = [];
        foreach ($input->getOption('keyword') as $keyword) {
            $list += $this->policyFactory->getPolicyListByKeyword($keyword);
        }

        $unique_policies = [];
        foreach ($list as $policy) {
            $unique_policies[$policy['name']] = [];
        }
        
        $profile->addPolicies($unique_policies);
 
        // Setup the target.
        $target = $this->targetFactory->create($input->getArgument('target'), $input->getOption('uri'));

        // Get the URLs.
        if ($uri = $input->getOption('uri')) {
          $target->setUri($uri);
        }

        $profile->setReportingPeriod($this->getReportingPeriodStart($input), $this->getReportingPeriodEnd($input));

        $policies = [];
        $this->progressBar->setMessage("Loading policy definitions...");
        foreach ($profile->getAllPolicyDefinitions() as $definition) {
            $policies[] = $definition->getPolicy($this->policyFactory);
        }

        $this->progressBar->setMessage("Assessing target...");

        $this->assessment
          ->setUri($uri)
          ->assessTarget($target, $policies, $profile->getReportingPeriodStart(), $profile->getReportingPeriodEnd());

        $this->progressBar->finish();
        $this->progressBar->clear();

        foreach ($this->getFormats($input, $profile, $this->formatFactory) as $format) {
            $format->setNamespace($this->getReportNamespace($input, $uri));
            $format->render($profile, $this->assessment);
            foreach ($format->write() as $location) {
              $output->writeln("Policy Audit written to $location.");
            }
        }
        $output->writeln("Keyword Audit Complete.");

        // Do not use a non-zero exit code when no severity is set (Default).
        $exit_severity = $input->getOption('exit-on-severity');
        if ($exit_severity === FALSE) {
            return 0;
        }
        $this->logger->info("Exiting with max severity code.");

        // Return the max severity as the exit code.
        $exit_code = $this->assessment->getSeverityCode();

        return $exit_code >= $exit_severity ? $exit_code : 0;
    }
}
