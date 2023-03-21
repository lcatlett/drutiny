<?php

namespace Drutiny\Report\Format;

use DateTime;
use Drutiny\Attribute\AsFormat;
use Drutiny\Helper\Json as HelperJson;
use Drutiny\Profile;
use Drutiny\Report\FilesystemFormatInterface;
use Drutiny\Report\FormatInterface;
use Drutiny\Report\Report;
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

    protected function prepareContent(Report $report):array
    {
        $this->data = HelperJson::extract($report);

        // Backwards compatibility formats
        $datetime = new DateTime();
        $this->data['date'] = $datetime->format('Y-m-d');
        $this->data['human_date'] = $datetime->format('F jS, Y');
        $this->data['time'] = $datetime->format('h:ia');

        $this->data['reporting_period_start'] = $report->reportingPeriodStart->format('Y-m-d H:i:s e');
        $this->data['reporting_period_end'] = $report->reportingPeriodEnd->format('Y-m-d H:i:s e');

        foreach ($report->results as $name => $response) {
          $this->data['policy'][] = $this->data['results'][$name]['policy'];

          $total = $this->data['totals'][$response->getType()] ?? 0;
          $this->data['totals'][$response->getType()] = $total+1;
        }

        $this->data['total'] = array_sum($this->data['totals']);
        return $this->data;
    }

    public function render(Report $report):FormatInterface
    {
        $this->buffer->write(json_encode($this->prepareContent($report)));
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
}
