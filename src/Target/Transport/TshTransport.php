<?php

namespace Drutiny\Target\Transport;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * RemoteService over Teleport (TSH).
 */
class TshTransport extends SshTransport {

  const ERROR_AMBIGUOUS_HOST_MSG = 'ambiguous host could match multiple nodes';

  protected string $tshProxy = '';

  /**
   * {@inheritdoc}
   */
  public function send(Process $command, ?callable $processor = null)
  {
    try {
      return parent::send($command, $processor);
    }
    // Look for teleport issue where an ambiguous host was detected.
    catch (ProcessFailedException $e) {
      $err = $e->getProcess()->getErrorOutput();
      if (strpos($err, self::ERROR_AMBIGUOUS_HOST_MSG) === false) {
        throw $e;
      }
      $output = $e->getMessage();
      $host = preg_quote($this->sshConfig['Host']);

      // Choose the first node found.
      if (!preg_match("/$host ([0-9a-z\-]+) /", $output, $matches)) {
        throw $e;
      }
      $node = $matches[1];

      $this->logger->warning("Teleport found multiple nodes for {$this->sshConfig['Host']}. Using first found: $node.");

      // Set future calls to use the Node rather than the hostname to avoid
      // ambiguous errors for the remainder of the process.
      $this->sshConfig['Host'] = $node;

      // Retry.
      return parent::send($command, $processor);
    }
  }

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
    if (isset($options['tsh.proxy'])) {
      $this->tshProxy = $options['tsh.proxy'];
    }
    unset($options['tsh.proxy']);

    // If telesync is enabled, then specify an explicit proxy.
    if (!empty($this->tshProxy)) {
      $args[] = '--proxy=' . $this->tshProxy;
    }
    
    foreach ($options as $key => $value) {
      $args[] = '-o';
      $args[] = sprintf('%s=%s', $key, $value);
    }
    $args[] = $host;
    return implode(' ', $args);
  }
}
