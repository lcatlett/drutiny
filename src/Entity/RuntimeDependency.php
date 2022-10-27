<?php

namespace Drutiny\Entity;

class RuntimeDependency
{
    protected string $name;
    protected $value;
    protected string $details;
    protected bool $status = false;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function setValue($value)
    {
        if (!is_string($value) && !is_bool($value) && !is_int($value) && !is_float($value)) {
            throw new \RuntimeException("Cannot set runtime dependency for $this->name with value of type: ".gettype($value).". Please use string, int, float or bool.");
        }
        $this->value = $value;
        return $this;
    }

    public function getValue()
    {
        return $this->value;
    }
    public function getDetails(): string
    {
        return $this->details;
    }
    public function getStatus(): bool
    {
        return $this->status;
    }
    public function getName(): string
    {
        return $this->name;
    }

    public function setDetails(string $details)
    {
        $this->details = $details;
        return $this;
    }

    public function setStatus(bool $status)
    {
        $this->status = $status;
        return $this;
    }
}
