<?php

namespace Drutiny\Audit\Drupal;

use Drutiny\Attribute\DataProvider;
use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Helper\TextCleaner;
use Drutiny\Policy\Dependency;

/**
 * Generic module is enabled check.
 *
 */
#[Dependency('Drupal.isBootstrapped')]
class ModuleAnalysis extends AbstractAnalysis
{
    #[DataProvider]
    public function listModules():void
    {
        $list = $this->target->getService('drush')
          ->pmList([
            'format' => 'json',
            'type' => 'module',
            'fields' => 'project,package,path,status,version,display_name,type,name'
          ])
          ->run(function ($output) {
              return TextCleaner::decodeDirtyJson($output);
          });
        $this->set('modules', $list);
    }
}
