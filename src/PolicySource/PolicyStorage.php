<?php

namespace Drutiny\PolicySource;

use Drutiny\Policy;
use Drutiny\Policy\UnavailablePolicyException;
use Drutiny\LanguageManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Psr\Log\LoggerInterface;

class PolicyStorage implements PolicySourceInterface
{
    protected PolicySourceInterface $source;
    protected LanguageManager $languageManager;
    protected LoggerInterface $logger;
    protected string $dir;

    public function __construct(PolicySourceInterface $source, ContainerInterface $container, LanguageManager $languageManager)
    {
      $this->source = $source;
      $this->dir = $container->getParameter('policy.library.fs');
      $this->languageManager = $languageManager;

      is_dir($this->getStoreLocation()) || mkdir($this->getStoreLocation(), 0744, true);

      $this->logger = $container->get('Psr\Log\LoggerInterface');
    }

    /**
     *{@inheritdoc}
     */
    public function getName()
    {
      return $this->source->getName();
    }

    public function getDriver()
    {
      return $this->source;
    }

    /**
     * Sync contents from source.
     */
    public function refresh()
    {
      file_exists($this->getListFilename()) && unlink($this->getListFilename());

      foreach ($this->getList($this->languageManager) as $definition) {
        $filename = $this->policyLocation($definition);
        file_exists($filename) && unlink($filename);
        try {
          yield $this->load($definition);
        }
        catch (UnavailablePolicyException $e) {
          $this->logger->warning($e->getMessage());
        }
      }
    }

    protected function getListFilename()
    {
      return $this->getStoreLocation() . '/list-' . $this->languageManager->getCurrentLanguage() . '.json';
    }

    protected function getStoreLocation()
    {
      return strtr('%dir/sources/%lang/%source', [
        '%dir' => $this->dir,
        '%lang' => $this->languageManager->getCurrentLanguage(),
        '%source' => str_replace('\\', '', get_class($this->source))
      ]);
    }

    private function policyLocation(array $definition)
    {
      return $this->getStoreLocation() . '/' . $definition['uuid'] . '.policy.json';
    }

    /**
     *{@inheritdoc}
     */
    public function getList(LanguageManager $languageManager)
    {
      if (file_exists($this->getListFilename())) {
        $this->logger->debug("Loading policies from " . $this->getListFilename());
        return json_decode(file_get_contents($this->getListFilename()), true);
      }
      $contents = $this->source->getList($this->languageManager);
      $this->logger->debug("Writting policies to " . $this->getListFilename());
      file_put_contents($this->getListFilename(), json_encode($contents));
      return $contents;
    }

    /**
     *{@inheritdoc}
     */
    public function load(array $definition)
    {

      if (file_exists($this->policyLocation($definition))) {
        $properties = json_decode(file_get_contents($this->policyLocation($definition)), true);
        $policy = new Policy();
        $policy->setProperties($properties);
        return $policy;
      }

      $policy = $this->source->load($definition);
      file_put_contents($this->policyLocation($definition), json_encode($policy->export()));
      return $policy;
    }

    /**
     *{@inheritdoc}
     */
    public function getWeight()
    {
      return $this->source->getWeight();
    }
}
