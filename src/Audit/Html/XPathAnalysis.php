<?php

namespace Drutiny\Audit\Html;

use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Audit\AuditValidationException;
use Drutiny\Sandbox\Sandbox;

/**
 * Run a local command and analyse the output.
 */
class XPathAnalysis extends AbstractAnalysis
{

    public function configure():void
    {
        parent::configure();
        $this->addParameter(
            'url',
            static::PARAMETER_REQUIRED,
            'Path to local command. Absolute or in user PATH.',
        );
        $this->addParameter(
            'xpath',
            static::PARAMETER_REQUIRED,
            'An XPath query to run against the downloaded HTML document.',
        );
    }


  /**
   * @inheritdoc
   */
    public function gather(Sandbox $sandbox)
    {
      $doc = new \DOMDocument();
      $doc->preserveWhiteSpace = false;
      @$doc->loadHTML(file_get_contents($this->getParameter('url')));

      $xpath = new \DOMXPath($doc);
      $entries = $xpath->query($this->getParameter('xpath'));

      $html = [];
      $text = [];
      foreach ($entries as $entry) {
        $html[] = $doc->saveXML($entry);
        $text[] = $entry->nodeValue;
      }

      $this->set('html', $html);
    }
}
