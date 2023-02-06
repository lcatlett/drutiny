<?php

namespace Drutiny\Audit\Filesystem;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Target\DrushTargetInterface;
use Drutiny\Target\FilesystemInterface;
use Drutiny\Audit\AuditValidationException;

/**
 * Checks for existence of requested file/directory on specified path.
 */
class FilesContentsAnalysis extends FilesExistenceAnalysis {

  public function configure():void {
    parent::configure();
    $this->addParameter(
      'contents_index',
      static::PARAMETER_OPTIONAL,
      'The index in the search results to retrieve file contents from. Default 0.',
      0
    );
  }

  /**
   * @inheritdoc
   */
  protected function validate():bool
  {
    return $this->target instanceof FilesystemInterface;
  }

  /**
   * @inheritdoc
   */
  public function gather(Sandbox $sandbox) {
    parent::gather($sandbox);
    $results = $this->get('results');
    $index = $this->get('contents_index');
    if ($results['found'] == 0 || !isset($results['findings'][$index])) {
      throw new AuditValidationException("File contents do not exist.");
    }
    $this->set('contents', $this->target->getService('exec')->run('cat ' . $results['findings'][$index], function ($output) {
      return trim($output);
    }));
  }
}
