<?php

namespace Drutiny\Audit\Drupal;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;

/**
 * Ensure all module updates have been applied.
 */
class UpdateDBStatus extends Audit
{

    /**
     * A string which presence indicates updates are pending.
     */
    const AFFIRMATIVE_UPDATES_STRING = "Do you wish to run all pending updates";

    /**
     * @inheritdoc
     */
    public function audit(Sandbox $sandbox)
    {
        $output = $this->target->getService('drush')->updb('-n')->run();

        if (strpos($output, self::AFFIRMATIVE_UPDATES_STRING) !== false) {
            $lines = array_filter(explode(PHP_EOL, $output));
            $updates = [];
            while (strpos(current($lines), self::AFFIRMATIVE_UPDATES_STRING) === false) {
                preg_match("/\s*([\w\s]*\w)\s+(\d+)\s+(.*)/", current($lines), $matches);
                list(, $module, $revision, $message) = $matches;
                $updates[] = [
                'module' => $module,
                'revision' => $revision,
                'message' => $message
                ];
                next($lines);
            }
            $this->set('updates', $updates);
            return false;
        }
        return true;
    }
}
