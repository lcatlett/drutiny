<?php

namespace Drutiny\Report\Format;

use Drutiny\AssessmentInterface;
use Drutiny\AuditResponse\AuditResponse;
use Drutiny\Policy;
use Drutiny\Profile;
use Drutiny\Report\FormatInterface;
use Drutiny\Report\FilesystemFormatInterface;
use League\Csv\Writer;
use Symfony\Component\Console\Output\StreamOutput;
use Drutiny\Attribute\AsFormat;


#[AsFormat(
  name: 'csv',
  extension: 'csv'
)]
class CSV extends FilesystemFormat implements FilesystemFormatInterface
{
    protected array $datasets;
    protected string $directory;

    /**
     * {@inheritdoc}
     */
    public function setWriteableDirectory(string $dir):void
    {
      $this->directory = $dir;
    }

    public function render(Profile $profile, AssessmentInterface $assessment):FormatInterface
    {

        $date = $profile->getReportingPeriodEnd()->format('c');
        $insert = [];

        $target = $assessment->getTarget();
        $target_class = str_replace('\\', '', get_class($target));
        $schema_name = $target_class.'Data';

        $insert[$schema_name][0] = [
          'assessment_uuid' => $assessment->getUuid(),
          'target' => $target->getId(),
          'date' => $date,
        ];

        foreach ($target->getPropertyList() as $property_name) {
          try {
            $insert[$schema_name][0] += $this->normalizeToColumns($property_name, $target[$property_name]);
          }
          catch (\InvalidArgumentException $e) {}
        }

        $defaults = [
          'assessment_uuid' => $assessment->getUuid(),
          'profile' => $profile->name,
          'target' => $target->getId(),
          'start' => $profile->getReportingPeriodStart()->format('c'),
          'end' => $profile->getReportingPeriodEnd()->format('c'),
          'policy_name' => NULL,
          'policy_title' => NULL,
          'language' => NULL,
          'type' => NULL,
          'result_type' => NULL,
          'result_severity' => NULL,
          'date' => $date,
        ];

        foreach ($assessment->getResults() as $response) {
          $policy = $response->getPolicy();
          $dataset_name = $this->getPolicyDatasetName($policy);

          $policy_row = $this->getPolicyDatasetValues($policy, $response, $assessment);
          $policy_row['assessment_uuid'] = $assessment->getUuid();
          $insert[$dataset_name][] =  $policy_row;

          $assessment_row = $defaults;
          $assessment_row['policy_name'] = $policy->name;
          $assessment_row['policy_title'] = $policy->title;
          $assessment_row['language'] = $policy->language;
          $assessment_row['type'] = $policy->type;
          $assessment_row['result_type'] = $response->getType();
          $assessment_row['result_severity'] = $response->getSeverity();
          $insert['DrutinyAssessmentResults'][] = $assessment_row;
        }

        $this->datasets =  $insert;
        return $this;
    }

    public function write():iterable
    {
        // Append new rows.
        foreach ($this->datasets as $dataset_name => $rows) {
            // Ensure the cell order matches the schema.
            $writer = Writer::createFromString();
            $writer->setEscape('');
            $writer->insertOne(array_keys($rows[0]));
            $writer->insertAll($rows);
            $writer->setNewline("\r\n");
            //RFC4180Field::addTo($writer);
            $this->logger->info("Appending rows into $dataset_name.");

            $filepath = $this->directory . '/' . $dataset_name . '__' . $this->namespace . '.' . $this->getExtension();
            $stream = new StreamOutput(fopen($filepath, 'w'));
            $stream->write($writer->getContent());
            $this->logger->info("Written $filepath.");
            yield $filepath;
        }
    }

    public function getPolicyDatasetName(Policy $policy):string
    {
        return 'DrutinyPolicyResults_'.strtr($policy->name, [
          ':' => '',
          ]);
    }

    public function getPolicyDatasetValues(Policy $policy, AuditResponse $response, AssessmentInterface $assessment):array
    {
        $row = [
          'assessment_uuid' => '',
          'target' => $assessment->getTarget()['drush.alias'],
          'title' => $policy->title,
          'name' => $policy->name,
          'class' => $policy->class,
          'description' => $policy->description,
          'language' => $policy->language,
          'type' => $policy->type,
          'tags' => implode(',', $policy->tags),
          'severity' => $policy->severity,

          'result_type' => $response->getType(),
          'result_severity' => $response->getSeverity(),
          'result_date' => date('c', REQUEST_TIME),
        ];

        foreach ($policy->parameters as $key => $value) {
          try {
            $row += $this->normalizeToColumns('parameters_' . $key, $value);
          }
          catch (\InvalidArgumentException $e) {
            $this->logger->error("Omitting data from column parameters_{$key}");
          }
        }

        foreach ($response->getTokens() as $key => $value) {
          // Omit parameters as they're already exported above.
          if (in_array($key, ['chart', 'parameters'])) {
            continue;
          }
          try {
            $row += $this->normalizeToColumns('result_token_' . $key, $value);
          }
          catch (\InvalidArgumentException $e) {
            $this->logger->error("Omitting data from column result_token_{$key}");
          }
        }
        return $row;
    }

    /**
     * Flatten an array into a set of columns.
     */
    protected function normalizeToColumns(string $name, $data, $depth = 0):array
    {
      if ($depth > 1) {
        return [];
      }

      switch (gettype($data)) {
         case 'string':
         case 'integer':
         case 'double':
         case 'boolean':
         case 'NULL':
             return [$name => $data];
             break;

        case 'object':
          switch (get_class($data)) {
            case 'DateTime':
              return [$name => $data->format('c')];
              break;

            default:
              $this->logger->warning("Cannot convert data of type " . gettype($data) . " into CSV format. Omitting value of $name.");
              return [$name => '-'];
              break;
          }

          case 'array':
            $cells = [];
            if (count($data) > 8) {
              $this->logger->warning("Omitting field $name as it contains too many keys to normalize.");
              return [];
            }
            foreach ($data as $key => $value) {
                if (is_numeric($key)) {
                  $this->logger->warning("Omitting field $name.$key. Numeric fields are not supported in CSV schema.");
                  continue;
                }
                $cells += $this->normalizeToColumns("$name.$key", $value, $depth + 1);
            }
            return $cells;
            break;

         default:
            $this->logger->warning("Data of type " . gettype($data) . " no supported. Omitting field $name.");
            return [];
            break;
      }
    }
}
