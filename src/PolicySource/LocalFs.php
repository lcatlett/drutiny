<?php

namespace Drutiny\PolicySource;

use Drutiny\Attribute\AsSource;
use Drutiny\LanguageManager;
use Drutiny\Settings;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Finder\Finder;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSource(name: 'localfs', weight: -10)]
#[Autoconfigure(tags: ['policy.source'])]
class LocalFs extends AbstractPolicySource
{
    public function __construct(
      protected Finder $finder, 
      protected Settings $settings,
      CacheInterface $cache,
      AsSource $source
    )
    {
        parent::__construct(cache: $cache, source: $source);

        // Ensure the policy directory is available.
        $fs = (array) $settings->get('policy.library.fs');
        $fs[] = DRUTINY_LIB;

        $fs = array_filter($fs, fn($p) => is_dir($p) || mkdir($p, 0744, true));
        $this->finder->files()->in($fs)->name('*.policy.yml');
    }

    /**
     * {@inheritdoc}
     */
    protected function doGetList(LanguageManager $languageManager):array
    {
        $list = [];
        foreach ($this->finder as $file) {
            $policy = Yaml::parse($file->getContents());
            $policy['uuid'] = md5($file->getPathname());
            $policy['language'] = $policy['language'] ?? $languageManager->getDefaultLanguage();

            if ($policy['language'] != $languageManager->getCurrentLanguage()) {
                continue;
            }
            $list[$policy['name']] = $policy;
        }
        return $list;
    }
}
