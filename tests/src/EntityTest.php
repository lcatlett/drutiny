<?php

namespace DrutinyTests;

use Drutiny\AuditResponse\AuditResponse;
use Drutiny\Audit;
use Drutiny\Console\Application;
use Drutiny\Kernel;
use Drutiny\Policy;
use Drutiny\PolicyFactory;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Target\TargetFactory;
use PHPUnit\Framework\TestCase;

class EntityTest extends KernelTestCase {

  protected $target;

  public function testPolicyObjectUsage()
  {
    $policy = $this->container->get(PolicyFactory::class)->loadPolicyByName('Test:Pass');
    $this->assertEquals($policy->name, 'Test:Pass');

    // Testing dynamic assignment to policy properties.
    $policy->name = 'Test:Test';
    $this->assertEquals($policy->name, 'Test:Test');

    // Testing dynamic assignment of parameters.
    $policy->addParameters([
      'foo' => 'bar',
      'baz' => 'gat'
    ]);

    $this->assertEquals($policy->getParameter('foo'), 'bar');
    $this->assertEquals($policy->parameters['baz'], 'gat');

    // Confirm the export can be imported verbatim.
    $policy2 = new Policy();
    $policy2->setProperties($policy->export());

    $this->assertEquals($policy->title, $policy2->title);
  }

  public function testTargetObjectUsage()
  {
      $target = $this->container->get(TargetFactory::class)->create('none:none');
      $target->setUri('bar');
      $this->assertEquals($target->getProperty('uri'), 'bar');
  }

  public function testAuditResponse()
  {
    $policy = $this->container->get(PolicyFactory::class)->loadPolicyByName('Test:Pass');
    $response = new AuditResponse($policy);
    $response->set(Audit::SUCCESS, [
      'foo' => 'bar',
    ]);
    $this->assertArrayHasKey('foo', $response->getTokens());
    $this->assertContains('bar', $response->getTokens());
    $this->assertTrue($response->isSuccessful());

    $export = $response->export();
    $export['state'] = Audit::FAIL;
    $response->import($export);

    $this->assertFalse($response->isSuccessful());
    $this->assertFalse($response->hasError());

    $this->assertIsString($sleep_data = serialize($response));

    $sleep_data = serialize($response);

    $response = unserialize($sleep_data);
    
    $this->assertInstanceOf(AuditResponse::class, $response);

    $this->assertFalse($response->isSuccessful());
    $this->assertFalse($response->hasError());
  }
}
