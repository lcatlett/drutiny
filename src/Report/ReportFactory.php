<?php

namespace Drutiny\Report;

use Async\Exception\ChildExceptionDetected;
use Async\ForkInterface;
use Async\ForkManager;
use DateTime;
use DateTimeInterface;
use Drutiny\Audit\AuditInterface;
use Drutiny\Audit\SyntaxProcessor;
use Drutiny\AuditFactory;
use Drutiny\AuditResponse\AuditResponse;
use Drutiny\AuditResponse\State;
use Drutiny\Policy;
use Drutiny\Policy\Dependency;
use Drutiny\Policy\DependencyBehaviour;
use Drutiny\Policy\DependencyException;
use Drutiny\PolicyFactory;
use Drutiny\Profile;
use Drutiny\Profile\PolicyDefinition;
use Drutiny\Target\TargetInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Twig\Error\RuntimeError;
use UnexpectedValueException;

class ReportFactory {
    public function __construct(
        protected LoggerInterface $logger,
        protected ProgressBar $progressBar, 
        protected ForkManager $forkManager,
        protected AuditFactory $auditFactory,
        protected PolicyFactory $policyFactory,
        protected SyntaxProcessor $syntaxProcessor,
    )
    {}

    /**
     * Execute audits from profile policies and present the results as a Report object.
     */
    public function create(
        Profile $profile,
        TargetInterface $target
    ):Report
    {
        $contexts = $this->buildContexts($target);

        $report = new Report(
            uri: $target->uri,
            results: $this->auditPolicies($contexts, $target, $profile->reportingPeriodStart, $profile->reportingPeriodEnd, ...$profile->dependencies),
            type: ReportType::DEPENDENCIES,
            profile: $profile,
            target: $target
        );
        
        $report = !$report->successful ? $report : new Report(
            uri: $target->uri,
            results: $this->auditPolicies($contexts, $target, $profile->reportingPeriodStart, $profile->reportingPeriodEnd, ...$profile->policies),
            type: ReportType::ASSESSMENT,
            profile: $profile,
            target: $target
        );

        return $report;
    }

    /**
     * Audit a batch of policies against a common target using the ForkManager.
     */
    private function auditPolicies(array $contexts, TargetInterface $target, DateTime $start, DateTime $end, PolicyDefinition ...$definitions)
    {
        $batch = [];
        $early_results = [];

        // Dependencies must be met for a policy to be audited.
        // If they generate a response then that response is used 
        // as the policy's AuditResponse and the policy is omitted from auditing.
        foreach ($definitions as $definition) {
            $policy = $definition->getPolicy($this->policyFactory);
            if ($response = $this->getDependencyResponse($contexts, $policy)) {
                $early_results[] = $response;
                continue;
            }
            $batch[$policy->class][$policy->name] = $policy;
        }
        
        $errors = [];

        // Batch audit policies by common classes. This allows audit classes
        // to optimize their auditing by the batch of policies being audited.
        foreach ($batch as $class => $policies) {
            $audit = $this->auditFactory->mock($class, $target);
            $audit->setReportingPeriod($start, $end);
            array_walk($policies, fn($p) => $audit->prepare($p));

            foreach ($policies as $policy) {
                // Audit each policy inside its own fork.
                $this->forkPolicyAudit($policy, $audit, $start, $end)
                     ->onError(fn($e, $f) => $errors[] = $this->handleForkError($e, $f, $policy));
            }
        }

        // Wait for audit forks to return... 
        foreach ($this->forkManager->waitWithUpdates(400) as $remaining) {
            $this->progressBar->setMessage(sprintf("%d/%d policy audits remaining for %s.", count($batch) - $remaining, count($batch), $target->uri));
            $this->progressBar->display();
        }
        $results = array_merge($this->forkManager->getForkResults(), $early_results);
        $returned = count($results);
        $total = count($definitions);
        $error_count = count($errors);
        $this->logger->info("Assessment returned $returned/$total with $error_count errors from the fork manager.");
        
        // Merge results and errors together as result set.
        return array_merge($errors, $results);
    }

    /**
     * Build an AuditResponse on fork errors.
     */
    private function handleForkError(ChildExceptionDetected $e, ForkInterface $fork, Policy $policy):AuditResponse
    {
        $err_msg = $e->getMessage();
        $this->logger->error('Fork error: ' . $fork->getLabel().': '.$err_msg);

        // Capture the error as a policy error outcome.
        return new AuditResponse(
            policy: $policy,
            state: State::ERROR,
            tokens: [
                'exception' => $err_msg,
                'exception_type' => get_class($e),
            ]
        );
    }

    private function forkPolicyAudit(Policy $policy, AuditInterface $audit, DateTimeInterface $start, DateTimeInterface $end):ForkInterface
    {
        // Backward compatibility with 3.4 and earlier.
        $audit->setParameter('reporting_period_start', $start);
        $audit->setParameter('reporting_period_end', $end);

        $fork = $this->forkManager->create();
        $fork->setLabel($policy->name);
        $fork->run(fn() => $audit->execute($policy));
        return $fork;
    }

    /**
     * Return the dependency behaviour based on their evaluation.
     */
    private function getDependencyResponse(array $contexts, Policy $policy):false|AuditResponse
    {
        $onFail = DependencyBehaviour::PASS;
        $exception = '';
        foreach ($policy->depends as $dependency) {
            try {
                $this->requireDependency($dependency, $contexts);
            }
            catch (DependencyException $e) {
                $onFail = $onFail->higher($dependency->onFail);
                if ($onFail === $dependency->onFail) {
                    $exception = $e->getMessage();
                }
            }
        }
        return $onFail === DependencyBehaviour::PASS ? false : new AuditResponse(
            policy: $policy,
            state: State::from($onFail->getAuditOutcome()),
            tokens: [
                'exception' => $exception,
                'exception_type' => DependencyException::class
            ]
        );
    }

    /**
     * @throws DependencyException when dependency is not met.
     */
    private function requireDependency(Dependency $dependency, array $contexts):true
    {
        $contexts['dependency'] = $dependency;
        try {
            $return = $this->syntaxProcessor->evaluate(
                expression: $this->syntaxProcessor->interpolate($dependency->expression, $contexts),
                language: $dependency->syntax,
                contexts: $contexts
            );
            if (($return === 1) || ($return === true)) {
                return true;
            }
        }
        catch (RuntimeError $e) {
            throw new DependencyException($dependency, $e->getMessage(), $e);
        }
        catch (UnexpectedValueException $e) {
            throw new DependencyException($dependency, $e->getMessage(), $e);
        }
        // UnexpectedValueException
        throw new DependencyException($dependency);
    }

    /**
     * Get contexts for SyntaxProcessor.
     */
    private function buildContexts(TargetInterface $target):array
    {
        $contexts = [];
        $contexts['target'] = $target;
        foreach ($target->getPropertyList() as $key) {
            $contexts[$key] = $target->getProperty($key);
        }
        foreach (State::cases() as $state) {
            $contexts[$state->name] = $state->value;
        }
        return $contexts;
    }
}