<?php

namespace Drutiny\Target\Service;

use Drutiny\Target\InvalidTargetException;

/**
 * RemoteService over Teleport (TSH).
 */
class TshRemoteService extends RemoteService {

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

    $activeRegion = $this->getActiveTelesyncRegion();

    // This means telesync is connected to a different region and we'd have to change that.
    if (!empty($activeRegion) && ($activeRegion != $this->telesyncRegion)) {
      throw new InvalidTargetException("Cannot access target on current telesync cluster '$activeRegion'. You must connect to '{$this->telesyncRegion}' instead.");
    }

    foreach ($options as $key => $value) {
      $args[] = '-o';
      $args[] = sprintf('%s=%s', $key, $value);
    }
    $args[] = $host;
    return implode(' ', $args);
  }

  protected function getActiveTelesyncRegion():string
  {
    return $this->local->run('telesync us-east-1 | grep Cluster | head -1 | awk \'{print $2}\'', fn($o) => trim($o), 60);
  }
}
