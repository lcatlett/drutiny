<?php

namespace Drutiny;

use Drutiny\AuditResponse\AuditResponse;
use Drutiny\Target\TargetInterface;

interface AssessmentInterface {
    public function getUuid(): string;
    public function setUri($uri = 'default'): Assessment;
    public function setType(string $type): Assessment;
    public function getType(): string;
    public function assessTarget(TargetInterface $target, array $policies, \DateTime $start = null, \DateTime $end = null): Assessment;
    public function setPolicyResult(AuditResponse $response);
    public function getSeverityCode(): int;
    public function isSuccessful();
    public function hasPolicyResult(string $name):bool;
    public function getPolicyResult(string $name):AuditResponse;
    public function getResults();
    public function getErrorCode();
    public function uri();
    public function getStatsByResult();
    public function getStatsBySeverity();
    public function getTarget(): TargetInterface;
}
