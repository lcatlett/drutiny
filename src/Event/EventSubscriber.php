<?php

namespace Drutiny\Event;

use Drutiny\Event\RuntimeDependencyCheckEvent;
use Drutiny\Entity\RuntimeDependency;
use Composer\Semver\Comparator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EventSubscriber implements EventSubscriberInterface
{
    /**
     *  Implements \Symfony\Component\EventDispatcher\EventSubscriberInterface::getSubscribedEvents().
     *
     * @return array of events and callbacks.
     */
    public static function getSubscribedEvents()
    {
        return [
      // Status checks.
      'status' => 'checkKernelStatus',
    ];
    }

    public function checkKernelStatus(RuntimeDependencyCheckEvent $event)
    {
        $event->addDependency(
            (new RuntimeDependency('PHP Version'))
        ->setValue(phpversion())
        ->setDetails('Drutiny requires PHP 7.4 or later')
        ->setStatus(Comparator::greaterThanOrEqualTo(phpversion(), '7.4'))
        )->addDependency(
            (new RuntimeDependency('PHP memory limit'))
        ->setValue(ini_get('memory_limit'))
        ->setDetails('Drutiny recommends no memory limit (-1)')
        ->setStatus(ini_get('memory_limit') < 0 || ini_get('memory_limit') > 256)
        );
    }
}
