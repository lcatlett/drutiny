<?php

namespace Drutiny\Config;

class Config
{
    protected ConfigFile $parent;
    protected string $name;
    protected array $data = [];

    public function __construct(ConfigFile $parent, string $name, array $config)
    {
        $this->parent = $parent;
        $this->name = $name;
        $this->data = $config;
    }

    public function __get($name)
    {
      return $this->data[$name] ?? null;
    }

    public function __isset($name): bool
    {
      return isset($this->data[$name]);
    }

    public function __set($name, $value):void
    {
      $this->data[$name] = $value;
      $this->parent->save($this->name, $this->data);
    }

    public function keys()
    {
      return array_keys($this->data);
    }

    public function delete()
    {
      return $this->parent->delete($this->name);
    }
}
