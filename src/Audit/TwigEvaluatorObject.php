<?php

namespace Drutiny\Audit;

use Drutiny\AuditFactory;
use Drutiny\Policy;
use Drutiny\Target\TargetInterface;
use Exception;

class TwigEvaluatorObject {
        public function __construct(
            public readonly string $namespace, 
            protected array $set, 
            protected TwigEvaluator $twigEvaluator,
            protected AuditFactory $auditFactory)
        {
        }

        /**
         * Allow argument-less methods to be called as properties.
         */
        public function __get($name):bool {
            return $this->__call($name, []); 
        }

        public function __call($name, $args):bool {
            if (!isset($this->set[$name])) {
                throw new Exception("{$this->namespace}.{$name} does not exist.");
            }
            $info = $this->set[$name];

            $contexts = [];

            // Argument Spec //
            // The name of an argument to pass into an expression:
            // - arg1
            //
            // The token in an expression to swap out with a literal value:
            // - $arg1
            //
            // A parameter to map into a policy using use_audit:
            // $arg1: arg1

            // This allows us to pass in variables from the twig runtime into function
            // provided the dependency definition specifies which keys to map the order 
            // of arguments passed into the function.
            foreach ($info['arguments'] ?? [] as $param) {
                $contexts[$param] = array_shift($args);
            }

            $target = $this->twigEvaluator->getGlobalContexts()['target'] ?? throw new Exception("Missing target from twigEvaluator global contexts.");

            if (isset($info['use_audit'])) {
                return $this->runPolicyAudit($name, $info, $target, $contexts);
            }
            elseif (!isset($info['expression'])) {
                throw new \Exception("Invalid dependency {$this->namespace}.$name. Requires 'expression' or 'use_audit' statement.");
            }
            
            // Dependencies primarily need to evaluate the target so we'll export
            // the target properties into the contexts so they're more accessible.
            foreach ($target->getPropertyList() as $key) {
                $contexts[$key] = $target->getProperty($key);
            }

            return (bool) $this->twigEvaluator->execute($info['expression'], $contexts);
        }

        /**
         * Create a pseudo policy to audit a result from.
         */
        protected function runPolicyAudit(string $name, array $info, TargetInterface $target, array $contexts):bool {
            $audit = $this->auditFactory->mock($info['use_audit'], $target);
            $tokens = [];
            foreach ($contexts as $key => $value) {
                // Cannot use as policy parameters.
                if (!$audit->hasArgument($key)) {
                    // Assume these are tokens to swap out in the expression.
                    $tokens[$key] = match(true) {
                        strtolower($value) == 'true' => 'true',
                        strtolower($value) == 'false' => 'false',
                        strtolower($value) == 'null' => 'null',
                        is_numeric($value) => $value,
                        // String
                        default => "'".$value."'" 
                    };
                    unset($contexts[$key]);
                }
            }

            if ($audit->hasArgument('expression') && isset($info['expression'])) {
                $contexts['expression'] = strtr($info['expression'], $tokens);
                $contexts['syntax'] = 'twig';
            }

            // Set required fields.
            $policy = new Policy(
                uuid: $this->namespace.$name,
                name: $this->namespace.':'.$name,
                title: "{$this->namespace}.$name",
                description: $name,
                failure: 'Failure message',
                success: 'Success message',
                class: $info['use_audit'],
                parameters: $contexts,
                source: 'phpunit'
            );
            return $audit->execute($policy)->state->isSuccessful();
        }
}