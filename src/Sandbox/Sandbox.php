<?php

namespace Drutiny\Sandbox;

use DateTime;
use Drutiny\Audit;

/**
 * Run check in an isolated environment.
 */
class Sandbox
{
    use ParameterTrait;
    use ReportingPeriodTrait;
    use Drutiny2xBackwardCompatibilityTrait;

    protected $target;
    protected $audit;

    public function __construct(Audit $audit)
    {
        $this->audit = $audit;
        $this->setReportingPeriod($audit->reportingPeriodStart ??  new DateTime('-24 hours'), $audit->reportingPeriodEnd ?? new DateTime());
    }
}
