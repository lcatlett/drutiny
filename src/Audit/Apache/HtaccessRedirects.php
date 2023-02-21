<?php

namespace Drutiny\Audit\Apache;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Symfony\Component\Process\Process;

/**
 * .htaccess redirects
 *
 * @Token(
 *  name = "total_redirects",
 *  description = "The number of redirects counted.",
 *  type = "integer",
 *  default = 10
 * )
 */
class HtaccessRedirects extends Audit
{

    public function configure():void
    {
         $this
        ->addParameter(
            'max_redirects',
            static::PARAMETER_OPTIONAL,
            'The maximum number of redirects to allow.'
        );
    }

  /**
   *
   */
    public function audit(Sandbox $sandbox)
    {

        $patterns = array(
            'RedirectPermanent',
            'Redirect(Match)?.*?(301|permanent) *$',
            'RewriteRule.*\[.*R=(301|permanent).*\] *$',
        );
        $regex = '^ *(' . implode('|', $patterns) . ')';
        $command = "grep -Ei '$regex' %docroot%/.htaccess | wc -l";

        $total_redirects = (int) $this->target->execute(Process::fromShellCommandline($command));

        $this->set('total_redirects', $total_redirects);

        return $total_redirects < $this->getParameter('max_redirects', 10);
    }
}
