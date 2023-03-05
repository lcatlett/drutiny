<?php

namespace DrutinyTests;

use Drutiny\Audit\TwigEvaluator;
use Drutiny\LocalCommand;
use Drutiny\ProfileFactory;
use Drutiny\Settings;
use Drutiny\Target\TargetFactory;
use Drutiny\Target\TargetInterface;
use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\CacheInterface;

abstract class KernelTestCase extends TestCase {

    protected $application;
    protected $output;
    protected $container;
    protected $profile;

    protected function setUp(): void
    {
        global $kernel;
        $kernel = new Kernel('phpunit', 'x.y.z-dev');
        // $kernel->addServicePath(str_replace(realpath($kernel->getProjectDir()).'/', '', dirname(__DIR__)));
        $builder = $this->getMockBuilder(LocalCommand::class);

        // Mock the local command.
        $kernel->addCompilerPass(new class($builder) implements CompilerPassInterface {
            public function __construct (protected MockBuilder $builder) {}
            public function process(ContainerBuilder $container)
            {
                $container->set(LocalCommand::class, $this->builder
                            ->onlyMethods(['run'])
                            ->setConstructorArgs([
                                $container->get(CacheInterface::class),
                                $container->get(LoggerInterface::class),
                                $container->get(Settings::class),
                            ])
                            ->getMock());
            }
        }, PassConfig::TYPE_OPTIMIZE);

        $this->application = $kernel->getApplication();
        $this->application->setAutoExit(FALSE);
        $this->container = $kernel->getContainer();
        $this->output = $this->container->get(OutputInterface::class);

        $this->assertInstanceOf(BufferedOutput::class, $this->output);

        $this->profile = $this->container->get(ProfileFactory::class)->loadProfileByName('empty');
    }

    protected function loadMockTarget($type = 'none', ...$exec_responses):TargetInterface {
        // Dependency factory loads the target from the twigEvaluator.
        $twigEvaluator = $this->container->get(TwigEvaluator::class);
        $targetFactory = $this->container->get(TargetFactory::class);
        $target = $targetFactory->mock($type);
        $target->setUri('https://example.com/');

        if (!empty($exec_responses)) {
            $this->container->get(LocalCommand::class)
                ->expects($this->exactly(count($exec_responses)))
                ->method('run')
                ->willReturn(...$exec_responses);
        }
        
        $twigEvaluator->setContext('target', $target);
        return $target;
    }

    protected function getFixture($name) {
        $filename = dirname(__DIR__) . '/fixtures/' . $name .'.yml';
        if (!file_exists($filename)) return null;
        return Yaml::parseFile($filename);
    }
}
