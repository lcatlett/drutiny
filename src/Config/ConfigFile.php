<?php

namespace Drutiny\Config;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

class ConfigFile
{
    protected $config;
    protected $filepath;
    protected $namespace;

    public function __construct($filepath)
    {
        $this->config = file_exists($filepath) ? Yaml::parseFile($filepath) : [];
        $this->filepath = $filepath;
    }

    public function getNamespaces()
    {
      return array_keys($this->config);
    }

    public function load($namespace)
    {
        return new Config($this, $namespace, $this->config[$namespace] ?? []);
    }

    public function doWrite()
    {
        return file_put_contents($this->filepath, Yaml::dump($this->config, 4, 4));
    }

    public function save(string $namespace, array $data)
    {
        $this->config[$namespace] = $data;
        return $this->doWrite();
    }

    public function delete(string $namespace)
    {
      unset($this->config[$namespace]);
      return $this->doWrite();
    }
}
