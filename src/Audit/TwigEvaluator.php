<?php

namespace Drutiny\Audit;

use DateTimeZone;
use Error;
use Exception;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use TypeError;

/**
 * Evaluates expressions through Twig.
 */
class TwigEvaluator {
    protected array $globalContexts = [];

    public function __construct(
        protected Environment $twig,
        protected LoggerInterface $logger,
    )
    {
        
    }

    public function setTimezone(DateTimeZone $timezone):void
    {
        $this->twig->getExtension(CoreExtension::class)->setTimezone($timezone);
    }

    public function setContext($key, $value):self
    {
        $this->globalContexts[$key] = $value;
        return $this;
    }

    public function getGlobalContexts():array
    {
        return $this->globalContexts;
    }
    
    /**
     * Evaluate a twig expression.
     */
    public function execute(string $expression, array $contexts = []):mixed
    {
        try {
            $code = '{{ ('.$expression.')|json_encode()|raw }}';
            $template = $this->twig->createTemplate($code);
            $contexts = array_merge($this->globalContexts, $contexts);
            $output = $this->twig->render($template, $contexts);
            $result = json_decode($output, true);
        }
        catch (TypeError $e) {
            $this->logger->error($e->getMessage());
            return null;
        }
        return $result;
    }
}
