<?php

namespace Drutiny\Target;

/**
 * Target for parsing Drush aliases.
 */
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
