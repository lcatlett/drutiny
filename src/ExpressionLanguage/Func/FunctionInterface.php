<?php

namespace Drutiny\ExpressionLanguage\Func;

use Closure;

interface FunctionInterface {
  public function getName():string;

  public function getCompiler():Closure;

  public function getEvaluator():Closure;
}


 ?>
