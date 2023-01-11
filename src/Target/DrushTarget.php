<?php

namespace Drutiny\Target;

use Drutiny\Target\Service\DrushService;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Target for parsing Drush aliases.
 */
class DrushTarget extends Target implements
  TargetInterface, TargetSourceInterface,
  DrushTargetInterface, FilesystemInterface
{

  protected bool $hasBuilt = false;

  /**
   * {@inheritdoc}
   */
  public function getId():string
  {
    return $this['drush.alias'];
  }

  /**
   * @inheritdoc
   * Implements Target::parse().
   */
    public function parse(string $alias, ?string $uri = NULL):TargetInterface
    {

        $this['drush.alias'] = $alias;

        $status_cmd = 'drush site:alias $DRUSH_ALIAS --format=json';
        $drush_properties = $this['service.exec']->get('local')->run($status_cmd, function ($output) use ($alias) {
          $json = json_decode($output, true);
          $index = substr($alias, 1);
          return $json[$index] ?? array_shift($json);
        });

        $this['drush']->add($drush_properties);

        // Provide a default URI if none already provided.
        if ($uri) {
          parent::setUri($uri);
        }
        elseif (isset($drush_properties['uri']) && !$this->hasProperty('uri')) {
          parent::setUri($drush_properties['uri']);
        }

        $this->buildAttributes();
        return $this;
    }

    public function buildAttributes() {
        $this->hasBuilt = true;
        $service = new DrushService($this['service.exec']);

        if ($url = $this->getUri()) {
          $service->setUrl($url);
        }

        try {
          // Default Drush root.
          //$this['drush.root'] = '.';

          $status = $service->status(['format' => 'json'])
             ->run(function ($output) use ($service) {
               return $service->decodeDirtyJson($output);
             });

          foreach ($status as $key => $value) {
            $this['drush.'.$key] = $value;
          }

          $this['service.drush'] = $service;

          $version = $this['service.exec']->run('php -v | head -1 | awk \'{print $2}\'');
          $this['php_version'] = trim($version);

          return $this;
        }
        catch (ProcessFailedException $e) {
          throw new InvalidTargetException($e->getMessage());
        }

        foreach ($status as $key => $value) {
          $this['drush.'.$key] = $value;
        }

        $this['service.drush'] = $service;

        $version = $this['service.exec']->run('php -v | head -1 | awk \'{print $2}\'');
        $this['php_version'] = trim($version);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setUri(string $uri)
    {
      parent::setUri($uri);
      // Rebuild the drush attributes.
      return $this->buildAttributes();
    }

    /**
     * {@inheritdoc}
     */
    public function getDirectory():string
    {
      if (!$this->hasBuilt) {
        $this->buildAttributes();
      }
      return $this['drush.root'];
    }

    /**
     * {@inheritdoc}
     */
    public function getAvailableTargets():array
    {
      $aliases = $this['service.exec']->get('local')->run('drush site:alias --format=json', function ($output) {
        return json_decode($output, true);
      });

      if (empty($aliases)) {
        $this->logger->error("Drush failed to return any aliases. Please ensure your local drush is up to date, has aliases available and returns a valid json response for `drush site:alias --format=json`.");
        return [];
      }

      $valid = array_filter(array_keys($aliases), function ($a) {
        return strpos($a, '.') !== FALSE;
      });

      $targets = [];
      foreach ($valid as $name) {
        $alias = $aliases[$name];
        $targets[] = [
          'id' => $name,
          'uri' => $alias['uri'] ?? '',
          'name' => $name
        ];
      }
      return $targets;
    }
}
