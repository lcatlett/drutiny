<?php

namespace Drutiny\Attribute;

use Attribute;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Attribute]
#[Autoconfigure(autowire:false)]
class AsSource extends Name {
    public function __construct(
        public readonly string $name, 
        public readonly int $weight = 0
    ) {} 
}