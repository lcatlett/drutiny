<?php

namespace Drutiny\ExpressionLanguage\Func;

use Closure;

class ArraySelect extends ExpressionFunction implements FunctionInterface
{
    public function getName():string
    {
        return 'array_select';
    }

    public function getCompiler():Closure
    {
        return function () {
            list($input, $property, $value, $strict_match) = array_slice(func_get_args(), 1);
            return sprintf('array_select(<input_array>, "%s", "%s", "%b")', $property, $value, $strict_match);
        };
    }

    public function getEvaluator():Closure
    {
        return function ($a, $input, $property, $value, $strict_match = false) {
            foreach ($input as $set) {
                if (!isset($set[$property])) {
                    continue;
                }
                if ($strict_match && ($set[$property] == $value)) {
                    return $set;
                }
                if (!$strict_match && (strpos($set[$property], $value) !== false)) {
                    return $set;
                }
            }
            return [
            $property => false,
            ];
        };
    }
}
