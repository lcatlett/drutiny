<?php

namespace Drutiny\Profile;

use Drutiny\Attribute\Description;
use Drutiny\Policy;
use Drutiny\Policy\Severity;
use Drutiny\PolicyFactory;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[Autoconfigure(autowire:false)]
class PolicyDefinition {
    #[Description('The parameter overrides to use for the policy in this profile.')]
    public readonly ParameterBagInterface $parameters;

    #[Description('The severity override to use for the policy in this profile.')]
    public readonly Severity $severity;
    
    public function __construct(
        #[Description('A list of policies that must pass for this profile to be applicable against a given target.')]
        public readonly string $name,
        array $parameters = [],
        #[Description('Weighting to influence policy ordering in the profile.')]
        public readonly int $weight = 0,
        string $severity = 'normal',
        // Internal policy reference can be used to load a policy instead of using the PolicyFactory.
        protected Policy|null $policy = null
    )
    {
        $this->parameters = new FrozenParameterBag($parameters);
        $this->severity = Severity::from($severity);
    }

    public function sort(PolicyDefinition $definition) {
        if ($definition->weight == $this->weight) {
            $alphasort = [$definition->name, $this->name];
            sort($alphasort);
            return reset($alphasort) == $this->name ? 1 : -1;
        }
        return $definition->weight > $this->weight ? -1 : 1;
    }

    /**
     * Get the policy for the profile.
     */
    public function getPolicy(PolicyFactory $factory):Policy
    {
        if (isset($this->policy)) {
            return $this->policy;
        }
        $policy = $factory->loadPolicyByName($this->name)->with(
            severity: $this->severity->value,
            weight: $this->weight,
        );

        if (count($this->parameters->all())) {
            $policy = $policy->with(['parameters' => $this->parameters->all()]);
        }
        return $policy;
    }

    public function export():array
    {
        $properties = get_object_vars($this);
        $properties['parameters'] = $properties['parameters']->all();
        $properties['severity'] = $properties['severity']->value;
        return array_filter($properties);
    }
}