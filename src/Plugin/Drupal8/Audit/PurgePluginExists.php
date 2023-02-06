<?php

namespace Drutiny\Plugin\Drupal8\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;

/**
 * Check a purge plugin exists.
 */
class PurgePluginExists extends Audit
{

    public function configure():void
    {
         $this->addParameter(
             'plugin',
             static::PARAMETER_OPTIONAL,
             'The plugins to check exists',
         );
    }

    /**
     * {@inheritDoc}
     */
    public function audit(Sandbox $sandbox)
    {
        $plugin_name = $this->getParameter('plugin');

        try {
            $config = $sandbox->drush([
              'format' => 'json',
              'include-overridden' => null,
            ])->configGet('purge.plugins');
            $plugins = $config['purgers'];

            foreach ($plugins as $plugin) {
                if ($plugin['plugin_id'] == $plugin_name) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            $this->set('exception', $e->getMessage());
        }

        return false;
    }
}
