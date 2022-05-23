<?php

namespace Drutiny\Target;

use Drutiny\Target\Service\DrushService;
use Drutiny\Target\Service\DockerService;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Target for parsing Drush aliases.
 */
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

        $lando = $this['service.exec']->get('local')->run('lando list --format=json', function ($output) {
          return json_decode($output, true);
        });

        $apps = array_filter($lando, function ($instance) use ($alias) {
          return ($instance['service'] == 'appserver') && ($instance['app'] == $alias);
        });

        if (empty($apps)) {
          throw new InvalidTargetException("Lando site '$alias' either doesn't exist or is not currently active.");
        }

        $this['lando.app'] = array_shift($apps);
        $this['service.docker'] = new DockerService($this['service.local']);
        $this['service.docker']->setContainer($this['lando.app']['name']);
        $this['service.exec']->addHandler($this['service.docker'], 'docker');

        $this['drush.root'] = '/app';

        $dir = dirname($this['lando.app']['src'][0]);
        $info = $this['service.exec']->get('local')->run(sprintf('cd %s && lando info --format=json', $dir), function ($output) {
          return json_decode($output, true);
        });

        $urls = [$uri];
        foreach ($info as $service) {
          $this['lando.'.$service['service']] = $service;
          $urls += $service['urls'] ?? [];
        }

        $urls = array_filter($urls);
        // Provide a default URI if none already provided.
        $this->setUri(array_pop($urls));
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getAvailableTargets():array
    {
      $lando = $this['service.exec']->get('local')->run('lando list --format=json', function ($output) {
        return json_decode($output, true);
      });

      $apps = array_filter($lando, function ($instance) {
        return $instance['service'] == 'appserver';
      });

      $targets = [];
      foreach ($apps as $app) {
        $dir = dirname($app['src'][0]);
        $edge = $this['service.exec']->get('local')->run(sprintf('cd %s && lando info --format=json', $dir), function ($output) {
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
