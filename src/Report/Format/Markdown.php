<?php

namespace Drutiny\Report\Format;

use Drutiny\Report\FormatInterface;
use Drutiny\Attribute\AsFormat;
use Drutiny\Report\Report;

#[AsFormat(
  name: 'markdown',
  extension: 'md'
)]
class Markdown extends HTML
{

    public function render(Report $report):FormatInterface
    {
        parent::render($report);

        $markdown = self::formatTables($this->buffer->fetch());

        $lines = explode(PHP_EOL, $markdown);
        array_walk($lines, function (&$line) {
          $line = trim($line);
        });

        $this->buffer->write(implode(PHP_EOL, $lines));
        return $this;
    }

    protected function prepareContent(array $variables):array
    {
      $sections = [];

      // In 3.x we support Twig TemplateWrappers to be passed directly
      // to the report format.
      foreach ($this->definition->content->getBlockNames() as $block){
        $sections[] = $this->definition->content->renderBlock($block, $variables);
      }
      return $sections;
    }

    public static function formatTables($markdown)
    {
        $lines = explode(PHP_EOL, $markdown);
        $table = [
        'start' => null,
        'widths' => [],
        'rows' => [],
        ];

        foreach ($lines as $idx => $line) {
            if ($table['start'] === null) {
                if (strpos($line, ' | ') !== false) {
                    $table['start'] = $idx;
                } else {
                    continue;
                }
            } elseif (strpos($line, ' | ') === false) {
                foreach ($table['rows'] as $line_idx => $row) {
                    $widths = $table['widths'];

                    foreach ($row as $i => $value) {
                        $pad = array_search($line_idx, array_keys($table['rows'])) === 1 ? '-' : ' ';
                        $row[$i] = str_pad($value, $table['widths'][$i], $pad, STR_PAD_RIGHT);
                    }
                    $lines[$line_idx] = implode(' | ', $row);
                }

                $table['start']  = null;
                $table['widths'] = [];
                $table['rows']   = [];
                continue;
            }

            $cells = array_map('trim', explode('|', $line));

            foreach ($cells as $i => $value) {
                if (!isset($table['widths'][$i])) {
                    $table['widths'][$i] = strlen($value);
                } else {
                    $table['widths'][$i] = max($table['widths'][$i], strlen($value));
                }
            }
            $table['rows'][$idx] = $cells;
        }

        return implode(PHP_EOL, $lines);
    }
}
