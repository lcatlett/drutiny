<?php

namespace Drutiny\Entity;

use ArrayAccess;
use ArrayIterator;
use Drutiny\Entity\Exception\DataNotFoundException;
use IteratorAggregate;
use Traversable;

/**
 * Holds data.
 */
class DataBag implements ExportableInterface, IteratorAggregate, ArrayAccess
{
    protected array $data = [];

    public function __construct(?array $data = null)
    {
        if ($data !== null) {
            $this->add($data);
        }
    }

    /**
     * Clears all data.
     */
    public function clear()
    {
        $this->data = [];
    }

    /**
     * Implements IteratorAggregate::getIterator().
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->data);
    }

    /**
     * Adds data to the service container data.
     *
     * @param array $data An array of data
     */
    public function add(array $data)
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        return $this->data;
    }

    /**
     * @deprecated
     */
    public function export():array
    {
        return array_map(function ($data) {
            if ($data instanceof ExportableInterface) {
                return $data->export();
            }

            return $data;
        }, $this->data);
    }

    public function __get(string $name)
    {
        return $this->get($name);
    }

    public function __isset(string $name)
    {
        return \array_key_exists($name, $this->data);
    }

    public function __set($name, $value)
    {
        return $this->set($name, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name)
    {
        if (!\array_key_exists($name, $this->data)) {
            if (!$name) {
                throw new DataNotFoundException($name);
            }

            $alternatives = [];
            foreach ($this->data as $key => $dataValue) {
                $lev = levenshtein($name, $key);
                if ($lev <= \strlen($name) / 3 || false !== strpos($key, $name)) {
                    $alternatives[] = $key;
                }
            }

            $nonNestedAlternative = null;
            if (!\count($alternatives) && false !== strpos($name, '.')) {
                $namePartsLength = array_map('strlen', explode('.', $name));
                $key = substr($name, 0, -1 * (1 + array_pop($namePartsLength)));
                while (\count($namePartsLength)) {
                    if ($this->has($key)) {
                        if (\is_array($this->get($key))) {
                            $nonNestedAlternative = $key;
                        }
                        break;
                    }

                    $key = substr($key, 0, -1 * (1 + array_pop($namePartsLength)));
                }
            }

            throw new DataNotFoundException($name, null, null, null, $alternatives, $nonNestedAlternative);
        }

        return $this->data[$name];
    }

    /**
     * Sets a service container data.
     *
     * @param string $name  The data name
     * @param mixed  $value The data value
     */
    public function set(string $name, $value):void
    {
        $this->data[$name] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $name):bool
    {
        return \array_key_exists((string) $name, $this->data);
    }

    /**
     * Removes a data.
     *
     * @param string $name The data name
     */
    public function remove(string $name):void
    {
        unset($this->data[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set($offset, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->remove($offset);
    }
}
