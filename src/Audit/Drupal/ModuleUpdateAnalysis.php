<?php

namespace Drutiny\Audit\Drupal;

use Drutiny\Sandbox\Sandbox;
use Composer\Semver\Comparator;
use Composer\Semver\Semver;

/**
 * Generic module is enabled check.
 *
 */
class ModuleUpdateAnalysis extends ModuleAnalysis
{

  const UPDATES_URL = 'https://updates.drupal.org/release-history/%module%/%core_version%';

  /**
   *
   */
    public function gather(Sandbox $sandbox)
    {
        // Gather module data from drush pm-list.
        parent::gather($sandbox);

        $modules = $this->get('modules');
        $modules = $this->getModuleFilepathData($modules);
        $modules = $this->getDecoratedModuleData($modules);
        $this->set('modules', $modules);
        $core = $this->getRecentVersions('drupal', $this->target['drush.drupal-version']);
        $core['version'] = $this->target['drush.drupal-version'];
        $this->set('core', $core);
    }

    /**
     * Decorate module data with filepath metadata.
     */
    protected function getModuleFilepathData($modules):array
    {
      // Get the locations of all the modules in the codebase.
      $filepaths = $this->target->getService('exec')->run('find $DRUSH_ROOT \( -name \*.info.yml -or -name \*.info \) -type f', function ($output) {
        return array_map(function ($line) {
          return trim($line);
        }, explode(PHP_EOL, $output));
      });

      $module_filepaths = [];

      foreach ($filepaths as $filepath) {
        list($module_name, , ) = explode('.', basename($filepath));
        $module_filepaths[$module_name] = $filepath;
      }

      foreach($modules as $module => &$info) {
        $info['filepath'] = $module_filepaths[$module];
        $info['dirname'] = dirname($info['filepath']);
        $info['name'] = $module;
        switch (true) {
          case strpos($info['filepath'], 'core/modules')  !== false:
            $info['type'] = 'core';
            break;

          case strpos($info['filepath'], 'modules/contrib')  !== false:
            $info['type'] = 'contrib';
            break;

          case strpos($info['filepath'], 'modules/custom')  !== false:
            $info['type'] = 'custom';
            break;

          // Defaulting to contrib will check for existance of the module
          // as the default behaviour.
          default:
            $info['type'] = 'contrib';
            break;
        }
      }
      return $modules;
    }

    /**
     * Get decorated module data.
     */
    protected function getDecoratedModuleData($modules):array
    {
      foreach ($modules as $module => $info) {

        // If the module is embedded inside another project then its a sub-module.
        $parent_modules = array_filter($modules, function ($mod) use ($info) {
          if ($info['name'] == $mod['name']) {
            return false;
          }
          return strpos($info['filepath'], $mod['dirname'] . '/') !== false;
        });

        if (count($parent_modules)) {
          $modules[$module]['type'] = 'sub-module';
          $modules[$module]['parent'] = reset($parent_modules)['name'];
          $modules[$modules[$module]['parent']]['sub-modules'][] = $modules[$module]['name'];
        }

        $modules[$module]['supported'] = false;

        if ($modules[$module]['type'] == 'contrib') {
          $modules[$module]['available_releases'] = $this->getRecentVersions($info['type'] == 'core' ? 'drupal' : $module, $info['version']);

          if (!$modules[$module]['available_releases']) {
            $modules[$module]['type'] = 'custom';
            unset($modules[$module]['available_releases']);
          }

          $supported = array_filter($modules[$module]['available_releases']['supported_branches'] ?? [], function ($branch) use ($info) {
            return strpos($info['version'], $branch) === 0;
          });
          $modules[$module]['supported'] = !empty($supported);
        }
      }
      return $modules;
    }

    protected function getRecentVersions($project, $version)
    {
      static $responses;

      list($major, ) = explode('.', $version, 2);

      $core_version = $this->target['drush.drupal-version'];
      list($core_major_version, ) = explode('.', $core_version);

      $url = strtr(self::UPDATES_URL, [
        '%module%' => $project,
        '%core_version%' => ($core_major_version == 7) ? $core_major_version . '.x' : 'current',
      ]);

      if (!isset($responses[$url])) {
        $responses[$url] = false;

        $history = $this->runCacheable($url, function () use ($url) {
          $client = $this->container->get('http.client')->create();
          $response = $client->request('GET', $url);

          if ($response->getStatusCode() != 200) {
            return false;
          }

          return $this->toArray(simplexml_load_string($response->getBody()));
        });

        // No release history was found.
        if (!is_array($history)) {
          return false;
        }

        // Only include newer releases. This keeps memory usage down.
        $semantic_version = $this->getSemanticVersion($version);
        $history['releases'] = array_filter($history['releases'], function ($release) use ($semantic_version, $core_major_version) {
          if (isset($release['terms'])) {
            $tags = array_map(function ($term) {
              return $term['value'];
            }, $release['terms']);
            // Don't pass through insecure releases as options.
            if (in_array('Insecure', $tags)) {
              return false;
            }
          }
          return Comparator::greaterThanOrEqualTo($this->getSemanticVersion($release['version']), $semantic_version);
        });

        if (isset($history['supported_branches'])) {
          $history['supported_branches'] = explode(',', $history['supported_branches']);
        }
        else {
          $history['supported_branches'] = [];
        }

        foreach ($history['releases'] as &$release) {
          $release['is_current_release'] = $this->getSemanticVersion($release['version']) == $semantic_version;
          if (!empty($semantic_version)) {
            $release['minor_upgrade'] = Semver::satisfies($this->getSemanticVersion($release['version']), '^'.$semantic_version);
          }

          // Indicate if the release is from a supported branch.
          $release['supported'] = count(array_filter($history['supported_branches'], function ($branch) use ($release) {
            return strpos($release['version'], $branch) === 0;
          })) > 0;

          $release['semantic_version'] = $this->parseSemanticVersion($this->getSemanticVersion($release['version']));

          if (empty($release['terms'])) {
            continue;
          }
          foreach ($release['terms'] as $flag) {
            $history['flags'][] = $flag['value'];
          }
        }

        $history['flags'] = array_values(array_unique($history['flags'] ?? []));

        $responses[$url] = $history;
      }

      return $responses[$url];
    }

    protected function toArray(\SimpleXMLElement $el)
    {
      $array = [];

      if (!$el->count()) {
        return (string) $el;
      }

      $keys = [];
      foreach ($el->children() as $c) {
        $keys[] = $c->getName();
      }

      $is_assoc = count($keys) == count(array_unique($keys));

      foreach ($el->children() as $c) {
        if ($is_assoc) {
          $array[$c->getName()] = $this->toArray($c);
        }
        else {
          $array[] = $this->toArray($c);
        }
      }

      return $array;
    }

    protected function getSemanticVersion($version)
    {
      // Sanitize the version.
      $version = preg_replace('/([^ ])( .*)/', '$1', $version);

      if (preg_match('/([0-9]+).x-(.*)/', $version, $matches)) {
        $version = $matches[2];
      }
      list($semver, ) = explode('-', $version);

      // 3.x => 3.0-dev
      $semver = strtr($semver, [
        'x' => '0-dev'
      ]);
      return $semver;
    }

    protected function parseSemanticVersion($version)
    {
      if (!preg_match('/^(([0-9]+)\.)?([0-9x]+)\.([0-9x]+)(.*)$/', $version, $matches)) {
        return false;
      }
      list(,,$major, $minor, $patch, $prerelease) = $matches;
      if (empty($major)) {
        $major = $minor;
        $minor = $patch;
        $patch = '';
      }
      return [
        'major' => $major,
        'minor' => $minor,
        'patch' => $patch,
        'pre-release' => substr($prerelease, 1),
      ];
    }
}
