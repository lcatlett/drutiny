<?php

namespace Drutiny\Http\Audit;

use Drutiny\Sandbox\Sandbox;

/**
 *
 */
class HttpHeaderRegex extends Http
{

    public function configure():void
    {
        $this->addParameter(
            'header',
            static::PARAMETER_OPTIONAL,
            'The HTTP header to check the value of.'
        );
        $this->addParameter(
            'regex',
            static::PARAMETER_OPTIONAL,
            'A regular expressions to validate the header value against.'
        );
        $this->HttpTrait_configure();
    }


  /**
   *
   */
    public function audit(Sandbox $sandbox)
    {
        $regex = $this->getParameter('regex');
        $regex = "/$regex/";
        $res = $this->getHttpResponse($sandbox);
        $header = $this->getParameter('header');

        if (!$res->hasHeader($header)) {
            return false;
        }
        $headers = $res->getHeader($header);
        return preg_match($regex, $headers[0]);
    }
}
