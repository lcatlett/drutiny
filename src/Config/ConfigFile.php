<?php

namespace Drutiny\Config;

use Symfony\Component\Yaml\Yaml;

class ConfigFile implements ConfigInterface
{
    protected array $config;

    public function __construct(protected string $filepath)
    {
        $this->config = file_exists($filepath) ? Yaml::parseFile($filepath) : [];
    }

    public function getFilepath():string
    {
        return $this->filepath;
    }

    public function load(string $namespace):Config
    {
        if (!isset($this->config[$namespace])) {
            $this->config[$namespace] = [];
        }

        return new Config($this, $this->config[$namespace], $namespace);
    }

    public function save():int|false
    {
        return file_put_contents($this->filepath, Yaml::dump(array_filter($this->config), 4, 4));
    }
}
