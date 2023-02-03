<?php

namespace Drutiny\Entity;

use Drutiny\PolicyFactory;
use Symfony\Component\Config\Definition\Processor;
use Drutiny\Config\PolicyOverrideConfigurationTrait;

class PolicyOverride extends StrictEntity
{
    const ENTITY_NAME = 'policy_override';

    use PolicyOverrideConfigurationTrait;

    public function __construct(string $policy_name)
    {
      parent::__construct();
      $this->name = $policy_name;
      $this->weight = 0;
    }

    /**
     * Get the policy for the profile.
     */
    public function getPolicy(PolicyFactory $factory)
    {
        $policy = $factory->loadPolicyByName($this->name);

        if (isset($this->severity)) {
            $policy->setSeverity($this->severity);
        }

        foreach ($this->parameters ?? [] as $param => $value) {
            $policy->addParameter($param, $value);
        }

        if (isset($this->weight)) {
          $policy->setProperty('weight', $this->weight);
        }
        return $policy;
    }
}
