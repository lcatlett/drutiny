<?php

namespace Drutiny;

use Drutiny\Attribute\Plugin as PluginAttribute;
use Drutiny\Config\ConfigInterface;
use Drutiny\Plugin\FieldType;
use Drutiny\Plugin\PluginInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Plugin implements PluginInterface {
    private array $stores;

    public function __construct(
      ConfigInterface $pluginConfig, 
      ConfigInterface $pluginCredentials, 
      protected InputInterface $input, 
      protected OutputInterface $output,
      private PluginAttribute $attribute
    )
    {
        // Set the name from the AsPlugin attribute.
        $this->stores[FieldType::CONFIG->key()] = $pluginConfig;
        $this->stores[FieldType::CREDENTIAL->key()] = $pluginCredentials;
        $this->input = $input;
        $this->output = $output;
        $this->configure();
    }

    final public function getName():string
    {
      return $this->attribute->name;
    }

    /**
     * Get an array of field attributes keyed by field name.
     */
    final public function getFieldAttributes():array
    {
      return $this->attribute->getFieldAttributes();
    }

    /**
     * Get a plugin field value or default value.
     */
    final public function __get($name):mixed
    {
      $field_type = $this->attribute->getField($name)->type;
      $store = $this->stores[$field_type->key()];
      if (!isset($store[$name])) {
        return $this->attribute->getField($name)->default;
      }
      return $store[$name];
    }

    final public function __isset($name)
    {
      $field_type = $this->attribute->getField($name)->type;
      $store = $this->stores[$field_type->key()];
      return isset($store[$name]);
    }

    /**
     * Callback to add fields to the plugin.
     */
    protected function configure() {}

    /**
     * Determines if the plugin is installed or not.
     */
    final public function isInstalled():bool
    {
      $has_stored_values = false;
      foreach ($this->getFieldAttributes() as $name => $field_info) {
        $has_stored_values = $has_stored_values || $this->__isset($name);
      }
      return $has_stored_values;
    }

    final public function isHidden():bool
    {
      return $this->attribute->hidden;
    }

    public function saveAs(array $values):void
    {
      foreach ($this->getFieldAttributes() as $field) {
        if (!isset($values[$field->name])) {
          continue;
        }
        $this->stores[$field->type->key()][$field->name] = $values[$field->name];
      }
      $this->stores[FieldType::CONFIG->key()]->save();
      $this->stores[FieldType::CREDENTIAL->key()]->save();
    }

    /**
     * Delete the configuration from the plugin storage.
     * @return void
     */
    public function delete():void
    {
      $this->stores[FieldType::CONFIG->key()]->delete();
      $this->stores[FieldType::CREDENTIAL->key()]->delete();
    }
}
