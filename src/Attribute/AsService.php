<?php

namespace Drutiny\Attribute;

use Attribute;

#[Attribute]
class AsService implements KeyableAttributeInterface {
    public function __construct(
        public readonly string $name
    ) {} 

    public function getKey():string
    {
        return $this->name;
    }
}