<?php

namespace Drutiny\Policy;

use Exception;

enum Severity:string {
    CASE NONE = 'none';
    CASE LOW = 'low';
    CASE NORMAL = 'normal';
    CASE HIGH = 'high';
    CASE CRITICAL = 'critical';

    /**
     * Get the default case if none preset.
     */
    static public function getDefault():self
    {
        return Severity::NORMAL;
    }

    /**
     * Get the severity weight.
     */
    public function getWeight():int
    {
        return match($this) {
            self::NONE => 0,
            self::LOW => 1,
            self::NORMAL => 2,
            self::HIGH => 4,
            self::CRITICAL => 8,
        };
    }

    /**
     * Check if a string is a severity case.
     */
    static public function has(string $value):bool {
        return in_array($value, array_map(fn($e) => $e->value, Severity::cases()));
    }

    /**
     * Get severity case from int or string value.
     */
    static public function fromValue(string|int|null $value): self {
        return match (gettype($value)) {
            'string' => self::from($value),
            'int' => self::fromInt($value),
            default => self::getDefault(),
        };
    }

    /**
     * Return an Enum severity by its weight.
     */
    static public function fromInt(int $int):self
    {
        return match ($int) {
            1 => self::LOW,
            2 => self::NORMAL,
            4 => self::HIGH,
            8 => self::CRITICAL,
            default => throw new Exception("Unknown severity int code: $int.")
        };
    }
}