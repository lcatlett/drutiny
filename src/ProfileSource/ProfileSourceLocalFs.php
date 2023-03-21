<?php

namespace Drutiny\ProfileSource;

use Drutiny\Attribute\AsSource;
use Drutiny\LanguageManager;
use Drutiny\Profile;
use Drutiny\ProfileFactory;
use Drutiny\Settings;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSource(name: 'localfs', weight: -10)]
#[Autoconfigure(tags: ['profile.source'])]
class ProfileSourceLocalFs extends AbstractProfileSource
{
    public function __construct(protected Finder $finder, Settings $settings, AsSource $source, CacheInterface $cache, ProfileFactory $profileFactory)
    {
        parent::__construct(source: $source, cache: $cache, profileFactory: $profileFactory);
        $this->finder->files()->in(DRUTINY_LIB)->name('*.profile.yml');

        try {
          $this->finder->in($settings->get('profile.library.fs'));
        }
        catch (DirectoryNotFoundException $e) {
          // Ignore not finding an existing config dir.
        }
    }

    /**
     * {@inheritdoc}
     */
    public function doGetList(LanguageManager $languageManager):array
    {
        $list = [];
        foreach ($this->finder as $file) {
            $filename = $file->getPathname();
            $name = str_replace('.profile.yml', '', pathinfo($filename, PATHINFO_BASENAME));
            $profile = Yaml::parse($file->getContents());
            $profile['language'] = $profile['language'] ?? $languageManager->getDefaultLanguage();

            if ($languageManager->getCurrentLanguage() != $profile['language']) {
              continue;
            }

            $profile['filepath'] = $filename;
            $profile['name'] = $name;
            unset($profile['format']);
            $list[$name] = $profile;
        }
        return $list;
    }

    /**
     * {@inheritdoc}
     */
    protected function doLoad(array $definition):Profile
    {
      $filepath = $definition['filepath'];

      $info = Yaml::parse(file_get_contents($filepath));
      $info['name'] = str_replace('.profile.yml', '', pathinfo($filepath, PATHINFO_BASENAME));
      $info['uuid'] = $filepath;

      return parent::doLoad($info);
    }
}
