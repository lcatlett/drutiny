<?php

namespace Drutiny\Policy;

use Drutiny\Audit\AuditInterface;
use Drutiny\Helper\ExpressionLanguageTranslation;

class Dependency
{

    /**
     * On fail behaviour: Fail policy in report.
     */
      const ON_FAIL_DEFAULT = 'fail';

    /**
     * On fail behaviour: Omit policy from report.
     */
      const ON_FAIL_OMIT = 'omit';

    /**
     * On fail behaviour: Report policy as error.
     */
      const ON_FAIL_ERROR = 'error';

    /**
     * On fail behaviour: Report as not applicable.
     */
      const ON_FAIL_REPORT_ONLY = 'report_only';

    /**
     * @var string Must be one of ON_FAIL constants.
     */
      protected string $onFail = 'fail';

      public readonly string $expression;

      public readonly string $syntax;

    public function __construct(
      string $expression = 'true',
      $on_fail = self::ON_FAIL_DEFAULT,
      string $syntax = 'expression_language',
      public readonly string $description = ''
      )
    {
        $this->setFailBehaviour($on_fail);
        
        // Gracefully port expression language syntax into twig.
        if ($syntax == 'expression_language') {
            $translation = new ExpressionLanguageTranslation($expression);
            $expression = $translation->toTwigSyntax();
            $syntax = 'twig';
        }
        $this->syntax = $syntax;
        $this->expression = $expression;
    }

    public function getExpression()
    {
        return $this->expression;
    }

    public function getFailBehaviour()
    {
        switch ($this->onFail) {
            case self::ON_FAIL_ERROR:
                return AuditInterface::ERROR;

            case self::ON_FAIL_REPORT_ONLY:
                return AuditInterface::NOT_APPLICABLE;

            case self::ON_FAIL_OMIT:
                return AuditInterface::IRRELEVANT;

            case self::ON_FAIL_DEFAULT;
            default:
            return AuditInterface::FAIL;
        }
    }

    public function export()
    {
        return [
        'on_fail' => $this->onFail,
        'expression' => $this->expression,
        'syntax' => $this->syntax
        ];
    }

    public function setFailBehaviour($on_fail = self::ON_FAIL_DEFAULT)
    {
        switch ($on_fail) {
            case self::ON_FAIL_ERROR:
            case self::ON_FAIL_DEFAULT:
            case self::ON_FAIL_REPORT_ONLY:
            case self::ON_FAIL_OMIT:
                $this->onFail = $on_fail;
                return $this;
            default:
                throw new \Exception("Unknown behaviour: $on_fail.");
        }
    }

    /**
     * Get the description.
     */
    public function getDescription():string
    {
      return $this->description;
    }
}
