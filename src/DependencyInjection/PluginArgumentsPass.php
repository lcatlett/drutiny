<?php

namespace Drutiny\DependencyInjection;

use Drutiny\Attribute\Plugin;
use Drutiny\Attribute\UsePlugin;
use Drutiny\Config\Config;
use Drutiny\Plugin\PluginCollection;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Load plugins for classes from PHP attributes on the class.
 */
class PluginArgumentsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container) {
        $registry = [];
        $useServices = [];
        $pluginCollections = [];
        foreach ($container->getDefinitions() as $id => $definition) {
            if (strpos($id, '.abstract.instanceof.') !== false) {
                continue;
            }
            if (!$class = $definition->getClass()) {
                continue;
            }
            if (!class_exists($class, false)) {
                continue;
            }
            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes(Plugin::class);
            $useAttributes = $reflection->getAttributes(UsePlugin::class);
            if (!empty($useAttributes)) {
                $useServices[$id] = $useAttributes;
            }
            if (empty($attributes)) {
               continue;
            }
            $pluginAttribute = $attributes[0]->newInstance();
            $pluginAttribute->buildFieldAttributes($reflection);

            $pluginCollections[$pluginAttribute->name] = $pluginAttribute->collectionKey === null ? null : $pluginAttribute;

            // Create a config service for the namespace.
            $configDefinition = new Definition(Config::class);
            $configDefinition->setFactory([new Reference('config'), 'load']);
            $configDefinition->setArgument('$namespace', $pluginAttribute->name);
            $container->setDefinition($configServiceId = 'config.'.$pluginAttribute->name, $configDefinition);

            // Create a credential service for the namespace.
            $credDefinition = new Definition(Config::class);
            $credDefinition->setFactory([new Reference('credentials'), 'load']);
            $credDefinition->setArgument('$namespace', $pluginAttribute->name);
            $container->setDefinition($credentialServiceId = 'credentials.'.$pluginAttribute->name, $credDefinition);
            
            // Create a new service matching the plugin requirements.
            $serviceDefinition = $pluginAttribute->collectionKey === null ? clone $container->getDefinition($pluginAttribute->class) : clone $container->getDefinition(PluginCollection::class);
            $serviceDefinition->setArgument('$pluginConfig', new Reference($configServiceId));
            $serviceDefinition->setArgument('$pluginCredentials', new Reference($credentialServiceId));
            $serviceDefinition->setArgument('$attribute', $pluginAttribute);
            $container->setDefinition($pluginAttribute->name, $serviceDefinition);

            // Inject the new service as the passed in argument for the consuming service.
            $definition->setArgument($pluginAttribute->as, new Reference($pluginAttribute->name));
            $registry[$pluginAttribute->name] = $pluginAttribute->name;
        }
        $container->setParameter('plugin.registry', $registry);
        $container->setParameter('plugin.collections', array_filter($pluginCollections));

        // UsePlugin attribute provides a named plugin to a service defined in another service.
        foreach ($useAttributes as $id => $attributes) {
            $definition = $container->getDefinition($id);
            foreach ($attributes as $usePlugin) {
                $definition->setArgument($usePlugin->as, new Reference($usePlugin->name));
            }
        }
    }
}