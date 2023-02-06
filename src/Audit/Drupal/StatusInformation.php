<?php

namespace Drutiny\Audit\Drupal;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Annotation\Token;

/**
 * Drush Status Information
 */
class StatusInformation extends Audit
{
    /**
     * {@inheritdoc}
     */
    public function configure():void
    {
        $this->setDeprecated("Use AbstractAnalysis and evaluate drush target status information.");
    }

    /**
     * {@inheritdoc}
     */
    public function audit(Sandbox $sandbox)
    {
        $stat = $sandbox->drush(['format' => 'json'])->status();
        $sandbox->setParameter('status', $stat);

        return Audit::NOTICE;
    }
}
