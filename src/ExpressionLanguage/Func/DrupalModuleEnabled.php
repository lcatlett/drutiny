<?php

namespace Drutiny\ExpressionLanguage\Func;

use Closure;
use Drutiny\Target\TargetInterface;

class DrupalModuleEnabled extends ExpressionFunction implements ContainerDependentFunctionInterface
{
    private $target;

    public function __construct(TargetInterface $target)
    {
      $this->target = $target;
    }

    public function getName():string
    {
        return 'drupal_module_enabled';
    }

    public function getCompiler():Closure
    {
        return function ($module_name) {
            return sprintf("(%s is enabled)", $module_name);
        };
    }

    public function getEvaluator():Closure
    {
        return function ($args, $module_name) {
          $list = $this->target->getService('drush')
            ->pmList(['format' => 'json'])
            ->run(function($output) {
              return json_decode($output, TRUE);
            });

          if (!isset($list[$module_name])) {
            return false;
          }
          $status = strtolower($list[$module_name]['status']);
          return $status == 'enabled';
        };
    }
}
