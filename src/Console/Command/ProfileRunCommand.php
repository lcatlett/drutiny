<?php

namespace Drutiny\Console\Command;

use Async\ForkInterface;
use Async\Exception\ChildExceptionDetected;
use Async\ForkManager;
use Drutiny\Profile;
use Drutiny\DomainSource;
use Drutiny\LanguageManager;
use Drutiny\PolicyFactory;
use Drutiny\Profile\FormatDefinition;
use Drutiny\ProfileFactory;
use Drutiny\Report\Format\Terminal;
use Drutiny\Report\FormatFactory;
use Drutiny\Report\Report;
use Drutiny\Report\ReportFactory;
use Drutiny\Report\ReportType;
use Drutiny\Target\TargetFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Run a profile and generate a report.
 */
class ProfileRunCommand extends DrutinyBaseCommand
{
    use ReportingCommandTrait;
    use DomainSourceCommandTrait;
    use LanguageCommandTrait;

    public const EXIT_INVALID_TARGET = 114;

    public function __construct(
        protected PolicyFactory $policyFactory,
        protected ProfileFactory $profileFactory,
        protected ReportFactory $reportFactory,
        protected DomainSource $domainSource,
        protected TargetFactory $targetFactory,
        protected LoggerInterface $logger,
        protected ProgressBar $progressBar,
        protected ForkManager $forkManager,
        protected FormatFactory $formatFactory,
        protected EventDispatcher $eventDispatcher,
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
        $this->configureDomainSource($this->domainSource);
        $this->configureLanguage();
    }

    /**
     * Prepare profile from input options.
     */
    protected function prepareProfile(InputInterface $input): Profile
    {
        $profile = $this->profileFactory->loadProfileByName($input->getArgument('profile'));

        // Override the title of the profile with the specified value.
        if ($title = $input->getOption('title')) {
            $profile = $profile->with(title: $title);
        }

        $this->progressBar->advance();
        $this->progressBar->setMessage("Loading policy definitions..");

        // Allow command line to add policies to the profile.
        $included_policies = $input->getOption('include-policy');
        if (!empty($included_policies)) {
            $extra = array_combine($included_policies, array_pad([], count($included_policies), []));
            $profile = $profile->with(policies: $extra);
        }

        // Allow command line omission of policies highlighted in the profile.
        // WARNING: This may remove policy dependants which may make polices behave
        // in strange ways.
        $excluded_policies = $input->getOption('exclude-policy') ?? [];
        if (!empty($excluded_policies)) {
            $profile = $profile->with(excluded_policies: $excluded_policies);
        }

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
        $target = $this->targetFactory->create($input->getArgument('target'));

        $domains = [];
        foreach ($this->parseDomainSourceOptions($input) as $source => $options) {
            $this->logger->debug("Loading domains from $source.");
            $domains = array_merge($this->domainSource->getDomains($target, $source, $options), $domains);
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

        $this->progressBar->start();

        $this->progressBar->setMessage("Loading profile..");
        $profile = $this->prepareProfile($input, $this->progressBar);

        $this->progressBar->advance();

        $uris = $this->loadUris($input);

        // Reset the progress step tracker.
        $this->progressBar->setMaxSteps($this->progressBar->getMaxSteps() + count($profile->policies) + count($uris));

        $this->forkManager->setAsync($this->forkManager->isAsync() && (count($uris) > 1));

        $console = new SymfonyStyle($input, $output);

        foreach ($uris as $uri) {
            $target = $this->targetFactory->create($input->getArgument('target'), $uri);
            $fork = $this->forkManager->create();
            $fork->setLabel(sprintf("Assessment of '%s': %s", $target->getId(), $uri));
            $fork->run(fn() => $this->reportFactory->create(
                profile: $profile,
                target: $target
            ));

            // Write the report to the provided formats.
            $fork->onSuccess(fn (Report $report) => $this->formatReport($report, $console, $input));

            $fork->onError(function (ChildExceptionDetected $e, ForkInterface $fork) use ($console) {
                $this->logger->error($fork->getLabel()." failed: " . $e->getMessage());
                $console->error($fork->getLabel()." failed: " . $e->getMessage());
            });
        }
        $this->progressBar->advance();

        foreach ($this->forkManager->waitWithUpdates(600) as $remaining) {
            $this->progressBar->setMessage(sprintf("%d/%d assessments remaining.", count($uris) - $remaining, count($uris)));
            $this->progressBar->display();
        }

        $exit_codes = [0];

        foreach ($this->forkManager->getForkResults(true) as $report) {
            $this->progressBar->advance();
            if ($report instanceof Report) {
                $exit_codes[] = $report->successful ? 0 : $report->severity->getWeight();
            } else {
                // Distinct error code denoting assessment error. Audit errors are < 16.
                $exit_codes[] = 17;
            }
        }

        $this->progressBar->finish();
        $this->progressBar->clear();

        // if ($input->getOption('report-summary')) {
        //     $report_filename = strtr($filepath, [
        //       'uri' => 'multiple_target',
        //     ]);

        //     $format->setOptions([
        //       'content' => $format->loadTwigTemplate('report/profile.multiple_target')
        //     ]);
        //     $format->setOutput(($filepath != 'stdout') ? new StreamOutput(fopen($report_filename, 'w')) : $output);
        //     $format->render($profile, $assessment_manager)->write();

        //     if ($filepath != 'stdout') {
        //         $console->success(sprintf("%s report written to %s", $format->getName(), $report_filename));
        //     }
        // }

        // Do not use a non-zero exit code when no severity is set (Default).
        $exit_severity = $input->getOption('exit-on-severity');
        if ($exit_severity === false) {
            return 0;
        }
        $exit_code = max($exit_codes);

        return $exit_code >= $exit_severity ? $exit_code : 0;
    }

    protected function formatReport(Report $report, SymfonyStyle $console, InputInterface $input) {
        // If this wasn't the actual assessment, then it means the target
        // failed a dependency check. We'll render a dependency failure
        // report out to the terminal.
        if ($report->type == ReportType::DEPENDENCIES) {
            $console->error($report->uri . " failed to meet profile dependencies of {$report->profile->name}.");
            $format = $this->formatFactory->create('terminal', new FormatDefinition('terminal'));
            if ($format instanceof Terminal) {
                $format->setDependencyReport();
            }
            $formats = [$format];
        }
        else {
            $formats = $this->getFormats($input, $report->profile, $this->formatFactory);
        }
        foreach ($formats as $format) {
            $format->setNamespace($this->getReportNamespace($input, $report->uri));
            $format->render($report);
            foreach ($format->write() as $written_location) {
                $console->success("Writen $written_location");
            }
        }
    }
}
