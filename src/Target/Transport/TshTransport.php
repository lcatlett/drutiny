<?php

namespace Drutiny\Target\Transport;

use Drutiny\Target\InvalidTargetException;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Process\Process;

/**
 * RemoteService over Teleport (TSH).
 */
class TshTransport extends SshTransport {

  protected string $telesyncRegion = '';

  /**
   * Formulate an SSH command. E.g. ssh -o User=foo hostname.bar
   */
  protected function getRemoteCall($bin = 'ssh')
  {
    $args = ['tsh ' . $bin];
    $options = $this->sshConfig;
    if (!isset($this->sshConfig['Host'])) {
      throw new \InvalidArgumentException("Missing 'Host' option in SSH Config.");
    }

    // Host is not a command line support option and must be passed as an argument.
    $host = $this->sshConfig['Host'];
    unset($options['Host']);

    if (isset($options['User'])) {
      $host = $options['User'].'@'.$host;
      unset($options['User']);
    }

    // Support for telesync region.
    if (isset($options['telesync.region'])) {
      $this->telesyncRegion = $options['telesync.region'];
    }
    unset($options['telesync.region']);

    // If telesync is enabled, then specify an explicit proxy.
    if (!empty($this->telesyncRegion)) {
      $activeRegions = $this->getActiveTelesyncRegions();
      // This means telesync is connected to a different region and we'd have to change that.
      if (!empty($activeRegions) && !in_array($this->telesyncRegion, $activeRegions)) {
        throw new InvalidTargetException("Cannot access target on current telesync clusters: " . implode(', ', $activeRegions) . ". You must connect to '{$this->telesyncRegion}' instead.");
      }
      $args[] = '--proxy=' . $this->telesyncRegion;
    }
    
    foreach ($options as $key => $value) {
      $args[] = '-o';
      $args[] = sprintf('%s=%s', $key, $value);
    }
    $args[] = $host;
    return implode(' ', $args);
  }

  protected function getActiveTelesyncRegions():array
  {
    return $this->localCommand->run(Process::fromShellCommandline('telesync status | grep Cluster | awk \'{print $2}\''), function (string $output, CacheItemInterface $cache):array {
      $cache->expiresAfter(300);
      $clusters = explode("\n", $output);
      return array_filter(array_map('trim', $clusters));
    }, 60);
  }
}
