<?php

namespace Drutiny;

use Drutiny\Config\Config;
use Drutiny\Plugin\PluginRequiredException;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class Plugin {
    const FIELD_TYPE_CONFIG = 3;
    const FIELD_TYPE_CREDENTIAL = 5;
    const FIELD_DEFAULT_QUESTION = 3;
    const FIELD_CHOICE_QUESTION = 5;
    const FIELD_CONFIRMATION_QUESTION = 7;

    protected array $fields = [];
    protected array $values = [];

    private Config $config;
    private Config $credentials;

    protected InputInterface $input;
    protected OutputInterface $output;

    public function __construct(ContainerInterface $container, InputInterface $input, OutputInterface $output)
    {
        $this->config = $container->get('config')->load($this->getName());
        $this->credentials = $container->get('credentials')->load($this->getName());
        $this->input = $input;
        $this->output = $output;
        $this->configure();
    }

    /**
     * @return string The name of the plugin.
     */
    abstract public function getName();

    /**
     * Callback to add fields to the plugin.
     */
    abstract protected function configure();

    protected function verify():bool
    {
        return true;
    }

    protected function getStorage($name)
    {
      return $this->fields[$name]['type'] == static::FIELD_TYPE_CREDENTIAL ? $this->credentials : $this->config;
    }

    final public function getField($name)
    {
      return $this->getStorage($name)->{$name} ?? null;
    }

    final public function isInstalled():bool
    {
      try {
        $this->load();
        return true;
      }
      catch (PluginRequiredException $e) {
        return false;
      }
    }

    final public function load()
    {
        $configuration = [];
        foreach ($this->fields as $name => $field) {
            $storage = $this->getStorage($name);

            if (!isset($storage->{$name})) {
              if (isset($field['default'])) {
                $configuration[$name] = $field['default'];
                continue;
              }

              // Indicates the plugin is not installed yet.
              throw new PluginRequiredException($this->getName());
            }

            $value = $storage->{$name};
            $configuration[$name] = $value;
        }
        return $configuration;
    }

    final public function setup()
    {
        try {
          $config = $this->load();
        }
        catch (PluginRequiredException $e) {}

        foreach ($this->fields as $name => $field) {
            $storage = $this->getStorage($name);
            $value = $this->setupField($name, $config[$name] ?? null);
            $storage->{$name} = $value;
        }
    }

    public function setField($name, $value = null):void
    {
      $storage = $this->getStorage($name);
      $storage->{$name} = $value;
    }

    /**
     * Get user input to get the value of a field.
     */
    protected function setupField($name, $default_value = null)
    {
        $field = $this->fields[$name];
        $extra = ' ';
        if (isset($default_value)) {
            $extra = "\n<comment>An existing credential exists.\n";
            if ($field['type'] == static::FIELD_TYPE_CONFIG) {
                $extra .= "Existing value: $default_value\n";
            }
            $extra .= "Leave blank to use existing value.</comment>\n";
        }
        $ask = sprintf("%s%s\n<info>[%s]</info>: ", $extra, ucfirst($field['description']), $name);
        switch ($field['ask']) {
           case static::FIELD_CHOICE_QUESTION:
              $question = new ChoiceQuestion($ask, $field['choices'], $default_value ?? $field['default']);
              break;

           case static::FIELD_CONFIRMATION_QUESTION:
              $default_value = $default_value ?? $field['default'];
              $default_value = is_bool($default_value) ? $default_value : false;
              $question = new ConfirmationQuestion("$ask (y/n)?", $default_value ?? $field['default']);
              break;

           case static::FIELD_DEFAULT_QUESTION:
           default:
              $question = new Question($ask, $default_value ?? $field['default']);
              break;
        }

        $helper = new QuestionHelper();
        do {
            $value = $helper->ask($this->input, $this->output, $question);
            if (!$field['validation']($value)) {
                $this->output->writeln('<error>Input failed validation. Please try again.</error>');
                continue;
            }
            break;
        }
        while (true);

        return $value;
    }

    /**
     * Add a configurable field to the plugin schema.
     *
     * @param string $name The name of the field.
     * @param string $description A description of the field purpose.
     * @param int $type A constant indicating if the field is a config or credential.
     * @param mixed $default The default value.
     * @param string $data_type A constant representing the data type.
     * @param int $ask A constant depicting the type of question to ask.
     * @param array $choices an array of choices to choose from for FIELD_CHOICE_QUESTION types.
     */
    protected function addField(
      string $name,
      string $description,
      int $type = Plugin::FIELD_TYPE_CONFIG,
      $default = null,
      callable $validation = null,
      int $ask = Plugin::FIELD_DEFAULT_QUESTION,
      array $choices = [])
    {
        $this->fields[$name] = [
            'name' => $name,
            'description' => $description,
            'default' => $default,
            'validation' => $validation ?? 'is_string',
            'type' => $type,
            'ask' => $ask,
            'choices' => $choices,
        ];

        if ($this->fields[$name]['ask'] == static::FIELD_CONFIRMATION_QUESTION) {
          $this->fields[$name]['validation'] = $validation ?? 'is_bool';
        }
        return $this;
    }

    /**
     * Delete the configuration from the plugin storage.
     * @return void
     */
    public function delete()
    {
      foreach (array_keys($this->fields) as $key) {
        $this->getStorage($key)->delete();
      }
    }
}
