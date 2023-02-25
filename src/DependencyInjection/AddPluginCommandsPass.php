<?php

namespace Drutiny\DependencyInjection;

use Drutiny\Console\Application;
use Drutiny\Console\Command\PluginCollectionCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AddPluginCommandsPass implements CompilerPassInterface {
    public function process(ContainerBuilder $container)
    {
        foreach ($container->getParameter('plugin.collections') as $id => $pluginAttribute) {
            $listCommand = new Command($id.':list');
            $listCommand->setDescription("List the available configurations for $id.");
            $listCommand->addOption(
                name: 'show-credentials',
                shortcut: 's',
                mode: InputOption::VALUE_NONE,
                description: "Print credentials to terminal."
            );
            $listCommand->setCode(function (InputInterface $input, OutputInterface $output) use ($container, $id) {
                return (new PluginCollectionCommand($container->get($id)))->list($input, $output);
            });

            $container->getDefinition(Application::class)->addMethodCall('add', [$listCommand]);

            $setupCommand = new Command($id.':add');
            $setupCommand->setDescription("Add a new configuration entry to $id.");
            $setupCommand->addArgument(
                name: $pluginAttribute->collectionKey, 
                mode: InputOption::VALUE_REQUIRED, 
                description: $pluginAttribute->getField($pluginAttribute->collectionKey)->description
            );
            foreach ($pluginAttribute->getFieldAttributes() as $field_name => $field) {
                $setupCommand->addOption($field_name, null, InputOption::VALUE_OPTIONAL, $field->description, $field->default);
            }
            $setupCommand->setCode(function (InputInterface $input, OutputInterface $output) use ($container, $id) {
                return (new PluginCollectionCommand($container->get($id)))->add($input, $output);
            });

            $container->getDefinition(Application::class)->addMethodCall('add', [$setupCommand]);


            $deleteCommand = new Command($id.':delete');
            $deleteCommand->setDescription("Remove a configuration entry from $id.");
            $deleteCommand->addArgument(
                name: $pluginAttribute->collectionKey, 
                mode: InputOption::VALUE_REQUIRED, 
                description: $pluginAttribute->getField($pluginAttribute->collectionKey)->description
            );
            $deleteCommand->setCode(function (InputInterface $input, OutputInterface $output) use ($container, $id) {
                return (new PluginCollectionCommand($container->get($id)))->delete($input, $output);
            });

            $container->getDefinition(Application::class)->addMethodCall('add', [$deleteCommand]);
        }
    }
}