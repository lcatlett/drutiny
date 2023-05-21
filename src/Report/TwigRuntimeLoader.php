<?php

namespace Drutiny\Report;

use Psr\Container\ContainerInterface;
use Twig\Extra\Markdown\MarkdownRuntime;
use Twig\RuntimeLoader\RuntimeLoaderInterface;

class TwigRuntimeLoader implements RuntimeLoaderInterface {

    public function __construct(protected ContainerInterface $container) {}
    
    /**
     * {@inheritDoc}
     */
    public function load($class) {
        if (MarkdownRuntime::class === $class) {
            return new MarkdownRuntime(new DrutinyMarkdown());
        }
        // Attempt to load from the container.
        return $this->container->get($class);
    }
}

 ?>
