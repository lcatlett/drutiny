<?php

namespace Drutiny\AuditResponse;

use Drutiny\Policy;
use Drutiny\Audit;
use Drutiny\Kernel;
use Drutiny\Entity\ExportableInterface;
use Drutiny\Entity\SerializableExportableTrait;

/**
 * Class AuditResponse.
 *
 * @package Drutiny\AuditResponse
 */
class AuditResponse implements ExportableInterface
{
    use SerializableExportableTrait {
      import as importUnserialized;
    }

    protected Policy $policy;
    protected $state = Audit::NOT_APPLICABLE;
    protected $tokens = [];

  /**
   * AuditResponse constructor.
   *
   * @param Policy $policy
   *   A policy object of type Drutiny\Policy.
   */
    public function __construct(Policy $policy)
    {
        $this->policy = $policy;
    }

  /**
   * Get the AudiResponse Policy.
   */
    public function getPolicy():Policy
    {
        return $this->policy;
    }

  /**
   * Set the state of the response.
   */
    public function set(int $state = Audit::ERROR, array $tokens = []):AuditResponse
    {
        switch (true) {
            case ($state === Audit::SUCCESS):
            case ($state === Audit::PASS):
                $state = Audit::SUCCESS;
                break;

            case ($state === Audit::FAILURE):
            case ($state === Audit::FAIL):
                $state = Audit::FAIL;
                break;

            case ($state === Audit::NOT_APPLICABLE):
            case ($state === null):
                $state = Audit::NOT_APPLICABLE;
                break;

            case ($state === Audit::IRRELEVANT):
            case ($state === Audit::WARNING):
            case ($state === Audit::WARNING_FAIL):
            case ($state === Audit::NOTICE):
            case ($state === Audit::ERROR):
              // Do nothing. These are all ok.
                break;

            default:
                throw new AuditResponseException("Unknown state set in Audit Response: $state");
        }
        $this->setTokens($tokens);
        $this->state = $state;
        return $this;
    }

    public function setTokens(array $tokens = []):AuditResponse
    {
        $this->tokens = $tokens;
        $this->tokens['chart'] = $this->policy->chart;
        return $this;
    }

    public function setToken($name, $value):AuditResponse
    {
      $this->tokens[$name] = $value;
      return $this;
    }

    public function getTokens():array
    {
      return $this->tokens;
    }

  /**
   * Get the exception message if present.
   */
    public function getExceptionMessage():string
    {
        return isset($this->tokens['exception']) ? $this->tokens['exception'] : '';
    }

  /**
   * Get the type of response based on policy type and audit response.
   */
    public function getType():string
    {
        if ($this->isIrrelevant()) {
            return 'irrelevant';
        }
        if ($this->isNotApplicable()) {
            return 'not-applicable';
        }
        if ($this->hasError()) {
            return 'error';
        }
        if ($this->isNotice()) {
            return 'notice';
        }
        $policy_type = $this->policy->type;
        if ($policy_type == 'data') {
            return 'data';
        }
        if (!$this->isSuccessful()) {
          return 'failure';
        }
        if ($this->hasWarning()) {
            return 'warning';
        }
        return 'success';
    }

    /**
     *
     */
    public function isSuccessful():bool
    {
        return $this->state === Audit::SUCCESS || $this->isNotice() || $this->state === Audit::WARNING;
    }

    public function isFailure():bool
    {
      return $this->getType() == 'failure';
    }

  /**
   *
   */
    public function isNotice():bool
    {
        return $this->state === Audit::NOTICE;
    }

  /**
   *
   */
    public function hasWarning():bool
    {
        return $this->state === Audit::WARNING || $this->state === Audit::WARNING_FAIL;
    }

    public function hasError():bool
    {
        return $this->state === Audit::ERROR;
    }

    public function isNotApplicable():bool
    {
        return $this->state === Audit::NOT_APPLICABLE;
    }

    public function isIrrelevant():bool
    {
        return $this->state === Audit::IRRELEVANT;
    }

    public function getSeverity():string
    {
        return $this->policy->severity;
    }

    public function getSeverityCode():int
    {
        return $this->policy->getSeverity();
    }

    /**
     * {@inheritdoc}
     */
    public function export():array
    {
      return [
        'policy' => $this->policy->name,
        'status' => $this->isSuccessful(),
        'is_notice' => $this->isNotice(),
        'has_warning' => $this->hasWarning(),
        'has_error' => $this->hasError(),
        'is_not_applicable' => $this->isNotApplicable(),
        'type' => $this->getType(),
        'severity' => $this->getSeverity(),
        'severity_code' => $this->getSeverityCode(),
        'exception' => $this->getExceptionMessage(),
        'tokens' => $this->tokens,
        'state' => $this->state,
      ];
    }

    /**
     * {@inheritdoc}
     */
    public function import($export)
    {

      // $this->state = $export['state'];
      // $this->tokens = $export['tokens'];
      $this->policy = drutiny()->get('policy.factory')->loadPolicyByName($export['policy']);
      unset($export['policy']);
      $this->importUnserialized($export);
    }
}
