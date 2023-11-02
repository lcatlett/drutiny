<?php

namespace Drutiny\Console\Command;

use Drutiny\PolicyFactory;
use Drutiny\ProfileFactory;
use Drutiny\Target\TargetFactory;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Run a profile and generate a report.
 */
abstract class DrutinyBaseCommand extends Command
{
    /**
     * @deprecated
     */
    protected function getProgressBar(): ProgressBar
    {
        throw new RuntimeException("Please use dependency injection in the constructor method instead for :" . ProgressBar::class);
    }

    /**
     * Get container logger.
     * @deprecated
     */
    protected function getLogger(): LoggerInterface
    {
        throw new RuntimeException("Please use dependency injection in the constructor method instead for :" . LoggerInterface::class);
    }

    /**
     * Get container policy factory.
     * @deprecated
     */
    protected function getPolicyFactory(): PolicyFactory
    {
        throw new RuntimeException("Please use dependency injection in the constructor method instead for :" . PolicyFactory::class);
    }

    /**
     * Get profile factory.
     * @deprecated
     */
    protected function getProfileFactory(): ProfileFactory
    {
        throw new RuntimeException("Please use dependency injection in the constructor method instead for :" . ProfileFactory::class);
    }

    /**
     * Get profile factory.
     * @deprecated
     */
    protected function getTargetFactory(): TargetFactory
    {
        throw new RuntimeException("Please use dependency injection in the constructor method instead for :" . TargetFactory::class);
    }

    /**
     * @deprecated
     */
    protected function dispatchEvent(string $event, array $args)
    {
        throw new RuntimeException("Please use dependency injection in the constructor method instead for :" . EventDispatcher::class);
    }
}
