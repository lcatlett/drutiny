<?php

namespace Drutiny\Audit\Filesystem;

use Drutiny\Attribute\Parameter;
use Drutiny\Attribute\Type;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Target\FilesystemInterface;
use Drutiny\Audit\AuditValidationException;
use Drutiny\Policy\Dependency;
use Symfony\Component\Process\Process;

/**
 * Checks for existence of requested file/directory on specified path.
 */
#[Dependency(expression: 'Target.typeOf("' . FilesystemInterface::class . '")')]
#[Parameter(name: 'contents_index', description: 'The index in the search results to retrieve file contents from. Default 0.', default: 0, type: Type::INTEGER)]
class FilesContentsAnalysis extends FilesExistenceAnalysis {

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
    $this->set('contents', $this->target->execute(Process::fromShellCommandline('cat ' . $results['findings'][$index]), function ($output) {
      return trim($output);
    }));
  }
}
