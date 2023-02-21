<?php

namespace DrutinyTests;

use Drutiny\Audit\TwigEvaluator;
use Drutiny\Kernel;
use Drutiny\ProfileFactory;
use Drutiny\Target\Service\DrushService;
use Drutiny\Target\Service\RemoteService;
use Drutiny\Target\TargetFactory;
use Drutiny\Target\TargetInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;


abstract class KernelTestCase extends TestCase {

  protected $application;
  protected $output;
  protected $container;
  protected $profile;

  protected function setUp(): void
  {
      global $kernel;
      $kernel = new Kernel('phpunit', 'x.y.z-dev');
      $kernel->addServicePath(str_replace(realpath($kernel->getProjectDir()).'/', '', dirname(dirname(__FILE__))));

      $this->application = $kernel->getApplication();
      $this->application->setAutoExit(FALSE);
      $this->container = $kernel->getContainer();
      $this->output = $this->container->get(OutputInterface::class);
      $this->profile = $this->container->get(ProfileFactory::class)->loadProfileByName('empty');
  }

  protected function loadMockTarget($type = 'null', ...$exec_responses):TargetInterface {
    // Dependency factory loads the target from the twigEvaluator.
    $twigEvaluator = $this->container->get(TwigEvaluator::class);
    $targetFactory = $this->container->get(TargetFactory::class);
    $target = $targetFactory->mock($type);
    $target->setUri('https://example.com/');

    // Set mock drush call.
    $exec = $this->getMockBuilder(RemoteService::class)
        ->onlyMethods(['run'])
        ->setConstructorArgs([$target->getService('local')])
        ->getMock();
    $exec
        ->method('run')
        ->willReturn(...$exec_responses);

    $target->setProperty('service.drush', new DrushService($exec));
    $twigEvaluator->setContext('target', $target);
    return $target;
}
}
