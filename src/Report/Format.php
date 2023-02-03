<?php

namespace Drutiny\Report;

use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Psr\Log\LoggerInterface;

abstract class Format implements FormatInterface
{
    use ContainerAwareTrait;

    protected string $namespace;
    protected string $name = 'unknown';
    protected array $options = [];
    protected LoggerInterface $logger;
    protected OutputInterface $buffer;


    public function __construct(ContainerInterface $container, LoggerInterface $logger)
    {
        $this->setContainer($container);
        $verbosity = $container->get('output')->getVerbosity();
        $this->buffer = new BufferedOutput($verbosity, true);
        $this->logger = $logger;
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
    public function setName(string $name):FormatInterface
    {
      $this->name = $name;
      return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getName():string
    {
        return $this->name;
    }
}
