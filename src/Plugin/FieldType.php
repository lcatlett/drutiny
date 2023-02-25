<?php

namespace Drutiny\Plugin;

enum FieldType {
    case CONFIG;
    case CREDENTIAL;
    
    public function key():string
    {
        return match ($this) {
            self::CONFIG => 'config',
            self::CREDENTIAL => 'credential'
        };
    }

    static public function get(string $type):FieldType
    {
        return match ($type) {
            'config' => self::CONFIG,
            'credential' => self::CREDENTIAL
        };
    }
}
