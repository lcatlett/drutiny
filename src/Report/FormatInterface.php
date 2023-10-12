<?php

namespace Drutiny\Report;

use Drutiny\Profile\FormatDefinition;
use Symfony\Component\Console\Output\BufferedOutput;

interface FormatInterface
{
    /**
     * Return the name the format may be called by.
     */
    public function getName():string;

    /**
     * Set options for the format.
     */
    public function setDefinition(FormatDefinition $definition):FormatInterface;

    /**
     * Render the assessment into the format.
     */
    public function render(Report $report):RenderedReport|iterable;
}
