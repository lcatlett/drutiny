<?php

namespace Drutiny\Report\Format;

use Drutiny\Profile;
use Drutiny\AssessmentInterface;
use Drutiny\Report\FormatInterface;
use Fiasco\SymfonyConsoleStyleMarkdown\Renderer;
use Drutiny\Attribute\AsFormat;

#[AsFormat(
    name: 'terminal',
    extension: 'md'
  )]
class Terminal extends Markdown
{
    /**
     * Change the report template to use a profile dependency report format.
     */
    public function setDependencyReport()
    {
      $this->options['content'] = $this->loadTwigTemplate('report/dependency');
    }

    /**
     * {@inheritdoc}
     */
    public function render(Profile $profile, AssessmentInterface $assessment):FormatInterface
    {
        parent::render($profile, $assessment);
        $this->buffer->write(Renderer::createFromMarkdown($this->buffer->fetch()));
        return $this;
    }

    /**
     * @deprecated
     */
    protected static function format(string $output)
    {
        return Renderer::createFromMarkdown($output);
    }

    /**
     * {@inheritdoc}
     */
    public function write():iterable
    {
        $this->output->write($this->buffer->fetch());
        yield 'terminal';
    }
}
