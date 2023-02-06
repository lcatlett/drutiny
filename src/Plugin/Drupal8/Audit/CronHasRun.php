<?php

namespace Drutiny\Plugin\Drupal8\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;

/**
 *  Cron last run.
 */
class CronHasRun extends Audit
{
    public function configure():void
    {
           $this->addParameter(
               'cron_max_interval',
               static::PARAMETER_OPTIONAL,
               'The maximum interval between ',
           );
    }
  /**
   *
   */
    public function audit(Sandbox $sandbox)
    {

        try {
            $timestamp = $sandbox->drush(['format' => 'json'])->stateGet('system.cron_last');
            $timestamp = is_array($timestamp) ? $timestamp['system.cron_last'] : $timestamp;
        } catch (\Exception $e) {
            return false;
        }

      // Check that cron was run in the last day.
        $since = time() - $timestamp;
        $this->set('cron_last', date('Y-m-d H:i:s', $timestamp));

        if ($since > $this->getParameter('cron_max_interval')) {
            return false;
        }

        return true;
    }
}
