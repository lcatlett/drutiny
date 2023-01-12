<?php

namespace Drutiny\ExpressionLanguage\Func;

use Closure;

class Filter extends ExpressionFunction implements FunctionInterface
{
    public function getName():string
    {
        return 'filter';
    }

    public function getCompiler():Closure
    {
        return function ($array, $property, $match, $equals) {
            list($array, $property, $match, $equals) = array_slice(func_get_args(), 1);
            return sprintf('filter(%s, "%s", "%s", "%s")', $array, $property, $match, $equals);
        };
    }

    public function getEvaluator():Closure
    {
        return function ($args, $array, $property, $match = true, $equals = true) {
            list($array, $property, $match, $equals) = array_slice(func_get_args(), 1);
            return array_filter($array, function ($value) use ($property, $match, $equals) {
                if (!is_array($value)) {
                    return false;
                }
                $property_parts = explode('.', $property);
                $ref = $value;
                foreach ($property_parts as $key) {
                    if (!isset($ref[$key])) {
                        return false;
                    }
                    $ref = $ref[$key];
                }

                return $equals ? $ref == $match : strpos($ref, $match) !== false;
            });
        };
    }
}
