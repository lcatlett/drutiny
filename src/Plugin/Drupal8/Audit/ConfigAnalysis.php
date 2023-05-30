<?php

namespace Drutiny\Plugin\Drupal8\Audit;

use Drutiny\Attribute\DataProvider;
use Drutiny\Attribute\Parameter;
use Drutiny\Attribute\Type;
use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Helper\TextCleaner;

/**
 * Check a configuration is set correctly.
 */
#[Parameter(name: 'collection', description: 'The collection the config belongs to.', type: Type::STRING, mode: Parameter::REQUIRED)]
class ConfigAnalysis extends AbstractAnalysis
{
  /**
   * @inheritDoc
   */
    #[DataProvider]
    public function drushConfigGet():void
    {
        $collection = $this->getParameter('collection');

        $drush = $this->target->getService('drush');
        $command = $drush->configGet($collection, [
          'format' => 'json',
          'include-overridden' => true,
        ]);
        $config = $command->run(function ($output) {
          return TextCleaner::decodeDirtyJson($output);
        });

        $this->set('config', $config);
    }
}
