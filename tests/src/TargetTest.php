<?php

namespace DrutinyTests;

use Drutiny\Target\TargetInterface;
use Drutiny\Target\TargetPropertyException;
use Drutiny\Entity\EventDispatchedDataBag;
use Drutiny\Target\Service\ExecutionService;
use Drutiny\Target\Service\LocalService;
use Prophecy\Prophet;
use PHPUnit\Framework\TestCase;
use DrutinyTests\Prophecies\LocalServiceDrushStub;
use DrutinyTests\Prophecies\LocalServiceDdevStub;
use DrutinyTests\Prophecies\LocalServiceLandoStub;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\Exception\InvalidPropertyPathException;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;

class TargetTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setup();
        $this->prophet = new \Prophecy\Prophet();
    }

    protected function tearDown(): void
    {
        $this->prophet->checkPredictions();
    }

    public function testProperties()
    {
        $target = new \Drutiny\Target\NullTarget(
            new ExecutionService(LocalServiceDrushStub::get($this->prophet)->reveal()),
            $this->container->get('logger'),
            $this->container->get('Drutiny\Entity\EventDispatchedDataBag')
        );
        $target->parse();

        $this->assertFalse($target->hasProperty('a.b.c.d'));
        $this->assertFalse($target->hasProperty('a.b.c'));
        $this->assertFalse($target->hasProperty('a.b'));
        $this->assertFalse($target->hasProperty('a'));

        try {
            $target->hasProperty('a.b.c.');
        } catch (InvalidPropertyPathException $e) {
        } finally {
            $this->assertInstanceOf(InvalidPropertyPathException::class, $e);
        }

        $target['a.b.c'] = 'foo';

        $this->assertIsString($target['a.b.c']);
        $this->assertInstanceOf(EventDispatchedDataBag::class, $target['a.b']);
        $this->assertInstanceOf(EventDispatchedDataBag::class, $target['a']);
        $this->assertIsString($target->a->b->c);
        $this->assertInstanceOf(EventDispatchedDataBag::class, $target->a);
        $this->assertInstanceOf(EventDispatchedDataBag::class, $target->a->b);

        try {
            $target['a.b'] = ['c' => 'bar'];
        } catch (TargetPropertyException $e) {
        } finally {
            $this->assertInstanceOf(TargetPropertyException::class, $e);
        }

        $target['a.b.d'] = ['e' => 'bar'];
        $this->assertIsString($target['a.b.d[e]']);
        $this->assertIsArray($target['a.b.d']);
        $this->assertIsArray($target->a->b->d);
        $this->assertIsString($target->a->b->d['e']);
        $this->assertInstanceOf(EventDispatchedDataBag::class, $target['a.b']);

        $target['h.i'] = 'hi';
        $this->assertIsString($target['h.i']);

        $target['h.o'] = 'ho';
        $this->assertIsString($target['h.o']);

        $this->assertNull($target['unset.value']);
    }

    protected function runStandardTests(TargetInterface $target, $uri = 'https://mysite.com/')
    {
        // Ensure the target can use dot syntax.
        $target['test.foo'] = 'bar';
        $this->assertEquals($target['test.foo'], 'bar', "Test using array reference syntax.");
        $this->assertEquals($target->test->foo, 'bar', "Test using property reference syntax.");
        $this->assertEquals($target->getProperty('test.foo'), 'bar', "Test using getProperty syntax.");

        $target['test.foo'] = 'bar:baz';
        $this->assertEquals($target['test.foo'], 'bar:baz', "Test using array reference syntax.");
        $this->assertEquals($target->test->foo, 'bar:baz', "Test using property reference syntax.");
        $this->assertEquals($target->getProperty('test.foo'), 'bar:baz', "Test using getProperty syntax.");
        $this->assertInstanceOf(EventDispatchedDataBag::class, $target['test']);
        $this->assertInstanceOf(EventDispatchedDataBag::class, $target->test);
        $this->assertInstanceOf(EventDispatchedDataBag::class, $target->getProperty('test'));

        $this->assertInstanceOf(TargetInterface::class, $target);
        $this->assertEquals($target->getUri(), $uri);

        $this->assertInstanceOf(ExecutionService::class, $target['service.exec']);
        $this->assertSame($target['service.exec'], $target->getService('exec'));

        // Existing DataBag can be overridden.
        $this->expectException(TargetPropertyException::class);
        $target['test'] = 'fault';
    }

    public function testDrushTarget()
    {
        $local = LocalServiceDrushStub::get($this->prophet)->reveal();

        // Load without service container so we can use our prophecy.
        $target = new \Drutiny\Target\DrushTarget(
            new ExecutionService($local),
            $this->container->get('logger'),
            $this->container->get('Drutiny\Entity\EventDispatchedDataBag')
        );
        $this->assertInstanceOf(\Drutiny\Target\DrushTarget::class, $target);
        $target->parse('@app.env', 'https://env.app.com');
        $this->runStandardTests($target, 'https://env.app.com');

        $this->assertEquals($target['drush.drupal-version'], '8.9.18');
        $this->assertEquals($target->getUri(), 'https://env.app.com');

        $target = new \Drutiny\Target\DrushTarget(
            new ExecutionService($local),
            $this->container->get('logger'),
            $this->container->get('Drutiny\Entity\EventDispatchedDataBag')
        );
        $target->parse('@app.env');
        $this->assertEquals($target->getUri(), 'dev1.app.com');
    }

    public function testDdevTarget()
    {
        $local = LocalServiceDdevStub::get($this->prophet)->reveal();

        // Load without service container so we can use our prophecy.
        $target = new \Drutiny\Target\DdevTarget(
            new ExecutionService($local),
            $this->container->get('logger'),
            $this->container->get('Drutiny\Entity\EventDispatchedDataBag')
        );
        $this->assertInstanceOf(\Drutiny\Target\DdevTarget::class, $target);
        $target->parse('ddev_app', 'https://env.app.com');

        $this->assertEquals($target['drush.drupal-version'], '8.9.18');
        $this->assertEquals($target->getUri(), 'https://env.app.com');
    }

    public function testLandoTarget()
    {
        $local = LocalServiceLandoStub::get($this->prophet)->reveal();

        // Load without service container so we can use our prophecy.
        $target = new \Drutiny\Target\LandoTarget(
            new ExecutionService($local),
            $this->container->get('logger'),
            $this->container->get('Drutiny\Entity\EventDispatchedDataBag')
        );
        $this->assertInstanceOf(\Drutiny\Target\LandoTarget::class, $target);
        $target->parse('appenv', 'https://env.app.com');

        $this->assertEquals($target['drush.drupal-version'], '8.9.18');
        $this->assertEquals($target->getUri(), 'https://env.app.com');
    }
}
