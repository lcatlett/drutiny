<?php

namespace Drutiny\Target;

use Drutiny\Audit\TwigEvaluator;
use Drutiny\Settings;
use Drutiny\Target\Exception\InvalidTargetException;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TargetFactory
{
    protected array $targetMap;

    public function __construct(
      protected ContainerInterface $container,
      protected TwigEvaluator $twigEvaluator,
      Settings $settings
    )
    {
      $this->targetMap = $settings->get('target.registry');
    }

    /**
     * Create a new TargetInterface instance from a target reference.
     */
    public function create(string $target_reference, ?string $uri = null):TargetInterface
    {
      // By default, assume a target is using drush.
        $target_name = 'drush';
        $target_data = $target_reference;

      // If a colon is used, then an alternate target maybe used.
        if (strpos($target_reference, ':') !== false) {
            list($target_name, $target_data) = explode(':', $target_reference, 2);
        }

        $target = $this->container->get($this->targetMap[$target_name]);
        $target->setTargetName($target_reference);
        $target->load($target_data, $uri);

        // This makes the target inherintly accessible by the twigEvaluator 
        // in other services.
        $this->twigEvaluator->setContext('target', $target);
        return $target;
    }

    public function mock($type):TargetInterface
    {
      $this->targetMap[$type] ?? throw new InvalidTargetException("No such target type '$type'.");
      return $this->container->get($this->targetMap[$type]);
    }

    /**
     * Display a list of target types and classes.
     */
    public function getTypes():array
    {
      return $this->targetMap;
    }

    /**
     * Test if a target is of a given type by its source name.
     */
    public function typeOf(TargetInterface $target, string $name = TargetInterface::class): bool
    {
      return $target instanceof ($this->targetMap[$name] ?? $name);
    }
}
