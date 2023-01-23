<?php

namespace Drutiny\Target\Service;

class ExecutionService implements ExecutionInterface {
  protected array $serviceHandlers = [];
  protected array $badHandlers = [];

  public function __construct(LocalService $service)
  {
    $this->serviceHandlers['local'] = $service;
  }

  /**
   * Attempt to find a service handler with a particular method and call it.
   */
  public function __call($method, $args)
  {
    $errors = [];
    // Start from the last added service.
    foreach (array_reverse($this->serviceHandlers) as $index => $handler) {
      if (isset($this->badHandlers[$method][$index])) {
        continue;
      }
      try {
        if (!method_exists($handler, $method)) {
          throw new \Exception(sprintf("Method '%s' doesn't exist on %s", $method, get_class($handler)));
        }
        return call_user_func_array([$handler, $method], $args);
      }
      catch (\Exception $e) {
        // Track the bad handlers as they shouldn't be attempted in future for
        // a given method.
        $this->badHandlers[$method][$index] = $index;
        $errors[get_class($handler).'['.$index.':'.$method.']'] = $e;
      }
    }
    throw new ExecutionServiceException($errors, $method, $args);
  }

  /**
   * Register a new ExecutionInterface service handler.
   */
  public function addHandler(ExecutionInterface $service, ?string $name = null):ExecutionService
  {
    if (isset($name)) {
      $this->serviceHandlers[$name] = $service;
    }
    else {
      $this->serviceHandlers[] = $service;
    }
    return $this;
  }

  /**
   * Get a specific handler by its name.
   */
  public function get(string $name):ExecutionInterface
  {
    if (!$this->has($name)) {
      throw new \Exception("No such service: '$name'.");
    }
    return $this->serviceHandlers[$name];
  }

  public function has(string $name):bool
  {
    return isset($this->serviceHandlers[$name]);
  }

  /**
   * Get a service of a particular class or interface.
   */
  public function getInstanceType($instance):ExecutionInterface
  {
    $handlers = array_filter($this->serviceHandlers, fn($h) => $h instanceof $instance);
    if (empty($handlers)) {
      throw new \Exception("No services of type $instance found.");
    }
    // Return the last one added to the stack.
    return array_pop($handlers);
  }

  /**
   * {@inheritdoc}
   */
  public function run(string $cmd, callable $outputProcessor = NULL, int $ttl = 3600)
  {
    return $this->__call('run', func_get_args());
  }

  /**
   * {@inheritdoc}
   */
  public function hasEnvVar(string $name):bool
  {
    return $this->__call('hasEnvVar', func_get_args());
  }
}
