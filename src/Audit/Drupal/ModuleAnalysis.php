<?php

namespace Drutiny\Audit\Drupal;

use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Sandbox\Sandbox;

/**
 * Generic module is enabled check.
 *
 */
class ModuleAnalysis extends AbstractAnalysis
{
  /**
   *
   */
    public function gather(Sandbox $sandbox)
    {
        $list = $this->target->getService('drush')
          ->pmList([
            'format' => 'json',
            'type' => 'module'
          ])
          ->run(function ($output) {
              return $this->target->getService('drush')->decodeDirtyJson($output);
          });
        $this->set('modules', $list);
    }
}
