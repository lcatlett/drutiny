<?php

namespace Drutiny;

use Drutiny\Audit\AuditInterface;
use Drutiny\Audit\AuditValidationException;
use Drutiny\Audit\TwigEvaluator;
use Drutiny\AuditResponse\AuditResponse;
use Drutiny\Entity\DataBag;
use Drutiny\Policy\DependencyException;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Target\TargetInterface;
use Drutiny\Upgrade\AuditUpgrade;
use Drutiny\Entity\Exception\DataNotFoundException;
use Drutiny\Helper\ExpressionLanguageTranslation;
use Drutiny\Policy\Dependency;
use Drutiny\Target\TargetPropertyException;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\Yaml\Yaml;
use Twig\Error\RuntimeError;

/**
 * Base class for Audit.
 */
abstract class Audit implements AuditInterface
{
    protected DataBag $dataBag;
    protected Policy $policy;
    protected InputDefinition $definition;
    protected bool $deprecated = false;
    protected string $deprecationMessage = '';
    protected int $verbosity;

    final public function __construct(
        protected ContainerInterface $container,
        protected TargetInterface $target,
        protected LoggerInterface $logger,

        /**
         * @deprecated
         */
        protected ExpressionLanguage $expressionLanguage,
        protected TwigEvaluator $twigEvaluator,
        protected ProgressBar $progressBar,
        protected CacheInterface $cache,
        protected EventDispatcher $eventDispatcher,
        OutputInterface $output,
    ) {
        if ($logger instanceof Logger) {
            $this->logger = $logger->withName('audit');
        }
        $this->definition = new InputDefinition();
        $this->dataBag = new DataBag();
        $this->dataBag->add([
        'parameters' => new DataBag(),
      ]);
        $this->configure();
        $this->twigEvaluator->setContext('target', $this->target);
        $this->verbosity = $output->getVerbosity();
    }

    /**
     * {@inheritdoc}
     */
    public function configure():void
    {
    }

    /**
     * @return
     */
    abstract public function audit(Sandbox $sandbox);

    protected function getPolicy():Policy
    {
        return $this->policy;
    }

    /**
     * Validate the contexts of the audit and target,
     */
    protected function validate(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    final public function execute(Policy $policy, $remediate = false): AuditResponse
    {
        if ($this->deprecated) {
            $this->logger->warning(sprintf("Policy '%s' is using '%s' which is a deprecated class. This may fail in the future.", $policy->name, get_class($this)));
            if (!empty($this->deprecationMessage)) {
                $this->logger->warning("Class {class} is deprecated: {msg}", [
                  "class" => get_class($this),
                  "msg" => $this->deprecationMessage,
                ]);
            }
        }
        $this->policy = $policy;
        $response = new AuditResponse($policy);
        $execution_start_time = new \DateTime();
        $this->logger->info('Auditing '.$policy->name.' with '.get_class($this));
        $outcome = AuditInterface::ERROR;
        try {
            if (!$this->validate()) {
                throw new AuditValidationException("Target of type ".get_class($this->target)." is not suitable for audit class ".get_class($this). " with policy: ".$policy->name);
            }

            $dependencies = $policy->getDepends();
            $this->progressBar->setMaxSteps(count($dependencies) + $this->progressBar->getMaxSteps());
            // Ensure policy dependencies are met.
            foreach ($policy->getDepends() as $dependency) {
                // Throws DependencyException if dependency is not met.
                $this->executeDependency($dependency);
                // $dependency->execute($this);
                $this->progressBar->advance();
            }

            // Build parameters to be used in the audit.
            foreach ($policy->build_parameters ?? [] as $key => $value) {
                try {
                    $this->logger->debug(__CLASS__ . ':build_parameters('.$key.'): ' . $value);
                    $value = $this->evaluate($value, 'twig');

                    // Set the token to be available for other build_parameters.
                    $this->set($key, $value);

                    // Set the parameter to be available in the audit().
                    if ($this->definition->hasArgument($key)) {
                        $policy->addParameter($key, $value);
                    }
                } catch (RuntimeError $e) {
                    throw new \Exception("Failed to create key: $key. Encountered Twig runtime error: " . $e->getMessage());
                }
            }

            $input = new ArrayInput($policy->getAllParameters(), $this->definition);
            $this->dataBag->get('parameters')->add($input->getArguments());
            $this->dataBag->add($input->getArguments());

            // Run the audit over the policy.
            $outcome = $this->audit(new Sandbox($this));
        } catch (DependencyException $e) {
            $outcome = AuditInterface::ERROR;
            $outcome = $e->getDependency()->getFailBehaviour();
            $message = $e->getMessage();
            $this->set('exception', $message);
            $this->set('exception_type', get_class($e));
            $log_level = $outcome == AuditInterface::IRRELEVANT ? 'debug' : 'warning';
            $this->logger->log($log_level, "'{policy}' {class} ({uri}): $message", [
              'class' => get_class($this),
              'uri' => $this->target->getUri(),
              'policy' => $policy->name
            ]);
        } catch (AuditValidationException $e) {
            $outcome = AuditInterface::NOT_APPLICABLE;
            $message = $e->getMessage();
            $this->set('exception', $message);
            $this->set('exception_type', get_class($e));
            $this->logger->warning("'{policy}' {class} ({uri}): $message", [
              'class' => get_class($this),
              'uri' => $this->target->getUri(),
              'policy' => $policy->name
            ]);
        } catch (TargetPropertyException $e) {
            $outcome = AuditInterface::NOT_APPLICABLE;
            $message = $e->getMessage();
            $this->set('exception', $message);
            $this->set('exception_type', get_class($e));
            $this->logger->warning("'{policy}' {class} ({uri}): $message", [
              'class' => get_class($this),
              'uri' => $this->target->getUri(),
              'policy' => $policy->name
            ]);
        } catch (InvalidArgumentException $e) {
            $outcome = AuditInterface::ERROR;
            $this->set('exception_type', get_class($e));
            $message = $e->getMessage();
            $this->set('exception', $message);
            $this->logger->warning("'{policy}' {class} ({uri}): $message", [
              'class' => get_class($this),
              'uri' => $this->target->getUri(),
              'policy' => $policy->name
            ]);
            $this->logger->warning($e->getTraceAsString());
            $this->logger->warning($policy->name . ': ' . get_class($this));
            $this->logger->warning(print_r($policy->getAllParameters(), 1));

            $helper = AuditUpgrade::fromAudit($this);
            $helper->addParameterFromException($e);
            $this->set('exception', $helper->getParamUpgradeMessage());
        } catch (\Exception $e) {
            $outcome = AuditInterface::ERROR;
            $message = $e->getMessage();
            if ($this->verbosity > OutputInterface::VERBOSITY_NORMAL) {
                $message .= PHP_EOL.$e->getTraceAsString();
            }
            $this->set('exception', $message);
            $this->set('exception_type', get_class($e));
            $this->logger->error("'{policy}' {class} ({uri}): $message", [
              'class' => get_class($this),
              'uri' => $this->target->getUri(),
              'policy' => $policy->name
            ]);
        } finally {
            // Log the parameters output.
            $tokens = $this->dataBag->export();
            $this->logger->debug("Tokens:\n".Yaml::dump($tokens, 4, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
            // Set the response.
            $response->set($outcome ?? AuditInterface::ERROR, $tokens);

            $this->eventDispatcher->dispatch(new GenericEvent('audit', [
              'class' => get_class($this),
              'policy' => $policy->name,
              'outcome' => $response->getType(),
              'uri' => $this->target->getUri(),
            ]), 'audit');
        }
        $execution_end_time = new \DateTime();
        $total_execution_time = $execution_start_time->diff($execution_end_time);
        $this->logger->info($total_execution_time->format('Execution completed for policy "' . $policy->name . '" in %m month(s) %d day(s) %H hour(s) %i minute(s) %s second(s)'));
        return $response;
    }

    /**
     * Evaluate a dependency.
     * 
     * @throws DependencyException.
     */
    protected function executeDependency(Dependency $dependency):bool {
        try {
            $expression = $this->interpolate($dependency->expression);
            $return = $this->evaluate($expression, $dependency->syntax, [
                'dependency' => $dependency
            ]);
            if ($return === 1 || $return === true) {
                return true;
            }
        }
        catch (\Exception $e) {
            $this->logger->warning($dependency->syntax . ': ' . $e->getMessage());
        }
        $this->logger->debug('Expression FAILED.', [
            'class' => get_class($this),
            'expression' => $expression,
            'return' => print_r($return ?? 'EXCEPTION_THROWN', 1),
            'syntax' => $dependency->syntax
        ]);
        // Execute the on fail behaviour.
        throw new DependencyException($dependency);
    }
        

    /**
     * Use a new Audit instance to audit policy.
     *
     * @param string $policy_name
     *    The name of the policy to audit.
     */
    public function withPolicy(string $policy_name): AuditResponse
    {
        $this->logger->debug("->withPolicy($policy_name)");
        $policy = $this->container
            ->get('policy.factory')
            ->loadPolicyByName($policy_name);
        return $this->container->get($policy->class)->execute($policy);
    }

    /**
     * Evaluate an expression using the Symfony ExpressionLanguage engine.
     */
    public function evaluate(string $expression, $language = 'expression_language', array $contexts = []):mixed
    {
        try {
            if ($language == 'expression_language') {
                $translation = new ExpressionLanguageTranslation($old = $expression);
                $expression = $translation->toTwigSyntax();
                $this->logger->warning("Expression language is deprecreated. Syntax will be translated to Twig. '$old' => '$expression'.");
            }
            $contexts = array_merge($contexts, $this->getContexts());
            return $this->twigEvaluator->execute($expression, $contexts);
        } catch (\Exception $e) {
            $this->logger->error("Evaluation failure {syntax}: {expression}: {message}", [
            'syntax' => $language,
            'expression' => $expression,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
          ]);
            throw $e;
        }
    }

    /**
     * Allow strings to utilise Audit and Target contexts.
     */
    public function interpolate(string $string, array $contexts = []): string
    {
        return $this->_interpolate($string, array_merge($contexts, $this->getContexts()));
    }

    /**
     * Helper function for the public interpolate function.
     */
    private function _interpolate(string $string, iterable $vars, $key_prefix = ''): string
    {
        foreach ($vars as $key => $value) {
            if (is_iterable($value)) {
                $string = $this->_interpolate($string, $value, $key.'.');
            }

            $token = '{'.$key_prefix.$key.'}';
            if (false === strpos($string, $token)) {
                continue;
            }

            $value = (string) $value;
            $string = str_replace($token, $value, $string);
        }

        return $string;
    }

    /**
     * Get all contexts from the Audit class.
     */
    protected function getContexts(): array
    {
        $contexts = $this->dataBag->all();
        $contexts['target'] = $this->target;
        foreach ($this->target->getPropertyList() as $key) {
            $contexts[$key] = $this->target->getProperty($key);
        }

        $reflection = new \ReflectionClass(__CLASS__);
        foreach ($reflection->getConstants() as $key => $value) {
            $contexts[$key] = $value;
        }

        $contexts['audit'] = $this;

        return $contexts;
    }

    /**
     * Set a parameter. Typically provided by a policy.
     */
    public function setParameter(string $name, $value): AuditInterface
    {
        $this->dataBag->get('parameters')->set($name, $value);

        return $this;
    }

    /**
     * Get a set parameter or provide the default value.
     */
    public function getParameter(string $name, $default_value = null)
    {
        try {
            return $this->dataBag->get('parameters')->get($name) ?? $default_value;
        } catch (DataNotFoundException $e) {
            return $default_value;
        }
    }

    /**
     * Set a non-parameterized value such as a token.
     *
     * This function is used to communicate output data computed by the
     * audit class. This is useful for policies to use to contextualize
     * messaging.
     */
    public function set(string $name, $value): AuditInterface
    {
        $this->dataBag->set($name, $value);

        return $this;
    }

    public function get(string $name)
    {
        return $this->dataBag->get($name);
    }

    /**
     * Check if an Audit has a given argument.
     */
    public function hasArgument(string $name): bool
    {
        return $this->definition->hasArgument($name);
    }

    /**
     * Used to provide target to deprecated Sandbox object.
     *
     * @deprecated
     */
    public function getTarget(): TargetInterface
    {
        return $this->target;
    }

    /**
     * Used to provide logger to deprecated Sandbox object.
     *
     * @deprecated
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function getDefinition():InputDefinition
    {
        return $this->definition;
    }

    /**
     * Set information about a parameter.
     *
     * This is used exclusively when the configure() method is called.
     * This allows the audit to specify and validate inputs from a policy.
     */
    protected function addParameter(string $name, int $mode = null, string $description = '', $default = null): self
    {
        if (!isset($this->definition)) {
            $this->definition = new InputDefinition();
        }
        $args = $this->definition->getArguments();
        $input = new InputArgument($name, $mode, $description, $default);

        if ($mode == self::PARAMETER_REQUIRED) {
            array_unshift($args, $input);
        } else {
            $args[] = $input;
        }

        $this->definition->setArguments($args);

        return $this;
    }

    /**
     * Set audit class as deprecated and shouldn't be used anymore.
     */
    protected function setDeprecated(string $message = ''): self
    {
        $this->deprecated = true;
        $this->deprecationMessage = $message;
        return $this;
    }

    public function isDeprecated(): bool
    {
        return $this->deprecated;
    }

    protected function runCacheable($contexts, callable $func)
    {
        $cid = md5(get_class($this) . json_encode($contexts));
        return $this->cache->get($cid, $func);
    }
}
