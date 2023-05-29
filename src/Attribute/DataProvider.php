<?php

namespace Drutiny\Attribute;

use Attribute;

/**
 * Use on classes extended from Drutiny\Audit\AbstractAnalysis.
 * 
 * This attribute informs the AbstractAnalysis class to gather data
 * from specific methods.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class DataProvider {}