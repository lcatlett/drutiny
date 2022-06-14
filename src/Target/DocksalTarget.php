<?php

namespace Drutiny\Target;

use Drutiny\Target\Service\DrushService;
use Drutiny\Target\Service\DockerService;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Yaml\Yaml;

/**
 * Target for parsing Drush aliases.
 */
class DocksalTarget extends DrushTarget implements TargetInterface, TargetSourceInterface
{
    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return $this['docksal.name'];
    }

    /**
     * @inheritdoc
     * Implements Target::parse().
     */
    public function parse(string $alias, ?string $uri = null): TargetInterface
    {
        $targets = $this->findTargets();
        if (!isset($targets[$alias])) {
            throw new InvalidTargetException("Docksal alias does not exist. Cannot locate Docksal project.");
        }

        $this['docksal.name'] = $alias;
        $this['docksal.target_dir'] = $targets[$alias];
        $this['docksal.project'] = basename($targets[$alias]);

        $config_command = sprintf('cd %s && fin config yml', $targets[$alias]);

        $this['docksal.config'] = $this['service.exec']->get('local')->run($config_command, function ($output) {
            return Yaml::parse($output);
        });

        $containers = $this['service.exec']->get('local')->run("docker container ls --format '{{json .}}' | grep _cli", function ($output) {
            $containers = [];
            foreach (array_filter(explode(PHP_EOL, $output), 'trim') as $line) {
                $container = json_decode($line, true);
                $containers[$container['ID']] = $container;
            }
            return $containers;
        });

        // Filter down to CLI containers using this project namespace.
        $containers = array_filter($containers, fn ($c) => strpos($c['Names'], $this['docksal.project'].'_cli') === 0);

        if (empty($containers)) {
            throw new InvalidTargetException("Docksal alias '$alias' found but couldn't find docker CLI container running.");
        }

        $container = array_shift($containers);

        $this['service.docker'] = new DockerService($this['service.local']);
        $this['service.docker']->setContainer($container['Names']);
        $this['service.exec']->addHandler($this['service.docker'], 'docker');

        $this['drush.root'] = '/var/www';

        // Provide a default URI if none already provided.
        $this->setUri($this['docksal.config']['services']['cli']['environment']['DRUSH_OPTIONS_URI']);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getAvailableTargets(): array
    {
        $targets = [];
        foreach ($this->findTargets() as $app => $location) {
            $uri = str_replace('_', '-', basename($location)).'.docksal';
            $targets[] = [
          'id' => $app,
          'uri' => $uri,
          'name' => $app
        ];
        }
        return $targets;
    }

    protected function findTargets()
    {
        return $this['service.exec']->get('local')->run('fin alias list', function ($output) {
            $lines = array_filter(array_map('trim', explode(PHP_EOL, $output)));
            // Remove headers
            array_shift($lines);
            $a = [];
            foreach ($lines as $line) {
                list($alias, $location) = array_values(array_filter(explode(" ", $line), 'trim'));
                $a[$alias] = $location;
            }
            return $a;
        });
    }
}
