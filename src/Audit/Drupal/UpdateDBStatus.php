<?php

namespace Drutiny\Audit\Drupal;

use Drutiny\Attribute\DataProvider;
use Drutiny\Attribute\Version;
use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Helper\TextCleaner;

/**
 * Ensure all module updates have been applied.
 */
#[Version('2.0')]
class UpdateDBStatus extends AbstractAnalysis
{
    #[DataProvider]
    public function getUpdateStatus()
    {
        $updates = $this->target->getService('drush')->updatedbStatus(['format' => 'json'])->run(function ($output) {
            return TextCleaner::decodeDirtyJson($output);
        });
        $this->set('updates', $updates);
    }
}
