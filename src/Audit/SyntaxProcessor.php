<?php

namespace Drutiny\Audit;

use DateTimeZone;
use Drutiny\Helper\ExpressionLanguageTranslation;
use Psr\Log\LoggerInterface;

class SyntaxProcessor {
    public function __construct(
        protected TwigEvaluator $twigEvaluator,
        protected LoggerInterface $logger
    )
    {}

    /**
     * Evaluate an expression using the Symfony ExpressionLanguage engine.
     */
    public function evaluate(string $expression, $language = 'expression_language', array $contexts = []):mixed
    {
        try {
            if ($language == 'expression_language') {
                $translation = new ExpressionLanguageTranslation($old = $expression);
                $expression = $translation->toTwigSyntax();
                $this->logger->warning("Expression language is deprecreated. Syntax will be translated to Twig. '$old' => '$expression'.");
            }
            return $this->twigEvaluator->execute($expression, $contexts);
        } catch (\Exception $e) {
            $this->logger->error("Evaluation failure {syntax}: {expression}: {message}", [
                'syntax' => $language,
                'expression' => $expression,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Allow strings to utilise Audit and Target contexts.
     */
    public function interpolate(string $string, array $contexts = []): string
    {
        return $this->_interpolate($string, $contexts);
    }

    public function setTimezone(DateTimeZone $timezone):void
    {
        $this->twigEvaluator->setTimezone($timezone);
    }

    /**
     * Helper function for the public interpolate function.
     */
    private function _interpolate(string $string, iterable $vars, $key_prefix = ''): string
    {
        foreach ($vars as $key => $value) {
            if (is_iterable($value)) {
                $string = $this->_interpolate($string, $value, $key.'.');
            }

            $token = '{'.$key_prefix.$key.'}';
            if (false === strpos($string, $token)) {
                continue;
            }

            $value = (string) $value;
            $string = str_replace($token, $value, $string);
        }

        return $string;
    }
}