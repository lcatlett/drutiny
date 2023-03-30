<?php

namespace Drutiny\DomainList;

use Drutiny\Attribute\Name;
use Drutiny\Target\TargetInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;

/**
 * Load domain lists from a yaml file.
 *
 * YAML file should use this schema:
 * domains:
 *   - mysite.com
 *   - example.com
 */
#[Name('yaml')]
class DomainListYamlFile extends AbstractDomainList
{
    /**
     * @return array list of domains.
     */
    public function getDomains(TargetInterface $target, array $options = []):array
    {
        $config = Yaml::parseFile($options['filepath']);
        return $config['domains'] ?? $config;
    }

    /**
     * @{inheritdoc}
     */
    public function getInputOptions(): array
    {
        return [
            new InputOption(
                name: 'filepath',
                description: 'Filepath to the YAML file containing the domains',
                mode: InputOption::VALUE_OPTIONAL
            )
        ];
    }
}
