<?php

namespace Drutiny\DomainList;

use Drutiny\Target\TargetInterface;

interface DomainListInterface
{

    /**
     * @return array list of domains.
     */
    public function getDomains(TargetInterface $target, array $options = []):array;
    
    /**
     * @deprecated
     */
    public function getOptionsDefinitions();
    
    /**
     * Get an array of Symfony\Component\Console\Input\InputOption objects.
     */
    public function getInputOptions():array;
}
