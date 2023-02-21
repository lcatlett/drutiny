<?php

namespace Drutiny\Report;

use Exception;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Drutiny\Attribute\AsFormat;

abstract class Format implements FormatInterface
{
    protected string $namespace;
    protected array $options = [];
    protected BufferedOutput $buffer;

    public function __construct(protected OutputInterface $output, protected LoggerInterface $logger)
    {
        $this->buffer = new BufferedOutput($output->getVerbosity(), true);
        $this->configure();
    }

    /**
     * {@inheritdoc}
     */
    public function setNamespace(string $namespace):void
    {
      $this->namespace = $namespace;
    }

    protected function configure() {}

    /**
     * {@inheritdoc}
     */
    public function setOptions(array $options = []):FormatInterface
    {
      $this->options = $options;
      return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getName():string
    {
      $reflect = new ReflectionClass($this);
      $attributes = $reflect->getAttributes(AsFormat::class);

      if (empty($attributes)) {
          throw new Exception(get_class($this) . " has no format attribute.");
      }

      $format = $attributes[0]->newInstance();
      return $format->name;
    }
}
