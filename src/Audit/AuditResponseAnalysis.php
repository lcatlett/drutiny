<?php

namespace Drutiny\Audit;

use Drutiny\AuditFactory;
use Drutiny\PolicyFactory;
use Drutiny\Sandbox\Sandbox;

class AuditResponseAnalysis extends AbstractAnalysis {

    protected PolicyFactory $policyFactory;
    protected AuditFactory $auditFactory;

    public function configure():void {
        parent::configure();
        $this->addParameter(
            'policy',
            static::PARAMETER_REQUIRED,
            'The name of the policy to evaluate'
        );
        $this->policyFactory = $this->container->get(PolicyFactory::class);
        $this->auditFactory = $this->container->get(AuditFactory::class);
    }

    protected function gather(Sandbox $sandbox) {
        $name = $this->getParameter('policy');
        $policy = $this->policyFactory->loadPolicyByName($name);
        $audit = $this->auditFactory->get($policy, $this->target);
        $response = $audit->execute($policy);
        $this->set('response', $response);
    }
}