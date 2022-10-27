<?php

namespace Drutiny\Console\Command;

use Drutiny\Event\RuntimeDependencyCheckEvent;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 *
 */
class StatusCommand extends DrutinyBaseCommand
{
    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
        ->setName('status')
        ->setDescription('Review key details about Drutiny\'s runtime environment')
        ;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        $headers = ['Criteria', 'Status', 'Value', 'Details'];
        $rows = [];

        $event = new RuntimeDependencyCheckEvent();
        $this->getContainer()
          ->get('event_dispatcher')
          ->dispatch($event, 'status');

        foreach ($event->getRuntimeDependencies() as $dependency) {
            $rows[] = [
              $dependency->getName(),
              $dependency->getStatus() ? 'Pass' : 'Fail',
              $dependency->getValue(),
              $dependency->getDetails(),
            ];
        }

        $style->table($headers, $rows);
        return 0;
    }
}
