<?php

namespace Drutiny\Report;

use Drutiny\Profile\FormatDefinition;

interface FormatInterface
{
    /**
     * Return the name the format may be called by.
     */
    public function getName():string;

    /**
     * Set the namespace for writing data to the format.
     */
    public function setNamespace(string $namespace):void;

    /**
     * Set options for the format.
     */
    public function setDefinition(FormatDefinition $definition):FormatInterface;

    /**
     * Render the assessment into the format.
     */
    public function render(Report $report):FormatInterface;

    /**
     * Write the format to the format medium (e.g. filesystem).
     *
     * @return iterable return a string location where the format was written too.
     */
    public function write():iterable;
}
