<?php

namespace DrutinyTests;

use Drutiny\Kernel as DrutinyKernel;

class Kernel extends DrutinyKernel {
    protected function getWorkingDirectory(): string
    {
        return dirname(__DIR__);
    }
}