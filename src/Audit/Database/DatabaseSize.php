<?php

namespace Drutiny\Audit\Database;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\AuditResponse\AuditResponse;

/**
 *  Large databases can negatively impact your production site, and slow down things like database dumps.
 * @Token(
 *  name = "db",
 *  description = "The name of the database",
 *  type = "string"
 * )
 * @Token(
 *  name = "size",
 *  description = "The size of the database",
 *  type = "integer"
 * )
 */
class DatabaseSize extends Audit
{

    public function configure():void
    {
         $this->addParameter(
             'max_size',
             static::PARAMETER_OPTIONAL,
             'Fail the audit if the database size is greater than this value',
         );
        $this->addParameter(
            'warning_size',
            static::PARAMETER_OPTIONAL,
            'Issue a warning if the database size is greater than this value',
        );
    }


  /**
   * {@inheritdoc}
   */
    public function audit(Sandbox $sandbox)
    {
        $stat = $sandbox->drush(['format' => 'json'])
        ->status();

        $name = $stat['db-name'];
        $sql = "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) 'DB Size in MB'
            FROM information_schema.tables
            WHERE table_schema='{$name}'
            GROUP BY table_schema;";

        $resultLines = $sandbox->drush()->sqlq($sql);
        $resultLines = array_filter($resultLines, function ($line) {
            return $line !== 'DB Size in MB';
        });
        $size = (float) reset($resultLines);

        $this->set('db', $name);
        $this->set('size', $size);

        if ($this->getParameter('max_size') < $size) {
            return false;
        }

        if ($this->getParameter('warning_size') < $size) {
            return Audit::WARNING;
        }

        return true;
    }
}
