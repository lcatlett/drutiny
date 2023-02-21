<?php

namespace Drutiny;

use Psr\Container\ContainerInterface;

/**
 *
 */
class AssessmentManager {
    protected $assessments = [];

    public function __construct(protected ContainerInterface $container)
    {
        
    }

    /**
     * Create a new Assessment instance.
     */
    public function create():Assessment
    {
        return $this->container->get(Assessment::class);
    }

    public function addAssessment(Assessment $assessment)
    {
        $this->assessments[$assessment->uri()] = $assessment;
    }

    public function getAssessments():array
    {
        return $this->assessments;
    }

    public function getAssessmentByUri($uri):AssessmentInterface
    {
        return $this->assessments[$uri];
    }

    public function getResultsByPolicy($policy_name):array
    {
        $results = [];
        foreach ($this->assessments as $assessment) {
            $results[$assessment->uri()] = $assessment->getPolicyResult($policy_name);
        }
        return $results;
    }

    public function getPolicyNames()
    {
        $names = [];
        foreach ($this->assessments as $assessment) {
            foreach (array_keys($assessment->getResults()) as $name) {
              $names[$name] = $name;
            }
        }
        return array_values($names);
    }
}
