<?php

namespace Drutiny\DependencyInjection;

use Drutiny\Console\Application;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\Reference;

class AddConsoleCommandPass implements CompilerPassInterface
{
    /**
     * You can modify the container here before it is dumped to PHP code.
     */
    public function process(ContainerBuilder $container)
    {
        $definition = $container->getDefinition(Application::class);
        foreach ($container->findTaggedServiceIds('command', true) as $id => $events) {
            $definition->addMethodCall('add', [
                new Reference($id)
            ]);

            if (!$reflection = new ReflectionClass($id)) {
                continue;
            }
            if (!$reflection->implementsInterface(ContainerAwareInterface::class)) {
                continue;
            }
            $commandDefinition = $container->getDefinition($id);
            $commandDefinition->addMethodCall('setContainer', [new Reference('service_container')]);
        }
    }
}
