<?php

namespace Drutiny\Event;

use Drutiny\Entity\RuntimeDependency;
use Symfony\Contracts\EventDispatcher\Event;

class RuntimeDependencyCheckEvent extends Event
{
    protected array $dependencies = [];

    public function addDependency(RuntimeDependency $dependency): RuntimeDependencyCheckEvent
    {
        $this->dependencies[] = $dependency;
        return $this;
    }

    public function getRuntimeDependencies(): array
    {
        return $this->dependencies;
    }
}
