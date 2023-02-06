<?php

namespace Drutiny\Target;

use Drutiny\Target\Service\ExecutionInterface;

/**
 * Definition of a Target.
 */
interface TargetInterface
{

  /**
   * Parse the target data passed in.
   * @param $data string to parse.
   */
    public function parse(string $data, ?string $uri = null):TargetInterface;

    /**
     * Get a serviced object.
     */
    public function getService($key);

    /**
     * Allow the execution service to change depending on the target environment.
     */
    public function setExecService(ExecutionInterface $service):TargetInterface;

    /**
     * Run a command through the ExecutionService.
     */
    public function run(string $cmd, callable $preProcess, int $ttl);

    /**
     * Return the target identifier.
     */
    public function getId():string;

    /**
     * Set target reference.
     */
    public function setTargetName(string $name):TargetInterface;

    /**
     * Get target reference name.
     */
    public function getTargetName():string;

    /**
     * Get the URI for the Target.
     */
    public function getUri(): string;

    /**
     * Get a list of properties available.
     */
    public function getPropertyList(): array;

     /**
     * Get a set property.
     *
     * @throws NoSuchIndexException
     */
    public function getProperty($key):mixed;

    /**
     * Check a property path exists.
     */
    public function hasProperty($key):bool;

    /**
     * Set a property.
     */
    public function setProperty($key, $value):self;
}
