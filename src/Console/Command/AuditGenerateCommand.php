<?php

namespace Drutiny\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

/**
 * Helper for building checks.
 */
class AuditGenerateCommand extends Command
{

  /**
   * @inheritdoc
   */
    protected function configure()
    {
        $this
        ->setName('audit:generate')
        ->setDescription('Create an Audit class')
        ->setHidden();
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $title = $io->ask("Briefly describe what your Audit does");
        $class = $io->ask("What is the class name?");
        $namespace = $io->ask("What namespace does the class belong to?", 'Drutiny\Audit\\' . $class);
        $filename = $class . '.php';
        $dirname = $io->ask("Where should the php file be written to?", realpath('.'));

        $template = file_get_contents(dirname(__DIR__) . '/Audit/Template/SampleAudit.php');
        $audit = strtr($template, [
        'Drutiny\Audit\Template' => $namespace,
        'SampleAudit' => $class,
        'A template audit class to implement a real audit from.' => $title,
        ]);
        file_put_contents($dirname . '/' . $filename, $audit);
        $io->success("Audit written to $dirname/$filename.");
    }
}
