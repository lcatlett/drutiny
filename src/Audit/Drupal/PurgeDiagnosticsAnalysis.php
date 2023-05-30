<?php

namespace Drutiny\Audit\Drupal;

use Drutiny\Attribute\DataProvider;
use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Helper\TextCleaner;
use Drutiny\Target\Service\Drush;

class PurgeDiagnosticsAnalysis extends AbstractAnalysis {
    #[DataProvider]
    protected function runDiagnostics():void {
        $drush = $this->target->getService('drush');
        assert($drush instanceof Drush);

        $analysis = $drush->purgeDiagnostics(['format' => 'json'])->run(function ($output) {
            return TextCleaner::decodeDirtyJson($output);
        });

        $this->set('diagnostics', $analysis);
    }
}