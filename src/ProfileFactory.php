<?php

namespace Drutiny;

use Drutiny\ProfileSource\ProfileSourceInterface;
use Drutiny\LanguageManager;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class ProfileFactory
{

    use ContainerAwareTrait;

    protected CacheInterface $cache;
    protected LanguageManager $languageManager;
    protected ProgressBar $progress;

    public function __construct(ContainerInterface $container, CacheInterface $cache, LanguageManager $languageManager, ProgressBar $progress)
    {
        $this->setContainer($container);
        $this->cache = $cache;
        $this->languageManager = $languageManager;
        $this->progress = $progress;
    }

    /**
     * Create a profile from an array of values.
     */
     public function create(array $values):Profile
     {
       $profile = new Profile();
       foreach ($values as $key => $value) {
         if ($key == 'include') {
           $includes = is_array($value) ? $value : [$value];
           $profiles = [];
           foreach ($includes as $include) {
             try {
               $profiles[] = $this->loadProfileByName($include);
             }
             catch (\Exception $e) {
               $this->container->get('logger')->error("{$values['title']} requires $include but is not present: " . $e->getMessage());
             }
           }
           $value = $profiles;
         }
         $profile->{$key} = $value;
       }
       try {
         return $profile->build();
       }
       catch (InvalidConfigurationException $e) {
        $this->container->get('logger')->error("Failed to build profile '{$values['name']}'", $values);
        throw $e;
       }
     }

    /**
     * Load policy by name.
     *
     * @param $name string
     */
    public function loadProfileByName($name):Profile
    {
        if ($name instanceof Profile) {
          return $name;
        }

        $list = $this->getProfileList();

        if (!isset($list[$name])) {
            throw new \Exception("No such profile found: $name.");
        }
        $definition = $list[$name];
        return $this->getSource($definition['source'])->load($definition);
    }

  /**
   * Acquire a list of available policies.
   *
   * @return array of policy information arrays.
   */
    public function getProfileList():array
    {
        $lang_code = $this->languageManager->getCurrentLanguage();
        $list = $this->cache->get('profile.list'.$lang_code, function (ItemInterface $item) {
          // $item->expiresAfter(0);
            $list = [];
            $this->progress->setMaxSteps($this->progress->getMaxSteps() + count($this->getSources()));
            foreach ($this->getSources() as $source) {
                foreach ($source->getList($this->languageManager) as $name => $item) {
                    $item['source'] = $source->getName();
                    $list[$name] = $item;
                }
                $this->progress->advance();
            }
            return $list;
        });
        $allow_list = $this->container->hasParameter('profile.allow_list') ? $this->container->getParameter('profile.allow_list') : [];
        return array_filter($list, fn($p) => empty($allow_list) || in_array($p, $allow_list), ARRAY_FILTER_USE_KEY);
    }

  /**
   * Load the sources that provide policies.
   *
   * @return array of PolicySourceInterface objects.
   */
    public function getSources():array
    {
        $sources = [];
        foreach ($this->container->findTaggedServiceIds('profile.source') as $id => $info) {
            $sources[$id] = $this->container->get($id);
        }

      // If multiple sources provide the same policy by name, then the policy from
      // the first source in the list will by used.
        uasort($sources, function ($a, $b) {
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
    public function getSource($name):ProfileSourceInterface
    {
        foreach ($this->getSources() as $class => $source) {
            if ($source->getName() == $name) {
                return $source;
            }
        }
        throw new \Exception("No such source found: $name.");
    }
}
