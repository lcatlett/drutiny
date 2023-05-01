<?php

namespace Drutiny;

use Drutiny\Attribute\ArrayType;
use Drutiny\Attribute\Description;
use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Policy\Dependency;
use Drutiny\Entity\ExportableInterface;
use Drutiny\Helper\MergeUtility;
use Drutiny\Policy\Chart;
use Drutiny\Policy\PolicyType;
use Drutiny\Policy\Severity;
use Drutiny\Policy\Tag;
use Drutiny\Profile\PolicyDefinition;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(autowire: false)]
class Policy implements ExportableInterface
{
    #[Description('What type of policy this is. Audit types return a pass/fail result while data types return only data.')]
    public readonly PolicyType $type;
    
    #[Description('A set of tags to categorize a policy.')]
    #[ArrayType('indexed', Tag::class)]
    public readonly array $tags;

    #[Description('What severity level the policy is rated at.')]
    public readonly Severity $severity;

    #[Description('Parameters are values that maybe used to configure an audit for use with the Policy.')]
    public readonly ParameterBagInterface $parameters;

    #[Description('Create parameters to pass to the audit before it is executed. Target object is available.')]
    public readonly ParameterBagInterface $build_parameters;

    #[Description('A list of executable dependencies to require before auditing the policy against a target.')]
    #[ArrayType('indexed', Dependency::class)]
    public readonly array $depends;

    #[Description('Configuration for any charts used in the policy messaging.')]
    #[ArrayType('indexed', Chart::class)]
    public readonly array $chart;

    public function __construct(
      #[Description('The human readable name of the policy.')]
      public readonly string $title,

      #[Description('The machine-name of the policy.')]
      public readonly string $name,

      #[Description('A description why the policy is valuable.')]
      public readonly string $description,

      #[Description('Unique identifier such as a URL.')]
      public readonly string $uuid,

      #[Description('Where the policy is sourced from.')]
      public readonly string $source,

      // Arrays and Enums are declared in the class and don't require Description attributes
      // in the constructor.
      string $type = 'audit',
      array $tags = [],
      string $severity = 'normal',
      array $parameters = [],
      array $build_parameters = [],
      array $depends = [],
      array $chart = [],

      #[Description('Weight of a policy to sort it amoung other policies.')]
      public readonly int $weight = 0,

      #[Description('A PHP Audit class to pass the policy to be assessed.')]
      public readonly string $class = AbstractAnalysis::class,

      #[Description('Language code')]
      public readonly string $language = 'en',

      #[Description('Content to communicate how to remediate a policy failure.')]
      public readonly string $remediation = '',

      #[Description('Content to communicate a policy failure.')]
      public readonly string $failure = '',

      #[Description('Content to communicate a policy success.')]
      public readonly string $success = '',

      #[Description('Content to communicate a policy warning (in a success).')]
      public readonly ?string $warning = '',

      #[Description('The URI this policy can be referenced and located by.')]
      public readonly ?string $uri = null,

      #[Description('Notes and commentary on policy configuration and prescribed usage.')]
      public readonly string $notes = '',
    )
    {
      $this->type = PolicyType::from($type);
      $this->severity = Severity::from($severity);
      $this->tags = array_map(fn(string $t) => new Tag($t), $tags);
      $this->parameters = new FrozenParameterBag($parameters);
      $this->build_parameters = new FrozenParameterBag($build_parameters);
      $this->depends = array_map(fn(string|array $d) => is_string($d) ? Dependency::fromString($d) : new Dependency(...$d), $depends);
      $this->chart = array_map(fn($c) => Chart::fromArray($c), $chart);
    }

    /**
     * Produce policy object variation with altered properties.
     */
    public function with(...$properties):self
    {
        $args = MergeUtility::arrayMerge($this->export(), $properties);
        return new static(...$args);
    }

    /**
     * Get a policy definition from the policy.
     */
    public function getDefinition():PolicyDefinition
    {
      return new PolicyDefinition(
        name: $this->name,
        parameters: $this->parameters->all(),
        weight: $this->weight,
        severity: $this->severity->value,
        policy: $this
      );
    }

    /**
     * {@inheritdoc}
     */
    public function export():array
    {
        $data = get_object_vars($this);
        $data['type'] = $data['type']->value;
        $data['severity'] = $data['severity']->value;
        $data['parameters'] = $data['parameters']->all();
        $data['build_parameters'] = $data['build_parameters']->all();
        $data['chart'] = array_map(fn($c) => get_object_vars($c), $data['chart']);
        $data['depends'] = array_map(fn($d) => $d->export(), $data['depends']);
        $data['tags'] = array_map(fn ($t) => $t->name, $this->tags);

        // Fix Yaml::dump bug where it doesn't correctly split \r\n to multiple
        // lines.
        foreach ($data as $key => $value) {
          if (is_string($value)) {
            $data[$key] = str_replace("\r\n", "\n", $value);
          }
        }

        return $data;
    }
}
