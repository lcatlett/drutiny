<?php

namespace Drutiny\Target;

use Drutiny\Entity\DataBag;
use Drutiny\Entity\EventDispatchedDataBag;
use Drutiny\Entity\Exception\DataNotFoundException;
use Drutiny\Target\Service\ExecutionInterface;
use Drutiny\Event\DataBagEvent;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\Exception\NoSuchIndexException;
use Psr\Log\LoggerInterface;

/**
 * Basic function of a Target.
 */
abstract class Target implements \ArrayAccess, ExecutionInterface
{
    /* @var PropertyAccess */
    protected $propertyAccessor;
    protected $properties;
    protected LoggerInterface $logger;
    protected $dispatcher;
    private string $targetName;

    public function __construct(ExecutionInterface $service, LoggerInterface $logger, EventDispatchedDataBag $databag)
    {
        if (method_exists($logger, 'withName')) {
            $logger = $logger->withName('target');
        }
        $this->logger = $logger;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()
      ->enableExceptionOnInvalidIndex()
      ->getPropertyAccessor();

        $this->properties = $databag->setObject($this);

        $service->setTarget($this);

        $this['service.local'] = $service->get('local');
        $this['service.exec'] = $service;
    }

    final public function setTargetName(string $name): TargetInterface
    {
        $this->targetName = $name;
        return $this;
    }

    final public function getTargetName(): string
    {
        return $this->targetName;
    }

    /**
     * {@inheritdoc}
     */
    public function setUri(string $uri)
    {
        $this->setProperty('domain', parse_url($uri, PHP_URL_HOST) ?? $uri);
        return $this->setProperty('uri', $uri);
    }

    /**
     * {@inheritdoc}
     */
    public function getUri()
    {
        return $this->getProperty('uri');
    }

    /**
     * {@inheritdoc}
     */
    public function hasEnvVar($name): bool
    {
        return $this['service.exec']->hasEnvVar($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getService($key)
    {
        return $this->getProperty('service.'.$key);
    }

    /**
     * Allow the execution service to change depending on the target environment.
     */
    public function setExecService(ExecutionInterface $service)
    {
        return $this->setProperty('service.exec', $service);
    }

    /**
     * {@inheritdoc}
     */
    public function run(string $cmd, callable $preProcess, int $ttl)
    {
        return $this->getService('exec')->run($cmd, $preProcess, $ttl);
    }

    /**
     * Set a property.
     */
    public function setProperty($key, $value)
    {
        $this->confirmPropertyPath($key);

        // If the property is already a DataBag object and is attempting to be
        // replaced with a non-DataBag value, then throw an exception as this
        // will loose target data and create problems accessing deeper
        // property references.
        if ((!($value instanceof DataBag)) && $this->hasProperty($key) && ($this->getProperty($key) instanceof DataBag)) {
            $type = is_object($value) ? get_class($value) : gettype($value);
            throw new TargetPropertyException("Property '$key' contains multiple dimensions of data and cannot be overriden with data of type: ". $type);
        }
        $this->propertyAccessor->setValue($this->properties, $key, $value);
        return $this;
    }

    /**
     * Ensure the property pathway exists.
     */
    protected function confirmPropertyPath($path)
    {
        // Handle top level properties.
        if (strpos($path, '.') === false) {
            return $this;
        }

        $bits = explode('.', $path);
        $total_bits = count($bits);
        $new_paths = [];
        do {
            $pathway = implode('.', $bits);

            // Do not create the $path pathway as setProperty will do this for us.
            if ($pathway == $path) {
                continue;
            }

            if (empty($pathway)) {
                break;
            }

            // If the pathway doesn't exist yet, create it as a new DataBag.
            if ($this->hasProperty($pathway)) {
                break;
            }

            // If the parent is a DataBag then the pathway is settable.
            if ($total_bits == count($bits) && $this->getParentProperty($pathway) instanceof DataBag) {
                break;
            }
            $new_paths[] = $pathway;
        } while (array_pop($bits));

        // Create all the DataBag objects required to support this pathway.
        foreach (array_reverse($new_paths) as $pathway) {
            $this->setProperty($pathway, $this->properties->create()->setEventPrefix($pathway));
        }
        return $this;
    }

    /**
     * Find the parent value.
     */
    private function getParentProperty($path)
    {
        if (strpos($path, '.') === false) {
            return false;
        }
        $bits = explode('.', $path);
        array_pop($bits);
        $path = implode('.', $bits);
        return $this->hasProperty($path) ? $this->getProperty($path) : false;
    }

    /**
     * Get a set property.
     *
     * @exception NoSuchIndexException
     */
    public function getProperty($key)
    {
        return $this->propertyAccessor->getValue($this->properties, $key);
    }

    /**
     *  Alias for getProperty().
     */
    public function __get($key)
    {
        return $this->getProperty($key);
    }

    /**
     * Get a list of properties available.
     */
    public function getPropertyList()
    {
        $paths = $this->getDataPaths($this->properties);
        sort($paths);
        return $paths;
    }

    /**
     * Traverse DataBags to obtain a list of property pathways.
     */
    private function getDataPaths(Databag $bag, $prefix = '')
    {
        $keys = [];
        foreach ($bag->all() as $key => $value) {
            // Periods are reserved characters and cannot be used for DataPaths.
            if (strpos($key, '.') !== false) {
                continue;
            }
            $keys[] = $prefix.$key;
            if ($value instanceof Databag) {
                $keys = array_merge($this->getDataPaths($value, $prefix.$key.'.'), $keys);
            }
        }
        return $keys;
    }

    /**
     * Check a property path exists.
     */
    public function hasProperty($key)
    {
        try {
            $this->propertyAccessor->getValue($this->properties, $key);
            return true;
        } catch (NoSuchIndexException $e) {
            return false;
        } catch (DataNotFoundException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            throw new \Exception(__CLASS__ . ' does not support numeric indexes as properties.');
        }
        return $this->setProperty($offset, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        return $this->hasProperty($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        throw new \Exception("Cannot unset $offset. Properties cannot be removed. Please set to null instead.");
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        return $this->hasProperty($offset) ? $this->getProperty($offset) : null;
    }

    abstract public function parse(string $data, ?string $uri = null): TargetInterface;
}
