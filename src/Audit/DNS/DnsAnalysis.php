<?php

namespace Drutiny\Audit\DNS;

use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Sandbox\Sandbox;
use InvalidArgumentException;

/**
 * Assert a value is present in the DNS record of the zone.
 */
class DnsAnalysis extends AbstractAnalysis
{

    public function configure():void
    {
        parent::configure();
        $this->addParameter(
            'type',
            static::PARAMETER_OPTIONAL,
            'The type of DNS record to lookup',
            'A'
        );
        $this->addParameter(
            'zone',
            static::PARAMETER_OPTIONAL,
            '',
        );
    }

    public function gather(Sandbox $sandbox)
    {
        $type = match($this->getParameter('type', 'A')) {
            'A' => DNS_A,
            'CNAME' => DNS_CNAME,
            'MX' => DNS_MX,
            'TXT' => DNS_TXT,
            default => throw new InvalidArgumentException("Parameter 'type' has a invalid value of: " . $this->getParameter('type') . '. Valid options are A, CNAME, MX and TXT.'),
        };
        $zone = $this->getParameter('zone', $this->target['domain']);

        // Set the zone incase it wasn't set.
        $this->set('zone', $zone);
        $this->set('dns', dns_get_record($zone, $type));
    }
}
