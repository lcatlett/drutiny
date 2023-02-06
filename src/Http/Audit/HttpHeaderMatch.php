<?php

namespace Drutiny\Http\Audit;

use Drutiny\Sandbox\Sandbox;

/**
 *
 */
class HttpHeaderMatch extends Http
{
    public function configure():void
    {
        $this->addParameter(
            'header',
            static::PARAMETER_REQUIRED,
            'The HTTP header to check the value of.'
        );
        $this->addParameter(
            'header_value',
            static::PARAMETER_REQUIRED,
            'The value to check against.'
        );
        $this->HttpTrait_configure();
    }

    public function audit(Sandbox $sandbox)
    {
        $value = $this->getParameter('header_value');
        $res = $this->getHttpResponse($sandbox);
        $header = $this->getParameter('header');

        if (!$res->hasHeader($header)) {
            return false;
        }
        $headers = $res->getHeader($header);
        return $value == $headers[0];
    }
}
