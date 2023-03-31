<?php

namespace Drutiny\Console\Command;

use Drutiny\Settings;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 *
 */
class CacheClearCommand extends Command
{
    public function __construct(
      protected ContainerInterface $container,
      protected Settings $settings,
      protected CacheInterface $cache)
    {
      parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
        ->setName('cache:clear')
        ->setDescription('Clear the Drutiny cache')
        ->addOption(
          'cid',
          null,
          InputOption::VALUE_OPTIONAL,
          'The cache ID to purge from cache.'
        )
        ->addOption(
          'twig-only',
          't',
          InputOption::VALUE_NONE,
          'Purge the '
        )
        ->addOption(
          'include-source-cache',
          's',
          InputOption::VALUE_NONE,
          'Clear policy and profile source caches also.'
        )
      ;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $registry = $this->settings->get('cache.registry');
        $registry = $input->getOption('include-source-cache') ? array_merge($registry, $this->settings->get('source.cache.registry')) : $registry;
        $registry = $input->getOption('twig-only') ? [] : $registry;

        global $kernel;

        register_shutdown_function(function () use ($kernel) {
          $kernel->refresh();
        });

        $cid = $input->getOption('cid');

        foreach ($registry as $id) {
          $service = $this->container->get($id);
          empty($cid) ? $service->clear() : $service->delete($cid);
          $io->success("Cleared '$id' cache.");
        } 

        $dir = $this->container->getParameter('twig.cache');

        if (!file_exists($dir)) {
          $io->info('Cache is already cleared: ' . $dir);
          return 0;
        }
        if (!is_writable($dir)) {
          $io->error(sprintf('Cannot clear cache: %s is not writable.', $dir));
          return 0;
        }
        exec(sprintf('rm -rf %s', $dir), $output, $status);
        if ($status === 0) {
          $io->success('Cache is cleared: ' . $dir);
          return 0;
        }
        $io->error(sprintf('Cannot clear cache from %s. An error occured.', $dir));
        return 0;
    }
}
