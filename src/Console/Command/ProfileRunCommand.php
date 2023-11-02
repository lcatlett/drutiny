<?php

namespace Drutiny\Console\Command;

use Drutiny\Console\ProcessManager;
use Drutiny\Profile;
use Drutiny\DomainSource;
use Drutiny\LanguageManager;
use Drutiny\PolicyFactory;
use Drutiny\ProfileFactory;
use Drutiny\Report\FormatFactory;
use Drutiny\Report\Report;
use Drutiny\Report\ReportFactory;
use Drutiny\Report\StoreFactory;
use Drutiny\Target\Exception\InvalidTargetException;
use Drutiny\Target\Exception\TargetLoadingException;
use Drutiny\Target\Exception\TargetNotFoundException;
use Drutiny\Target\TargetFactory;
use Drutiny\Target\TargetInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Process\Process;

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
        protected FormatFactory $formatFactory,
        protected EventDispatcher $eventDispatcher,
        protected StoreFactory $storeFactory,
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
        $this->progressBar->setMessage("Loading profile..");
        $this->progressBar->display();
        $profile = $this->profileFactory->loadProfileByName($input->getArgument('profile'));

        // Override the title of the profile with the specified value.
        if ($title = $input->getOption('title')) {
            $profile = $profile->with(title: $title);
        }

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
        $this->progressBar->advance();

        return $profile;
    }

    /**
     * Load URIs from input options.
     */
    protected function loadUris(InputInterface $input): array
    {
        // Get the URLs.
        $this->progressBar->setMessage("Loading URIs..");
        $this->progressBar->display();

        $uris = $input->getOption('uri');

        $domains = [];
        foreach ($this->parseDomainSourceOptions($input) as $source => $options) {
            $this->logger->debug("Loading domains from $source.");
            $target ??= $this->targetFactory->create($input->getArgument('target'));
            $domains = array_merge($this->domainSource->getDomains($target, $source, $options), $domains);
        }

        if (!empty($domains)) {
            // Merge domains in with the $uris argument.
            // Omit the "default" key that is present by default.
            $uris = array_merge($domains, ($uris === ['default']) ? [] : $uris);
        }
        $this->progressBar->advance();
        return empty($uris) ? [null] : $uris;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initLanguage($input);

        $this->progressBar->start(2);

        $profile = $this->prepareProfile($input, $this->progressBar);
        $uris = $this->loadUris($input);

        // Reset the progress step tracker.
        $this->progressBar->setMaxSteps($this->progressBar->getMaxSteps() + count($uris));

        $console = new SymfonyStyle($input, $output);

        $exit_codes = [Command::SUCCESS];

        foreach ($uris as $uri) {
            $this->progressBar->setMessage("Running {$profile->title} on target $uri...");
            $this->progressBar->display();

            try {
                $target = $this->targetFactory->create($input->getArgument('target'), $uri);
            }
            catch (TargetLoadingException | TargetNotFoundException | InvalidTargetException $e) {
                $console->error($e->getMessage());
                $exit_codes[] = $e::ERROR_CODE;
                continue;
            }

            try {
                $report = $this->reportFactory->promise(
                    profile: $profile,
                    target: $target
                );

                if ($report instanceof ProcessManager) {
                    if ($output instanceof ConsoleOutput && !$input->getOption('no-interaction')) {
                        $report = $this->waitForReport($report, $output, $target);
                    }
                    else {
                        while (!$report->hasFinished()) {
                            $total = $report->length();
                            $active = count($report->getActive());
                            $complete = count($report->getCompleted());
                            $this->progressBar->setMaxSteps($total);
                            $this->progressBar->setProgress($complete);
                            $this->progressBar->setMessage(date('H:i:s') . " $active in progress. $complete/$total complete.");
                            $this->progressBar->display();
                            sleep(1);
                            $report->update();
                        }
                        $report = $report->resolve();
                    }
                }
    
                $this->formatReport($report, $console, $input);
                $exit_codes[] = $report->successful ? 0 : $report->severity->getWeight();
            }
            catch (\Exception $e) {
                $this->logger->error("{$profile->name} audit on $uri failed: " . $e->getMessage());
                $console->error("{$profile->name} audit on $uri failed: " . $e->getMessage());
                $exit_codes[] = 17;
                throw $e;
            }
            catch (\Error $e) {
                $this->logger->error("{$profile->name} audit on $uri failed: " . $e->getMessage());
                $console->error("{$profile->name} audit on $uri failed: " . $e->getMessage());
                $exit_codes[] = 17;
                throw $e;
            }
            finally {
                $this->progressBar->advance();
            }
        }

        $this->progressBar->finish();
        $this->progressBar->clear();

        // Do not use a non-zero exit code when no severity is set (Default).
        $exit_severity = $input->getOption('exit-on-severity');
        if ($exit_severity === false) {
            return Command::SUCCESS;
        }
        $exit_code = max($exit_codes);

        return $exit_code >= $exit_severity ? $exit_code : Command::SUCCESS;
    }

    /**
     * Wait for a streaming report to resolve.
     */
    protected function waitForReport(ProcessManager $report, ConsoleOutput $output, TargetInterface $target): Report {
        $table_output = $output->section();
        $table = new Table($table_output);
        $table->setHeaders([$target['domain'], 'Status']);
        // Create a section for each process in the report.
        /**
         * @var Array[]
         */
        $rows = $report->map(function (Process $process, string $policy) use ($output) {
            $policies = explode(', ', $policy);
            $tag = array_shift($policies);
            if (count($policies)) {
                $tag .= ' and ' . count($policies) . ' more';
            }
            return [$tag, '<fg=yellow>Waiting to start</>'];
        });
        $keys = array_keys($rows);
        $rows = array_values($rows);
        $table->setRows($rows);
        $table->render();

        $status = [];

        while (!$report->hasFinished()) {
            $updated = false;

            $active_pids = [];
            $active = $report->getActive();
            foreach ($active as $name => $process) {
                $row_id = array_search($name, $keys);
                if (!isset($status[$name])) {
                    $rows[$row_id][1] = 'Running policy';
                    $table->setRow($row_id, $rows[$row_id]);
                    $status[$name] = $process->getPid();
                    $updated = true;
                }
                $active_pids[] = $process->getPid();

                // $stderr = $process->getIncrementalErrorOutput();
                // if (!empty($stderr)) {
                //     $lines = array_filter(explode(PHP_EOL, trim($stderr)));
                //     $rows[$row_id][1] = array_pop($lines);
                //     $table->setRow($row_id, $rows[$row_id]);
                //     $process->clearErrorOutput();
                //     $updated = true;
                // }
            }
            $completed = $report->getCompleted();

            foreach ($completed as $name => $process) {
                if (is_bool($status[$name])) {
                    continue;
                }
                $row_id = array_search($name, $keys);
                $rows[$row_id][1] =  $process->isSuccessful() ? '<fg=green>Complete</>' : '<fg=red>An error occured</>';
                $table->setRow($row_id, $rows[$row_id]);
                $status[$name] = $process->isSuccessful();
                $updated = true;
            }

            if ($table_output instanceof ConsoleSectionOutput && $updated) {
                $clear_lines = 5 + count($rows);
                $table_output->clear($clear_lines);
                $table->render();
                $table_output->writeln(count($active) . ' Active. ' . count($completed) . ' Completed');
            }
            
            sleep(1);
            $report->update();
        }

        $clear_lines = 5 + count($rows);
        $table_output->clear($clear_lines);

        /**
         * @var \Drutiny\Report
         */
        return $report->resolve();
    }
}
