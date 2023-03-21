<?php

namespace Drutiny\Helper;

class Tabular {
    const DIVIDER = '_';
    static public function flatten(object|array $object, $divider = Tabular::DIVIDER) {
        $array = Json::extract($object);
        return self::doFlatten($array, '', $divider);
    }

    static protected function doFlatten(mixed $variable, string $prefix, string $divider) {
        if (gettype($variable) != 'array') {
            return $variable;
        }
        $array = [];
        foreach ($variable as $k => $v) {
            $value = self::doFlatten($v, $prefix.$k.$divider, $divider);
            if (is_array($value)) {
                $array += $value;
            }
            else {
                $array[$prefix.$k] = $value;
            }
        }
        return $array;
    }
}