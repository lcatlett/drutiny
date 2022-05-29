<?php

namespace Drutiny\Target;

use Drutiny\Target\Service\DrushService;
use Drutiny\Target\Service\DockerService;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Target for parsing Drush aliases.
 */
class DdevTarget extends DrushTarget implements TargetInterface, TargetSourceInterface
{
    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return $this['ddev.name'];
    }

    /**
     * @inheritdoc
     * Implements Target::parse().
     */
    public function parse(string $alias, ?string $uri = null): TargetInterface
    {
        $status_cmd = sprintf('ddev describe %s -j', $alias);
        $ddev = $this['service.exec']->get('local')->run($status_cmd, function ($output) {
            $json = json_decode(trim($output), true);
            return $json['raw'];
        });

        if (empty($ddev)) {
            throw new InvalidTargetException("DDEV site '$alias' either doesn't exist or is not currently active.");
        }
        if ($ddev['status'] == 'stopped') {
            throw new InvalidTargetException("DDEV site '$alias' is currently stopped. Please start this service and try again.");
        }

        $ddev['name'] = $alias;
        foreach ($ddev as $k => $v) {
            $this['ddev.'.$k] = $v;
        }
        $this['service.docker'] = new DockerService($this['service.local']);
        $this['service.docker']->setContainer($ddev['services']['web']['full_name']);
        $this['service.exec']->addHandler($this['service.docker'], 'docker');

        $this['drush.root'] = '/var/www/html';

        // Provide a default URI if none already provided.
        $this->setUri($uri ?? $ddev['primary_url']);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getAvailableTargets(): array
    {
        $aliases = $this['service.exec']->get('local')->run('ddev list -A -j', function ($output) {
            $json = json_decode($output, true);
            return array_combine(array_column($json['raw'], 'name'), $json['raw']);
        });

        $targets = [];
        foreach ($aliases as $name => $info) {
            $targets[] = [
          'id' => $name,
          'uri' => $info['primary_url'] ?? '',
          'name' => $name
        ];
        }
        return $targets;
    }
}
