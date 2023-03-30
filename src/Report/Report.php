<?php

namespace Drutiny\Report;

use Drutiny\Attribute\ArrayType;
use Drutiny\AuditResponse\AuditResponse;
use Drutiny\Helper\MergeUtility;
use Drutiny\Policy\Severity;
use Drutiny\Profile;
use Drutiny\Sandbox\ReportingPeriodTrait;
use Drutiny\Target\TargetInterface;
use Exception;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(autowire: false)]
class Report {
    use ReportingPeriodTrait;

    public readonly string $uuid;
    #[ArrayType('keyed', AuditResponse::class)]
    public readonly array $results;
    public readonly Severity $severity;
    public readonly bool $successful;

    public function __construct(
        public readonly string $uri,
        public readonly Profile $profile,
        public readonly TargetInterface $target,
        public readonly ReportType $type = ReportType::ASSESSMENT,
        array $results = []
    )
    {
        // Validate the number of results reflects the number of policies that were 
        // provided by the profile.
        $expected_results = count($type == ReportType::ASSESSMENT ? $profile->policies : $profile->dependencies);
        if (count($results) != $expected_results) {
            throw new Exception('Incorrect number of ' . $this->type->value . ' report results: ' . count($results) . '. Expecting: ' . $expected_results);
        }

        $this->setReportingPeriod($profile->reportingPeriodStart, $profile->reportingPeriodEnd);

        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        $this->uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        // Ensure each item in the array is an AuditResponse and make the results
        // array a keyed array of results.
        $this->results = array_combine(
            array_map(fn(AuditResponse $r) => $r->policy->name, $results),
            array_map(fn(AuditResponse $r) => $r, $results)
        );

        if (empty($results)) {
            $this->severity = Severity::getDefault();
            $this->successful = true;
            return;
        }

        // Severity is the high (heaviest) severity weight of an unsuccessful policy.
        $this->severity = array_reduce($this->results, function (Severity $c, AuditResponse $i) {
            return $i->state->isSuccessful() ? $c : Severity::fromInt(max($c->getWeight(), $i->policy->severity->getWeight()));
        }, Severity::getDefault());

        // Successful means all results were successful.
        $this->successful = array_reduce($this->results, function (AuditResponse|null $a, AuditResponse $b) {
            $a ??= $b;
            return !$a->state->isSuccessful() ? $a : $b;
        })->state->isSuccessful();
    }

    /**
     * Produce report object variation with altered properties.
     */
    public function with(...$properties):self
    {
        $args = MergeUtility::arrayMerge(get_object_vars($this), $properties);
        unset($args['uuid']);
        return new static(...$args);
    }
}