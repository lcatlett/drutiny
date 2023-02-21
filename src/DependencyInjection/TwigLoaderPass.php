<?php

namespace Drutiny\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class TwigLoaderPass implements CompilerPassInterface
{
    /**
     * You can modify the container here before it is dumped to PHP code.
     */
    public function process(ContainerBuilder $container)
    {
        $definition = $container->getDefinition('twig.loader');
        foreach ($container->findTaggedServiceIds('twig.loader', true) as $id => $events) {
          $definition->addMethodCall('addLoader', [$container->get($id)]);
        }
    }
}
