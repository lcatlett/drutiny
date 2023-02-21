<?php

namespace Drutiny\Console\Command;

use Drutiny\Assessment;
use Drutiny\LanguageManager;
use Drutiny\Policy;
use Drutiny\ProfileFactory;
use Drutiny\Report\FormatFactory;
use Drutiny\Target\TargetFactory;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 *
 */
class AuditRunCommand extends DrutinyBaseCommand
{
  use ReportingCommandTrait;
  use LanguageCommandTrait;

  public function __construct(
    protected TargetFactory $targetFactory,
    protected Assessment $assessment,
    protected ProfileFactory $profileFactory,
    protected FormatFactory $formatFactory,
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
        ->setName('audit:run')
        ->setDescription('Run a single audit against a site without a policy.')
        ->addArgument(
            'audit',
            InputArgument::REQUIRED,
            'The PHP class (including namespace) of the audit'
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
            'default'
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
        $this->initLanguage($input);

        $audit_class = $input->getArgument('audit');

        // Fabricate a policy to run the audit.
        $policy = new Policy();
        $policy->setProperties([
          'title' => 'Audit: ' . $audit_class,
          'name' => '_test',
          'class' => $audit_class,
          'description' => 'Verbatim run of an audit class',
          'remediation' => 'none',
          'success' => 'success',
          'failure' => 'failure',
          'warning' => 'warning',
          'uuid' => $audit_class,
          'severity' => 'normal'
        ]);

        // Setup any parameters for the check.
        foreach ($input->getOption('set-parameter') as $option) {
            list($key, $value) = explode('=', $option, 2);

            $info = Yaml::parse($value);

            $policy->addParameter($key, $info);
        }

        // Setup the target.
        $target = $this->targetFactory->create($input->getArgument('target'));

        // Setup the reporting report.
        $start = new \DateTime($input->getOption('reporting-period-start'));
        $end   = new \DateTime($input->getOption('reporting-period-end'));

        $uri = $input->getOption('uri');
        
        // If a URI is provided set it on the Target.
        $target->setUri($uri);

        $this->assessment->setUri($uri);
        $this->assessment->assessTarget($target, [$policy], $this->getReportingPeriodStart($input), $this->getReportingPeriodEnd($input));

        $profile = $this->profileFactory->create([
          'title' => 'Audit:Run',
          'name' => 'audit_run',
          'uuid' => 'audit_run',
          'description' => 'Wrapper profile for audit:run'
        ]);

        foreach ($this->getFormats($input, $profile, $this->formatFactory) as $format) {
            $format->setNamespace($this->getReportNamespace($input, $uri));
            $format->render($profile, $this->assessment);
            foreach ($format->write() as $written_location) {
              // To nothing.
            }
        }

        return $this->assessment->getSeverityCode();
    }
}
