<?php

namespace Drutiny\Target;

use Drutiny\Attribute\AsTarget;

/**
 * Target for parsing Drush aliases.
 */
#[AsTarget(name: 'none')]
class NullTarget extends Target implements TargetInterface
{
    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return 'null';
    }

    /**
     * @inheritdoc
     * Implements Target::parse().
     */
    public function parse(string $data = '', ?string $uri = null): TargetInterface
    {
        $this->setUri($uri ?? 'http://example.com');
        return $this;
    }
}
