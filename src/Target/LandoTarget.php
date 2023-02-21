<?php

namespace Drutiny\Target;

use Drutiny\Attribute\AsTarget;
use Drutiny\Target\Transport\DockerTransport;
use Psr\Cache\CacheItemInterface;

/**
 * Target for parsing Drush aliases.
 */
#[AsTarget(name: 'lando')]
class LandoTarget extends DrushTarget implements TargetInterface, TargetSourceInterface
{
  /**
   * {@inheritdoc}
   */
  public function getId():string
  {
    return $this['lando.name'];
  }

  /**
   * @inheritdoc
   * Implements Target::parse().
   */
    public function parse(string $alias, ?string $uri = NULL):TargetInterface
    {

        $this['lando.name'] = $alias;

        $lando = $this->localCommand->run('lando list --format=json', function ($output) {
          return json_decode($output, true);
        });

        $apps = array_filter($lando, function ($instance) use ($alias) {
          return ($instance['service'] == 'appserver') && ($instance['app'] == $alias);
        });

        if (empty($apps)) {
          throw new InvalidTargetException("Lando site '$alias' either doesn't exist or is not currently active.");
        }

        $this['lando.app'] = array_shift($apps);
        $this->transport = new DockerTransport($this->localCommand);
        $this->transport->setContainer($this['lando.app']['name']);

        $this['drush.root'] = '/app';

        $dir = dirname($this['lando.app']['src'][0]);
        $info = $this->localCommand->run(sprintf('cd %s && lando info --format=json', $dir), function ($output) {
          return json_decode($output, true);
        });

        $urls = [];
        foreach ($info as $service) {
          $this['lando.'.$service['service']] = $service;
          $urls += $service['urls'] ?? [];
        }
        $urls[] = $uri;

        $urls = array_filter($urls);
        // Provide a default URI if none already provided.
        $this->setUri(array_pop($urls));
        $this->buildAttributes();
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getAvailableTargets():array
    {
      $lando = $this->localCommand->run('lando list --format=json', function ($output, CacheItemInterface $cache) {
        $cache->expiresAfter(1);
        return json_decode($output, true);
      });

      $apps = array_filter($lando, function ($instance) {
        return $instance['service'] == 'appserver';
      });

      $targets = [];
      foreach ($apps as $app) {
        $dir = dirname($app['src'][0]);
        $edge = $this->localCommand->run(sprintf('cd %s && lando info --format=json', $dir), function ($output) {
          return array_filter(json_decode($output, true), fn ($d) => isset($d['urls']));
        });

        $targets[] = [
          'id' => $app['app'],
          'uri' => end($edge[0]['urls']),
          'name' => $app['app']
        ];
      }
      return $targets;
    }
}
