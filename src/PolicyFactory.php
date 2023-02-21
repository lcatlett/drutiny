<?php

namespace Drutiny;

use Drutiny\PolicySource\PolicySourceInterface;
use Drutiny\PolicySource\PolicyStorage;
use Drutiny\Policy\UnavailablePolicyException;
use Drutiny\Policy\UnknownPolicyException;
use Drutiny\LanguageManager;
use Drutiny\Plugin\PluginRequiredException;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Psr\Log\LoggerInterface;

class PolicyFactory
{
    use ContainerAwareTrait;

    protected LanguageManager $languageManager;
    protected ProgressBar $progress;
    protected LoggerInterface $logger;

    public function __construct(ContainerInterface $container, LoggerInterface $logger, LanguageManager $languageManager, ProgressBar $progress)
    {
        $this->setContainer($container);
        if (method_exists($logger, 'withName')) {
            $logger = $logger->withName('policy.factory');
        }
        $this->logger = $logger;
        $this->languageManager = $languageManager;
        $this->progress = $progress;
    }

    /**
     * Load policy by name.
     *
     * @param $name string
     */
    public function loadPolicyByName($name):Policy
    {
        $list = $this->getPolicyList();

        if (!isset($list[$name])) {
            $list = $this->getPolicyList(true);
            if (!isset($list[$name])) {
                throw new UnknownPolicyException("$name does not exist.");
            }
            throw new UnavailablePolicyException("$name requires {$list[$name]['class']} but is not available in this environment.");
        }
        $definition = $list[$name];
        unset($definition['sources']);

        try {
            $policy = $this->getSource($definition['source'])->load($definition);
            $policy->setProperty('source', $definition['source']);
            return $policy;
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning($e->getMessage());
            throw new UnavailablePolicyException("$name requires {$list[$name]['class']} but is not available in this environment.");
        }
    }

    /**
     * Acquire a list of available policies.
     *
     * @return array of policy information arrays.
     */
    public function getPolicyList($include_invalid = false)
    {
        static $policy_list, $available_list;

        if ($include_invalid && !empty($policy_list)) {
            return $policy_list;
        }

        if (!empty($available_list)) {
            return $available_list;
        }

        $policy_list = [];
        // Add steps to the progress bar.
        $this->progress->setMaxSteps($this->progress->getMaxSteps() + count($this->getSources()));
        foreach ($this->getSources() as $source) {
            try {
                $items = $source->getList($this->languageManager);
                $this->logger->notice($source->getName() . " has " . count($items) . " polices.");
                foreach ($items as $name => $item) {
                    $item['source'] = $source->getName();

                    if (isset($policy_list[$name])) {
                        $item['sources'] = $policy_list[$name]['sources'] ?? [];
                    }
                    $item['sources'][] = $item['source'];
                    $policy_list[$name] = $item;
                }
            } catch (\Exception $e) {
                $this->logger->error(strtr("Failed to load policies from source: @name: @error", [
                '@name' => $source->getName(),
                '@error' => $e->getMessage(),
                ]));
            }
            $this->progress->advance();
        }

        if ($include_invalid) {
            return $policy_list;
        }

        $available_list = array_filter($policy_list, function ($listedPolicy) {
            if (!class_exists($listedPolicy['class'])) {
                $this->logger->debug('Failed to find class:  ' . $listedPolicy['class']);
                return false;
            }
            return true;
        });
        return $available_list;
    }

    /**
     * Load the policies from a single source.
     */
    public function getSourcePolicyList(string $source): array
    {
        return $this->getSource($source)
        ->getList($this->languageManager);
    }

    /**
     * Load the sources that provide policies.
     *
     * @return array of PolicySourceInterface objects.
     */
    public function getSources()
    {
        static $sources;
        if (!empty($sources)) {
            return $sources;
        }

        $sources = [];
        foreach ($this->container->findTaggedServiceIds('policy.source') as $id => $info) {
            if (strpos($id, 'PolicyStorage') !== false) {
                continue;
            }
            try {
                $sources[] = new PolicyStorage($this->container->get($id), $this->container, $this->container->get('Drutiny\LanguageManager'));
            } catch (PluginRequiredException $e) {
                $this->logger->warning("Cannot load policy source: $id: " . $e->getMessage());
            }
        }

        // If multiple sources provide the same policy by name, then the policy from
        // the first source in the list will by used.
        usort($sources, function ($a, $b) {
            if ($a->getWeight() == $b->getWeight()) {
                return 0;
            }
            return $a->getWeight() > $b->getWeight() ? 1 : -1;
        });

        return $sources;
    }

    /**
     * Load a single source.
     */
    public function getSource($name): PolicySourceInterface
    {
        foreach ($this->getSources() as $source) {
            if ($source->getName() == $name) {
                return $source;
            }

            // Attempt lookup without formatting.
            $raw_name = preg_replace('/<[^>]+>/', '', $source->getName());
            if ($raw_name == $name) {
                return $source;
            }
        }
        throw new \Exception("No such source found: $name.");
    }
}
