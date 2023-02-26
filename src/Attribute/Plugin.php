<?php

namespace Drutiny\Attribute;

use Attribute;
use Drutiny\Plugin as DrutinyPlugin;
use ReflectionClass;

#[Attribute]
class Plugin implements KeyableAttributeInterface {
    protected PluginFieldCollection $fields;
    public function __construct(
        public readonly string $name,
        public readonly bool $hidden = false,
        public readonly string $class = DrutinyPlugin::class,
        public readonly string $as = '$plugin',
        public readonly ?string $collectionKey = null
    ) {
        $this->fields = new PluginFieldCollection;
    } 

    public function getKey():string
    {
        return $this->name;
    }

    /**
     * Get an array of field attributes keyed by field name.
     */
    public function buildFieldAttributes(string $class_name):self
    {
      $reflection = new ReflectionClass($class_name);
      foreach ($reflection->getAttributes(PluginField::class) as $attribute) {
        $this->fields->add($attribute->newInstance());
      }
      return $this;
    }

    public function getFieldAttributes():array
    {
        return $this->fields->getAll();
    }

    public function getField($name):PluginField
    {
        return $this->fields->get($name);
    }
}