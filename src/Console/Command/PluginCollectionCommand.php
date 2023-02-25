<?php

namespace Drutiny\Console\Command;

use Drutiny\Attribute\PluginField;
use Drutiny\Plugin as DrutinyPlugin;
use Drutiny\Plugin\FieldType;
use Drutiny\Plugin\PluginCollection;
use Drutiny\Plugin\Question;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PluginCollectionCommand {

    public function __construct(protected PluginCollection $pluginCollection) {

    }

    /**
     * List the plugins registred within a plugin colleciton.
     */
    public function list(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $rows = [];
        foreach ($this->pluginCollection->getAll() as $key => $plugin) {
            $row = [];
            $headers = [];
            foreach ($plugin->getFieldAttributes() as $field) {
                $row[$field->name] = ($field->type == FieldType::CREDENTIAL && $input->getOption('show-credentials') == false) ? '****' :  $plugin->{$field->name};
                $headers[] = $field->name;
            }
            $rows[$key] = $row;
        }
        ksort($rows);

        if (!empty($rows)) {
            $io->table($headers, $rows);
        }
        else {
            $io->text("There are no plugins setup for ".$this->pluginCollection->getName());
            $io->text("Run ".$this->pluginCollection->getName().":setup to configure a new plugin setting.");
        }
        
        return 0;
    }

    /**
     * Add a plugin entry to a plugin collection.
     */
    public function add(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $namespace = $input->getArgument($this->pluginCollection->getKeyField()->name);

        if ($this->pluginCollection->has($namespace) && !$io->confirm("$namespace already exists. Override it?")) {
            $io->text("Aborted.");
            return 0;
        }
        $plugin = $this->pluginCollection->has($namespace) ? $this->pluginCollection->get($namespace) : $this->pluginCollection->create($namespace);

        $values = [];
        foreach ($this->pluginCollection->getFieldAttributes() as $field) {
            if ($field->name == $this->pluginCollection->getKeyField()->name) {
                $values[$field->name] = $namespace;
                continue;
            }
            $values[$field->name] = $input->getOption($field->name) ?? $this->setupField($field, $plugin, $io);
        }
        $plugin->saveAs($values);

        $io->success("Plugin '$namespace' has been setup.");
        return 0;
    }

    public function delete(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $namespace = $input->getArgument($this->pluginCollection->getKeyField()->name);

        if (!$this->pluginCollection->has($namespace)) {
            $io->error("$namespace does not exist.");
            return 1;
        }

        if (!$io->confirm("Are you sure you want to remove $namespace?")) {
            $io->text("Aborted.");
            return 0;
        }

        $this->pluginCollection->get($namespace)->delete();
        $io->success("$namespace has been removed.");
        return 0;
    }

    /**
     * Get user input to get the value of a field.
     */
    protected function setupField(PluginField $field, DrutinyPlugin $plugin, SymfonyStyle $io)
    {
        $extra = ' ';
        if (isset($plugin->{$field->name})) {
            $existing_value = $plugin->{$field->name};
            $extra = "\n<comment>An existing credential exists.\n";
            if ($field->type == FieldType::CONFIG) {
                $extra .= "Existing value: {$existing_value}\n";
            }
            $extra .= "Leave blank to use existing value.</comment>\n";
        }
        $ask = sprintf("%s%s\n<info>[%s]</info>:     ", $extra, ucfirst($field->description), $field->name);
        do {
            $value = match ($field->ask) {
                Question::CHOICE => $io->choice($ask, $field->choices, $plugin->{$field->name}),
                Question::CONFIRMATION => $io->confirm("$ask (y/n)?", $plugin->{$field->name} ?? true),
                Question::DEFAULT => $io->ask($ask,$plugin->{$field->name})
            };
            if (!call_user_func($field->validation, $value)) {
                $io->error("Input failed validation");
                continue;
            }
            break;
        }
        while (true);
        return $value;
    }
}