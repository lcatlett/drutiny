<?php

namespace Drutiny\Audit\Drupal;

use Drutiny\Audit;
use Drutiny\Helper\TextCleaner;
use Drutiny\Sandbox\Sandbox;

/**
 * Generic module is enabled check.
 *
 */
class ModuleEnabled extends Audit
{

    public function configure():void
    {
           $this->addParameter(
               'module',
               static::PARAMETER_OPTIONAL,
               'The module to check is enabled.',
           );
           $this->setDeprecated();
    }


  /**
   * {@inheritdoc}
   */
    public function audit(Sandbox $sandbox)
    {
        $module = $this->getParameter('module');
        $list = $this->target->getService('drush')
          ->pmList([
            'format' => 'json',
            'type' => 'module'
          ])
          ->run(function ($output) {
              return TextCleaner::decodeDirtyJson($output);
          });

        if (!isset($list[$module])) {
            return false;
        }

        $status = strtolower($list[$module]['status']);

        return ($status == 'enabled');
    }
}
