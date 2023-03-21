<?php

namespace Drutiny\ProfileSource;

use Drutiny\Attribute\AsSource;
use Drutiny\Helper\TextCleaner;
use Drutiny\LanguageManager;
use Drutiny\Profile;
use Drutiny\ProfileFactory;
use Symfony\Contracts\Cache\CacheInterface;

abstract class AbstractProfileSource implements ProfileSourceInterface
{
    public function __construct(protected AsSource $source, protected CacheInterface $cache, protected ProfileFactory $profileFactory)
    {
    }

    final public function load(array $definition): Profile
    {
        $key = hash('md5', $this->source->name.json_encode($definition));
        return $this->cache->get($key, fn() => $this->doLoad($definition));
    }

    protected function doLoad(array $definition): Profile
    {
        $definition['source'] = $this->source->name;
        return $this->profileFactory->create($definition);
    }

    final public function getList(LanguageManager $languageManager): array
    {
        $key = TextCleaner::machineValue($this->source->name.'.profile.list.'.$languageManager->getCurrentLanguage());
        return $this->cache->get($key, fn() => $this->doGetList($languageManager));
    }

    final public function refresh(LanguageManager $languageManager): array
    {
        $key = TextCleaner::machineValue($this->source->name.'.profile.list.'.$languageManager->getCurrentLanguage());
        return $this->getList($languageManager);
    }

    abstract protected function doGetList(LanguageManager $languageManager): array;
}
