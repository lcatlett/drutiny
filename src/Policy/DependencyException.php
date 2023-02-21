<?php

namespace Drutiny\Policy;

class DependencyException extends \Exception
{
    protected $dependency;

    public function __construct(Dependency $dependency, string $message = '')
    {
        $this->dependency = $dependency;
        parent::__construct(sprintf("Policy dependency failed: %s (%s). %s",$dependency->getDescription(), $dependency->getExpression(), $message));
    }

    public function getDependency()
    {
        return $this->dependency;
    }
}
