<?php

namespace Drutiny\Report\Format\LeagueMarkdown;

use League\CommonMark\ConverterInterface;
use League\CommonMark\Extension\Table\TableCell;
use League\CommonMark\Extension\Table\TableSection;
use Twig\Extra\Markdown\MarkdownInterface;

class LeagueMarkdown implements MarkdownInterface
{
    public function __construct(private ConverterInterface $converter) {}

    public function convert(string $body): string
    {
        return $this->converter->convert($body);
    }

    public static function tableCellScopeAttribute(TableCell $node): ?string
    {
        $tag = $node->getType() === TableCell::TYPE_HEADER ? 'th' : 'td';
        if ($tag == 'th') {
            return 'col';
        }
        return null;
    }

    public static function tableSectionClassAttribute(TableSection $node): ?string {
        $tag = $node->getType() === TableSection::TYPE_HEAD ? 'thead' : 'tbody';

        if ($tag == 'thead') {
            return 'table-active';
        }
        return null;
    }
}