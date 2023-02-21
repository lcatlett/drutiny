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
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\Yaml\Yaml;
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

        if (!$command instanceof ListCommand) {
            if ($this->registrationErrors) {
                $this->renderRegistrationErrors($input, $output);
                $this->registrationErrors = [];
            }

            return parent::doRunCommand($command, $input, $output);
        }

        $returnCode = parent::doRunCommand($command, $input, $output);
        $endTimer = microtime(TRUE);

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
      $container = $this->container;

      // Check for 2.x drutiny credentials and migrate them if 3.x credentials are
      // not yet setup.
      $old_path = $container->getParameter('config.old_path');
      $config = $container->get('config');
      $creds = $container->get('credentials');

      // If 3.x creds are set or 2.x creds dont' exist, don't continue.
      if (!file_exists($old_path) || count($config->getNamespaces()) || count($creds->getNamespaces())) {
        return;
      }

      $map = [
        'sumologic' => 'sumologic',
        'github' => 'github',
        'cloudflare' => 'cloudflare',
        'acquia:cloud' => 'acquia_api_v2',
        'acquia:lift' => 'acquia_lift',
        'http' => 'http',
      ];

      $old_creds = Yaml::parseFile($old_path);

      foreach ($container->findTaggedServiceIds('plugin') as $id => $info) {
          $plugin = $container->get($id);

          if (!isset($map[$plugin->getName()])) {
            continue;
          }

          if (!isset($old_creds[$map[$plugin->getName()]])) {
            continue;
          }

          foreach ($old_creds[$map[$plugin->getName()]] as $field => $value) {
            $plugin->setField($field, $value);
          }

          $output->writeln("Migrated plugin credentials for " . $plugin->getName() . ".");
      }

    }
}
