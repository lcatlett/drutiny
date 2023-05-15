<?php

namespace Drutiny\Target;

use Drutiny\Attribute\AsTarget;
use Drutiny\Target\Exception\TargetLoadingException;
use Drutiny\Target\Exception\TargetNotFoundException;
use Drutiny\Target\Exception\TargetSourceFailureException;
use Drutiny\Target\Transport\DockerTransport;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Target for parsing Drush aliases.
 */
#[AsTarget(name: 'ddev')]
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

        try {
            $ddev = $this->localCommand->run($status_cmd, function ($output) {
                $json = json_decode(trim($output), true);
                return $json['raw'];
            });
        }
        catch (ProcessFailedException $e) {
            throw new TargetSourceFailureException(message: "DDEV describe command failed to execute: $status_cmd.", previous: $e);
        }

        if (empty($ddev)) {
            throw new TargetNotFoundException(message: "DDEV site '$alias' either doesn't exist or is not currently active.");
        }
        if ($ddev['status'] == 'stopped') {
            throw new TargetLoadingException(message: "DDEV site '$alias' is currently stopped. Please start this service and try again.");
        }

        $ddev['name'] = $alias;
        foreach ($ddev as $k => $v) {
            $this['ddev.'.$k] = $v;
        }
        $this->transport = new DockerTransport($this->localCommand);
        $this->transport->setContainer($ddev['services']['web']['full_name']);

        $this['drush.root'] = '/var/www/html/'.$ddev['docroot'];

        // Provide a default URI if none already provided.
        $this->setUri($uri ?? $ddev['primary_url']);
        $this->buildAttributes();
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getAvailableTargets(): array
    {
        $aliases = $this->localCommand->run('ddev list -A -j', function ($output, CacheItemInterface $cache) {
            $cache->expiresAfter(1);
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
