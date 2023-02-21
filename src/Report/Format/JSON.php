<?php

namespace Drutiny\Report\Format;

use Drutiny\AssessmentInterface;
use Drutiny\Attribute\AsFormat;
use Drutiny\Profile;
use Drutiny\Report\FilesystemFormatInterface;
use Drutiny\Report\FormatInterface;
use Symfony\Component\Console\Output\StreamOutput;

#[AsFormat(
  name: 'json',
  extension: 'json'
)]
class JSON extends FilesystemFormat implements FilesystemFormatInterface
{
    protected string $name = 'json';
    protected string $extension = 'json';
    protected $data;

    /**
     * Return an array of FormattedOutput objects.
     */
    public function getOutput():iterable
    {
      return [];
    }

    protected function prepareContent(Profile $profile, AssessmentInterface $assessment):array
    {
        $json = [
          'date' => date('Y-m-d'),
          'human_date' => date('F jS, Y'),
          'time' => date('h:ia'),
          'uri' => $assessment->uri(),
        ];
        $json['profile'] = $profile->export();
        $json['reporting_period_start'] = $profile->getReportingPeriodStart()->format('Y-m-d H:i:s e');
        $json['reporting_period_end'] = $profile->getReportingPeriodEnd()->format('Y-m-d H:i:s e');
        $json['policy'] = [];
        $json['results'] = [];
        $json['totals'] = [];

        foreach ($assessment->getResults() as $response) {
          $policy = $response->getPolicy();
          $json['policy'][] = $policy->export();

          $result = $response->export();
          $result['policy'] = $policy->name;
          $json['results'][] = $result;

          $total = $json['totals'][$response->getType()] ?? 0;
          $json['totals'][$response->getType()] = $total+1;
        }

        $json['total'] = array_sum($json['totals']);

        $this->data = $json;
        return $this->data;
    }

    public function render(Profile $profile, AssessmentInterface $assessment):FormatInterface
    {
        $this->buffer->write(json_encode($this->prepareContent($profile, $assessment)));
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function write():iterable
    {
      $filepath = $this->directory . '/' . $this->namespace . '.' .  $this->getExtension();
      $stream = new StreamOutput(fopen($filepath, 'w'));
      $stream->write($this->buffer->fetch());
      $this->logger->info("Written $filepath.");
      yield $filepath;
    }

    /**
     * {@inheritdoc}
     */
    public function setWriteableDirectory(string $dir):void
    {
      $this->directory = $dir;
    }
}
