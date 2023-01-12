<?php

namespace Drutiny\ExpressionLanguage\Func;

use Composer\Semver\Comparator;
use Closure;

class SemverGt extends ExpressionFunction implements FunctionInterface
{
    public function getName():string
    {
        return 'semver_gt';
    }

    public function getCompiler():Closure
    {
        return function ($v1, $v2) {
          return sprintf('%s > %s', $v1, $v2);
        };
    }

    public function getEvaluator():Closure
    {
        return function ($args, $v1, $v2) {
            return Comparator::greaterThan($v1, $v2);
        };
    }
}
