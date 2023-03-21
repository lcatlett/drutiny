<?php

namespace Drutiny\DependencyInjection;

use Drutiny\Attribute\AsSource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

class AddSourcesCachePass implements CompilerPassInterface
{
    /**
     * You can modify the container here before it is dumped to PHP code.
     */
    public function process(ContainerBuilder $container)
    {
        foreach ($container->findTaggedServiceIds('policy.source', true) as $id => $events) {
            $definition = $container->getDefinition($id);
            $definition->setArgument('$cache', new Reference('policy.store'));
            
            $reflection = $container->getReflectionClass($id);
            $source = $reflection->getAttributes(AsSource::class)[0]->newInstance();
            $definition->setArgument('$source', $source);
        }

        foreach ($container->findTaggedServiceIds('profile.source', true) as $id => $events) {
            $definition = $container->getDefinition($id);
            $definition->setArgument('$cache', new Reference('profile.store'));

            $reflection = $container->getReflectionClass($id);
            $source = $reflection->getAttributes(AsSource::class)[0]->newInstance();
            $definition->setArgument('$source', $source);
        }
    }
}
