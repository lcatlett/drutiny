<?php

namespace Drutiny\ExpressionLanguage\Func;

use Drutiny\Target\TargetInterface;
use Drutiny\Assessment;
use Drutiny\PolicyFactory;
use Drutiny\AuditResponse\NoAuditResponseFoundException;
use Closure;

class Policy extends ExpressionFunction implements ContainerDependentFunctionInterface
{
    private $target;
    private $assessment;
    private $factory;

    public function __construct(Assessment $assessment, TargetInterface $target, PolicyFactory $factory)
    {
      $this->target = $target;
      $this->assessment = $assessment;
      $this->factory = $factory;
    }

    public function getName():string
    {
        return 'policy';
    }

    public function getCompiler():Closure
    {
        return function ($policy_name) {
            return 'policy('.$policy_name.')';
        };
    }

    public function getEvaluator():Closure
    {
        return function ($args, $policy_name) {
          try {
              return $this->assessment->getPolicyResult($policy_name);
          } catch (NoAuditResponseFoundException $e) {
            $policy = $this->factory->loadPolicyByName($policy_name);
            // TODO: Run assessment by TimeRange of parent assessment.
            return $this->assessment
              ->assessTarget($this->target, [$policy])
              ->getPolicyResult($policy_name)
              ->getType();
          }
        };
    }
}
