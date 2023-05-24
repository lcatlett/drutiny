<?php

namespace Drutiny\Audit;

use Drutiny\Attribute\Parameter;
use Drutiny\Attribute\Type;
use Drutiny\Audit;
use Drutiny\AuditResponse\State;
use Drutiny\Sandbox\Sandbox;
use Twig\Error\RuntimeError;

/**
 * Audit gathered data.
 */
#[Parameter(name: 'expression', type: Type::STRING, description: 'Twig syntax to evaluate audit data into an outcome.', default: 'true')]
#[Parameter(name: 'variables', type: Type::HASH, description: 'A keyed array of expressions to set variables before evaluating the passing expression.', default: [])]
#[Parameter(name: 'syntax', type: Type::STRING, enums: ['twig', 'expression_language'], default: 'expression_language', description: 'Which syntax to use. Options: twig or expression_language.')]
#[Parameter(name: 'not_applicable', type: Type::STRING, default: 'false', description: 'An expression that if returns true, will render the policy as not applicable.')]
#[Parameter(name: 'fail_on_error', type: Type::BOOLEAN, default: false, description: 'Set to true if you want an error during data gathering to result in a failure.')]
#[Parameter(name: 'failIf', type: Type::STRING, description: 'Fail policy if twig expression returns true')]
#[Parameter(name: 'warningIf', type: Type::STRING, description: 'Add warning to outcome if expression return true')]
class AbstractAnalysis extends Audit
{
  /**
   * Gather analysis data to audit.
   */
    protected function gather(Sandbox $sandbox) {}

    final public function audit(Sandbox $sandbox)
    {
        try {
          $this->gather($sandbox);
        }
        catch (\Exception $e) {
          if ($this->getParameter('fail_on_error')) {
            $this->set('exception', $e->getMessage());
            $this->set('exception_type', get_class($e));
            return self::FAIL;
          }
          // Continue the error as normal.
          throw $e;
        }

        $syntax = $this->getParameter('syntax', 'expression_language');

        if ($expression = $this->getParameter('not_applicable', 'false')) {
            $this->logger->debug(__CLASS__ . ':INAPPLICABILITY ' . $expression);
            if ($this->evaluate($expression, $syntax)) {
                return self::NOT_APPLICABLE;
            }
        }

        foreach ($this->getParameter('variables',[]) as $key => $value) {
          try {
            $this->logger->debug(__CLASS__ . ':VARIABLE('.$key.'): ' . $value);
            $this->set($key, $this->evaluate($value, 'twig'));
          }
          catch (RuntimeError $e)
          {
            throw new \Exception("Failed to create variable: $key. Encountered Twig runtime error: " . $e->getMessage());
          }
        }

        // Set outcome to failure if failIf expression is true.
        // Note: This takes precedence of the `expression` parameter which does not get evaluated
        // if this parameter is set.
        $failIf = $this->getParameter('failIf');
        if ($failIf !== null) {
          $this->logger->debug(__CLASS__ . ':EXPRESSION(failIf): ' . $failIf);
          $outcome = $this->evaluate($failIf, 'twig') ? State::FAILURE : State::SUCCESS;
        }
        else {
          $expression = $this->getParameter('expression', 'true');
          $this->logger->debug(__CLASS__ . ':EXPRESSION: ' . $expression);
          $outcome = State::fromValue($this->evaluate($expression, $syntax));
        }

        // Add a warning if warningIf expression is true.
        if (($outcome->isSuccessful() || $outcome->isFailure()) && !$outcome->hasWarning()) {
          $warningIf = $this->getParameter('warningIf');
          if ($warningIf !== null) {
            $this->logger->debug(__CLASS__ . ':EXPRESSION(warningIf): ' . $warningIf);
            $outcome = $this->evaluate($warningIf, 'twig') ? $outcome->withWarning() : $outcome;
          }
        }
        return $outcome->value;
    }
}
