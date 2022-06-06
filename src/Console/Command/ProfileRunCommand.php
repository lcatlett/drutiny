<?php

namespace Drutiny\Console\Command;

use Async\ForkInterface;
use Async\Exception\ChildExceptionDetected;
use Drutiny\Assessment;
use Drutiny\Profile;
use Drutiny\Policy;
use Drutiny\AssessmentManager;
use Drutiny\Report\FilesystemFormatInterface;
use Drutiny\Target\InvalidTargetException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Run a profile and generate a report.
 */
class ProfileRunCommand extends DrutinyBaseCommand
{
    use ReportingCommandTrait;
    use DomainSourceCommandTrait;
    use LanguageCommandTrait;

    public const EXIT_INVALID_TARGET = 114;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        parent::configure();

        $this
        ->setName('profile:run')
        ->setDescription('Run a profile of checks against a target.')
        ->addArgument(
            'profile',
            InputArgument::REQUIRED,
            'The name of the profile to run.'
        )
        ->addArgument(
            'target',
            InputArgument::REQUIRED,
            'The target to run the policy collection against.'
        )
        ->addOption(
            'uri',
            'l',
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'Provide URLs to run against the target. Useful for multisite installs. Accepts multiple arguments.',
            []
        )
        ->addOption(
            'exit-on-severity',
            'x',
            InputOption::VALUE_OPTIONAL,
            'Send an exit code to the console if a policy of a given severity fails. Defaults to none (exit code 0). (Options: none, low, normal, high, critical)',
            false
        )
        ->addOption(
            'exclude-policy',
            'e',
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'Specify policy names to exclude from the profile that are normally listed.',
            []
        )
        ->addOption(
            'include-policy',
            'p',
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'Specify policy names to include in the profile in addition to those listed in the profile.',
            []
        )
        ->addOption(
            'report-summary',
            null,
            InputOption::VALUE_NONE,
            'Flag to additionally render a summary report for all targets audited.'
        )
        ->addOption(
            'title',
            't',
            InputOption::VALUE_OPTIONAL,
            'Override the title of the profile with the specified value.',
            false
        )
        ;
        $this->configureReporting();
        $this->configureDomainSource();
        $this->configureLanguage();
    }

    /**
     * Prepare profile from input options.
     */
    protected function prepareProfile(InputInterface $input, ProgressBar $progress): Profile
    {
        $profile = $this->getProfileFactory()
          ->loadProfileByName($input->getArgument('profile'))
          ->setReportPerSite(true);

        // Override the title of the profile with the specified value.
        if ($title = $input->getOption('title')) {
            $profile->title = $title;
        }

        $progress->advance();
        $progress->setMessage("Loading policy definitions..");

        // Allow command line to add policies to the profile.
        $included_policies = $input->getOption('include-policy');
        foreach ($included_policies as $policy_name) {
            $this->getLogger()->debug("Loading policy definition: $policy_name");
            $profile->addPolicies([
              $policy_name => ['name' => $policy_name]
            ]);
        }

        // Allow command line omission of policies highlighted in the profile.
        // WARNING: This may remove policy dependants which may make polices behave
        // in strange ways.
        $profile->excluded_policies = $input->getOption('exclude-policy') ?? [];

        $profile->setReportingPeriod($this->getReportingPeriodStart($input), $this->getReportingPeriodEnd($input));

        return $profile;
    }

    /**
     * Load URIs from input options.
     */
    protected function loadUris(InputInterface $input): array
    {
        // Get the URLs.
        $uris = $input->getOption('uri');

        $domains = [];
        foreach ($this->parseDomainSourceOptions($input) as $source => $options) {
            $this->getLogger()->debug("Loading domains from $source.");
            $domains = array_merge($this->getDomainSource()->getDomains($source, $options), $domains);
        }

        if (!empty($domains)) {
            // Merge domains in with the $uris argument.
            // Omit the "default" key that is present by default.
            $uris = array_merge($domains, ($uris === ['default']) ? [] : $uris);
        }
        return empty($uris) ? [null] : $uris;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initLanguage($input);

        $progress = $this->getProgressBar(6);
        $progress->start();

        $progress->setMessage("Loading profile..");
        $profile = $this->prepareProfile($input, $progress);

        $progress->advance();

        $uris = $this->loadUris($input);
        $definitions = $profile->getAllPolicyDefinitions();

        // Reset the progress step tracker.
        $progress->setMaxSteps($progress->getMaxSteps() + count($definitions) + count($uris));

        // Preload policies so they're faster to load later.
        foreach ($profile->getAllPolicyDefinitions() as $policyDefinition) {
            $this->getLogger()->debug("Loading policy from definition: " . $policyDefinition->name);
            $policyDefinition->getPolicy($this->getPolicyFactory());
            $progress->advance();
        }
        $progress->advance();

        $forkManager = $this->getForkManager();
        $forkManager->setAsync(count($uris) > 1);

        $console = new SymfonyStyle($input, $output);
        $target = $input->getArgument('target');

        foreach ($uris as $uri) {
            $forkManager->create()
            ->setLabel(sprintf("Assessment of '%s': %s", $target, $uri))
            ->run(function (ForkInterface $fork) use ($target, $uri, $profile): Assessment {
                $target = $this->getTargetFactory()->create($target, $uri);
                // Check profile dependencies.
                $assessment = $this->getContainer()
                  ->get('assessment')
                  ->setUri($uri ?? '')
                  ->setType('dependencies');
                $assessment->assessTarget(
                    $target,
                    array_map(
                        fn ($p): Policy => $p->getPolicy($this->getPolicyFactory()),
                        $profile->getDependencyDefinitions()
                    ),
                    $profile->getReportingPeriodStart(),
                    $profile->getReportingPeriodEnd()
                );

                // If the dependency policies failed then we should return this
                // assessment rather than following on with the actual profile
                // policies.
                if (!$assessment->isSuccessful()) {
                    return $assessment;
                }

                $this->getLogger()->notice($fork->getLabel());
                return $this->getContainer()
                  ->get('assessment')
                  ->setUri($uri ?? '')
                  ->assessTarget(
                  // Instance of TargetInterface.
                  $target,
                  // Array of Policy objects.
                  array_map(
                      fn ($p): Policy => $p->getPolicy($this->getPolicyFactory()),
                      $profile->getAllPolicyDefinitions()
                  ),
                      $profile->getReportingPeriodStart(),
                      $profile->getReportingPeriodEnd()
                  );
            })
            // Write the report to the provided formats.
            ->onSuccess(function (Assessment $a, ForkInterface $f) use ($profile, $input, $console) {
                // If this wasn't the actual assessment, then it means the target
                // failed a dependency check. We'll render a dependency failure
                // report out to the terminal.
                if ($a->getType() != 'assessment') {
                    $console->error($a->uri() . " failed to meet profile dependencies of {$profile->name}.");
                    $format = $this->getContainer()->get('format.factory')->create('terminal');
                    $format->setDependencyReport();
                    $format->render($profile, $a);
                    // write() must be iterated on to render the report.
                    foreach ($format->write() as $location) {
                    }
                    return;
                }
                foreach ($this->getFormats($input, $profile) as $format) {
                    $format->setNamespace($this->getReportNamespace($input, $a->uri()));
                    $format->render($profile, $a);
                    foreach ($format->write() as $written_location) {
                        $console->success("Writen $written_location");
                    }
                }
            })
            ->onError(function (ChildExceptionDetected $e, ForkInterface $fork) use ($console) {
                $this->getLogger()->error($fork->getLabel()." failed: " . $e->getMessage());
                $console->error($fork->getLabel()." failed: " . $e->getMessage());
            });
        }
        $progress->advance();

        foreach ($forkManager->waitWithUpdates(600) as $remaining) {
            $progress->setMessage(sprintf("%d/%d assessments remaining.", count($uris) - $remaining, count($uris)));
            $progress->display();
        }

        $exit_codes = [0];
        $assessment_manager = new AssessmentManager();

        foreach ($forkManager->getForkResults(true) as $assessment) {
            $progress->advance();
            if ($assessment instanceof Assessment) {
                $assessment_manager->addAssessment($assessment);
                $exit_codes[] = $assessment->getSeverityCode();
            } else {
                // Distinct error code denoting assessment error.
                $exit_codes[] = 5;
            }
        }

        $progress->finish();
        $progress->clear();

        if ($input->getOption('report-summary')) {
            $report_filename = strtr($filepath, [
              'uri' => 'multiple_target',
            ]);

            $format->setOptions([
              'content' => $format->loadTwigTemplate('report/profile.multiple_target')
            ]);
            $format->setOutput(($filepath != 'stdout') ? new StreamOutput(fopen($report_filename, 'w')) : $output);
            $format->render($profile, $assessment_manager)->write();

            if ($filepath != 'stdout') {
                $console->success(sprintf("%s report written to %s", $format->getName(), $report_filename));
            }
        }

        // Do not use a non-zero exit code when no severity is set (Default).
        $exit_severity = $input->getOption('exit-on-severity');
        if ($exit_severity === false) {
            return 0;
        }
        $exit_code = max($exit_codes);

        return $exit_code >= $exit_severity ? $exit_code : 0;
    }
}
