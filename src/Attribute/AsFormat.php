<?php

namespace Drutiny\Attribute;

use Attribute;

#[Attribute]
class AsFormat implements KeyableAttributeInterface {
    public function __construct(
        public readonly string $name, 
        public readonly string|false $extension = false
    ) {} 

    public function getKey():string
    {
        return $this->name;
    }
}