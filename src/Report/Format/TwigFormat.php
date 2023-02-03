<?php

namespace Drutiny\Report\Format;

use Drutiny\AssessmentInterface;
use Drutiny\AuditResponse\AuditResponse;
use Drutiny\Profile;
use Drutiny\Report\FilesystemFormatInterface;
use Drutiny\Report\Format;
use Drutiny\Report\FormatInterface;
use Drutiny\Report\FormattedOutput;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Twig\Environment;
use Twig\TwigFunction;

abstract class TwigFormat extends Format implements FilesystemFormatInterface
{
    protected Environment $twig;
    protected string $directory;

    public function configure()
    {
        $this->twig = $this->container->get('twig');
        $this->twig->addGlobal('ext', $this->getExtension());
        $this->buffer = new BufferedOutput($this->container->get('output')->getVerbosity(), true);
    }

    /**
     * {@inheritdoc}
     */
    public function setWriteableDirectory(string $dir):void
    {
      $this->directory = $dir;
    }

    /**
     * {@inheritdoc}
     */
    public function getExtension():string
    {
      return $this->extension;
    }


    public function render(Profile $profile, AssessmentInterface $assessment):FormatInterface
    {
        try {
          $template = $this->options['template'];
          // 2.x backwards compatibility.
          if (strpos($template, '.twig') === false) {
            $this->logger->warning("Deprecated template declaration found: $template. Templates should explicitly specify extension (e.g. .md.twig).");
            $template .= '.'.$this->extension.'.twig';
          }
          $this->logger->debug("Rendering {$this->name} with template $template.");
          $this->buffer->write($this->twig->render($template, [
            'profile' => $profile,
            'assessment' => $assessment,
            'sections' => $this->prepareContent($profile, $assessment),
          ]));
        }
        catch (\Twig\Error\Error $e) {
          $message[] = $e->getMessage();
          foreach ($e->getTrace() as $stack) {
            $message[] = strtr('file:line', $stack);
          }
          $this->logger->error(implode("\n", $message));
          if ($source = $e->getSourceContext()) {
            $lines = [];
            foreach (explode(PHP_EOL, $source->getCode()) as $idx => $line) {
              $lines[] = ($idx+1) . ":\t" . $line;
            }
            $this->logger->info(PHP_EOL . implode(PHP_EOL, $lines));
          }
          throw $e;
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function write():iterable
    {
      $filepath = $this->directory . '/' . $this->namespace . '.' . $this->extension;
      $stream = new StreamOutput(fopen($filepath, 'w'));
      $stream->write($this->buffer->fetch());
      $this->logger->info("Written $filepath.");
      yield $filepath;
    }

    /**
     * Attempt to load a twig template based on the provided format extension.
     *
     * Each class implementing this one will provide a property called "extension".
     * This extension becomes apart of a prefix used to auto load twig templates.
     *
     * @param $name the namespace for the template. E.g. "content/page"
     */
    final protected function loadTwigTemplate($name)
    {
      return $this->twig->load(sprintf('%s.%s.twig', $name, $this->extension));
    }

    /**
     * Produce an array of renderable sections for the twig template.
     */
    abstract protected function prepareContent(Profile $profile, AssessmentInterface $assessment):array;
}
