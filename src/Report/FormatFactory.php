<?php

namespace Drutiny\Report;

use Drutiny\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;

class FormatFactory
{

    public function __construct(
        protected ContainerInterface $container, 
        protected Settings $settings)
    {
    }

    public function create(string $format, array $options = [])
    {
        // Registry built by compiler pass. See Drutiny\Kernel.
        $registry = $this->settings->get('format.registry');
        if (!isset($registry[$format])) {
            throw new \InvalidArgumentException("Reporting format '$format' is not supported.");
        }
        $formatter = $this->container->get($registry[$format]);
        $formatter->setOptions($options);
        return $formatter;
    }
}
