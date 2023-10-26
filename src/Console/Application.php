<?php

namespace Drutiny\Console;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Application extends BaseApplication
{
    private $registrationErrors = [];

    public function __construct(
      string $name, 
      string $version,
      protected EventDispatcher $eventDispatcher,
      protected LoggerInterface $logger,
      protected ContainerInterface $container
    )
    {
        parent::__construct($name, $version);
    }

    /**
     * {@inheritdoc}
     */
    public function doRun(InputInterface $input = null, OutputInterface $output = null)
    {
      $this->checkForUpdates($output);

      if ($this->registrationErrors) {
          $this->renderRegistrationErrors($input, $output);
      }

      $event = new GenericEvent('application.run', [
        'input' => $input,
        'output' => $output,
        'name' => $this->getName(),
        'version' => $this->getVersion(),
      ]);
      $this->eventDispatcher->dispatch($event, $event->getSubject());
      return parent::doRun($input, $output);
    }

    /**
     * {@inheritdoc}
     */
    protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output)
    {
        $startTimer = microtime(TRUE);
        switch ($output->getVerbosity()) {
          case OutputInterface::VERBOSITY_VERBOSE:
            $this->container->get('logger.logfile')->setLevel('NOTICE');
            break;
          case OutputInterface::VERBOSITY_VERY_VERBOSE:
            $this->container->get('logger.logfile')->setLevel('INFO');
            break;
          case OutputInterface::VERBOSITY_DEBUG:
            $this->container->get('logger.logfile')->setLevel('DEBUG');
            break;
          default:
            $this->container->get('logger.logfile')->setLevel('WARNING');
            break;
        }

        $event = new GenericEvent('command.run', [
          'command' => $command,
          'input' => $input,
          'output' => $output,
        ]);
        $this->eventDispatcher->dispatch($event, $event->getSubject());

        try {
          if (!$command instanceof ListCommand) {
            if ($this->registrationErrors) {
                $this->renderRegistrationErrors($input, $output);
                $this->registrationErrors = [];
            }
            
            $returnCode = parent::doRunCommand($command, $input, $output);
            $endTimer = microtime(TRUE);

            $event = new GenericEvent('command.exit', [
              'command' => $command,
              'input' => $input,
              'output' => $output,
              'exitCode' => $returnCode,
              'runtime' => ($endTimer-$startTimer),
            ]);
            $this->eventDispatcher->dispatch($event, $event->getSubject());

            return $returnCode;
          }

          $returnCode = parent::doRunCommand($command, $input, $output);
          $endTimer = microtime(TRUE);
        }
        catch (\Exception $e) {
          $this->container->get(LoggerInterface::class)->error($e->getMessage());
          throw $e;
        }

        if ($this->registrationErrors) {
            $this->renderRegistrationErrors($input, $output);
            $this->registrationErrors = [];
        }
        $this->container->get('logger')->notice("Application Command {command} completed in {runtime} seconds.", [
          'command' => $command->getName(),
          'runtime' => ($endTimer-$startTimer),
        ]);

        return $returnCode;
    }

    private function renderRegistrationErrors(InputInterface $input, OutputInterface $output)
    {
        if ($output instanceof ConsoleOutputInterface) {
            $output = $output->getErrorOutput();
        }

        (new SymfonyStyle($input, $output))->warning('Some commands could not be registered:');

        foreach ($this->registrationErrors as $error) {
            $this->doRenderThrowable($error, $output);
        }
    }

    private function checkForUpdates(OutputInterface $output = null)
    {
      
    }
}
