<?php

namespace Drutiny;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class Settings {
    protected ParameterBagInterface $parameterBag;

    public function __construct(Container $container)
    {
        $this->parameterBag = $container->getParameterBag();
    }

    public function get($id)
    {
        if ($this->parameterBag->has($id)) {
            return $this->parameterBag->get($id);
        }
    }

    public function getAll():array
    {
        return $this->parameterBag->all();
    }

    public function has($id) {
        return $this->parameterBag->has($id);
    }
}