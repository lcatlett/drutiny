<?php

namespace Drutiny\Report\Format;

use Drutiny\AuditResponse\AuditResponse;
use Drutiny\Policy;
use Drutiny\Report\FormatInterface;
use Drutiny\Report\FilesystemFormatInterface;
use League\Csv\Writer;
use Symfony\Component\Console\Output\StreamOutput;
use Drutiny\Attribute\AsFormat;
use Drutiny\Helper\OpenApi;
use Drutiny\Report\Report;
use Fiasco\TabularOpenapi\Table;
use Fiasco\TabularOpenapi\TableManager;

#[AsFormat(
  name: 'csv',
  extension: 'csv'
)]
class CSV extends FilesystemFormat implements FilesystemFormatInterface
{
    protected TableManager $tabularSchema;

    public function render(Report $report):FormatInterface
    {
        $target = [];
        $target['class'] = get_class($report->target);
        $target['report_uuid'] = $report->uuid;
        $target['id'] = $report->target->getId();
        $target['date'] = $report->reportingPeriodEnd->format('c');

        foreach ($report->target->getPropertyList() as $property_name) {
          $target[$property_name] = $report->target[$property_name];
        }

        $this->tabularSchema = new TableManager(OpenApi::getFilename());
        // $this->tabularSchema->resolve('Result', 'policy');
        // $this->tabularSchema->resolve('Report', 'profile');

        $row = get_object_vars($report);
        $row['target'] = $target;

        $this->tabularSchema->getTable('Report')->insertRow($row);

        return $this;
    }

    public function write():iterable
    {
        $lookup_table = $this->tabularSchema->buildLookupTable();
        if ($lookup_table->getRowsTotal()) {
          yield $this->writeTable($lookup_table);
        }

        // Append new rows.
        foreach ($this->tabularSchema->getTables() as $table) {
            if (!$table->getRowsTotal()) {
              continue;
            }
            yield $this->writeTable($table);
        }
    }

    protected function writeTable(Table $table):string
    {
        $writer = Writer::createFromString();
        $writer->setEscape('');
        $writer->setNewline("\r\n");
        $headers = false;
        foreach ($table->fetchAll() as $values) {
          $values['_table'] = $table->uuid;
          if (!$headers) {
            $headers = array_keys($values);
            $writer->insertOne($headers);
          }
          $row = [];
          // Ensure the table values come out the right way.
          foreach ($headers as $header) {
            $row[$header] = $values[$header];
          }
          $writer->insertOne($row);
        }
        $filepath = $this->directory . '/' . $table->name . '__' . $this->namespace . '.' . $this->getExtension();
        $stream = new StreamOutput(fopen($filepath, 'w'));
        $stream->write($writer->toString());
        $this->logger->info("Written $filepath.");
        return $filepath;
    }
}
