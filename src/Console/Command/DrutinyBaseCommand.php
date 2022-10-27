<?php

namespace Drutiny\Console\Command;

use Drutiny\PolicyFactory;
use Drutiny\ProfileFactory;
use Drutiny\Target\TargetFactory;
use Drutiny\LanguageManager;
use Async\ForkManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Run a profile and generate a report.
 */
abstract class DrutinyBaseCommand extends Command
{
    /**
     * Shortcut to the service container.
     */
    protected function getContainer(): ContainerInterface
    {
        return $this->getApplication()
          ->getKernel()
          ->getContainer();
    }

    protected function getProgressBar(): ProgressBar
    {
        return $this->getContainer()->get('progress_bar');
    }

    /**
     * Get container logger.
     */
    protected function getLogger(): LoggerInterface
    {
        return $this->getContainer()->get('logger');
    }

    /**
     * Get container policy factory.
     */
    protected function getPolicyFactory(): PolicyFactory
    {
        return $this->getContainer()->get('policy.factory');
    }

    /**
     * Get profile factory.
     */
    protected function getProfileFactory(): ProfileFactory
    {
        return $this->getContainer()->get('profile.factory');
    }

    /**
     * Get profile factory.
     */
    protected function getTargetFactory(): TargetFactory
    {
        return $this->getContainer()->get('target.factory');
    }

    /**
     * Get utility for managing forks.
     */
    protected function getForkManager(): ForkManager
    {
        return $this->getContainer()->get('async');
    }

    protected function dispatchEvent(string $event, array $args)
    {
        return $this
        ->getApplication()
        ->getKernel()->dispatchEvent($event, $args);
    }
}
