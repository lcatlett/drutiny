<?php

namespace DrutinyTests;

use Drutiny\AuditFactory;
use Drutiny\Policy;
use Drutiny\PolicyFactory;
use Drutiny\Target\TargetFactory;


class PolicyTest extends KernelTestCase {

  protected $target;

  protected function setUp(): void
  {
      parent::setUp();
      $this->target = $this->container->get(TargetFactory::class)->create('none:none');
  }

  public function testPass()
  {
    $policy = $this->container->get(PolicyFactory::class)->loadPolicyByName('Test:Pass');
    $audit = $this->container->get(AuditFactory::class)->get($policy, $this->target);
    $response = $audit->execute($policy);
    $this->assertTrue($response->isSuccessful());
  }

  public function testFail()
  {
    $policy = $this->container->get(PolicyFactory::class)->loadPolicyByName('Test:Fail');
    $audit = $this->container->get(AuditFactory::class)->get($policy, $this->target);
    $response = $audit->execute($policy);
    $this->assertFalse($response->isSuccessful());
  }

  public function testError()
  {
    $policy = $this->container->get(PolicyFactory::class)->loadPolicyByName('Test:Error');
    $audit = $this->container->get(AuditFactory::class)->get($policy, $this->target);
    $response = $audit->execute($policy);
    $this->assertFalse($response->isSuccessful());
    $this->assertTrue($response->hasError());
  }

  public function testWarning()
  {
    $policy = $this->container->get(PolicyFactory::class)->loadPolicyByName('Test:Warning');
    $audit = $this->container->get(AuditFactory::class)->get($policy, $this->target);
    $response = $audit->execute($policy);
    $this->assertTrue($response->isSuccessful());
    $this->assertTrue($response->hasWarning());
  }

  public function testNotApplicable()
  {
    $policy = $this->container->get('policy.factory')->loadPolicyByName('Test:NA');
    $audit = $this->container->get(AuditFactory::class)->get($policy, $this->target);
    $response = $audit->execute($policy);
    $this->assertFalse($response->isSuccessful());
    $this->assertTrue($response->isNotApplicable());
  }

  public function testNotice()
  {
    $policy = $this->container->get(PolicyFactory::class)->loadPolicyByName('Test:Notice');
    $audit = $this->container->get(AuditFactory::class)->get($policy, $this->target);
    $response = $audit->execute($policy);
    $this->assertTrue($response->isSuccessful());
    $this->assertTrue($response->isNotice());
  }

  public function testSerializable()
  {
      $policy = $this->container->get(PolicyFactory::class)->loadPolicyByName('Test:Notice');
      $serial = serialize($policy);
      $this->assertIsString($serial, "Policy can be serialized");
      $this->assertInstanceOf(Policy::class, unserialize($serial), "Policy can be unserialized");
  }

  public function testBuildParameters()
  {
    $policy = $this->container->get(PolicyFactory::class)->loadPolicyByName('Test:Pass')->with(
      build_parameters: [
        'foo' => "'site.env'|split('.')|last"
      ]
    );
    $this->assertTrue($policy->build_parameters->has('foo'));
    $audit = $this->container->get(AuditFactory::class)->get($policy, $this->target);
    $response = $audit->execute($policy);
    $this->assertEquals('env', $response->tokens['foo'], "build parameter was correctly evaluated and converted into a token.");
  }
}
