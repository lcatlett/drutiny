<?php

namespace Drutiny\Console\Command;

use Drutiny\Attribute\PluginField;
use Drutiny\Plugin;
use Drutiny\Plugin\FieldType;
use Drutiny\Plugin\Question;
use Drutiny\Settings;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 */
class PluginSetupCommand extends Command
{
    public function __construct(protected Settings $settings, protected ContainerInterface $container)
    {
        parent::__construct();
    }

  /**
   * @inheritdoc
   */
    protected function configure()
    {
        $this
        ->setName('plugin:setup')
        ->setDescription('Register credentials against an API drutiny integrates with.')
        ->addArgument(
            'namespace',
            InputArgument::REQUIRED,
            'The service to authenticate against.',
        );
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $namespace = $input->getArgument('namespace');
        $registry = $this->settings->get('plugin.registry');

        if (!isset($registry[$namespace])) {
            $io->error("No such plugin found: $namespace.");
            return 1;
        }

        $plugin = $this->container->get($registry[$namespace]);

        $values = [];
        foreach ($plugin->getFieldAttributes() as $field) {
            $values[$field->name] = $this->setupField($field, $plugin, $io);
        }

        $plugin->saveAs($values);

        $io->success("Plugin '$namespace' has been setup.");
        return 0;
    }

    /**
     * Get user input to get the value of a field.
     */
    protected function setupField(PluginField $field, Plugin $plugin, SymfonyStyle $io)
    {
        $extra = ' ';
        $default_value = $field->default;
        if (isset($plugin->{$field->name})) {
            $default_value = $plugin->{$field->name};
            $extra = "\n<comment>An existing credential exists.\n";
            if ($field->type == FieldType::CONFIG) {
                $extra .= "Existing value: {$default_value}\n";
            }
            $extra .= "Leave blank to use existing value.</comment>\n";
        }
        $ask = sprintf("%s%s\n<info>[%s]</info>:     ", $extra, ucfirst($field->description), $field->name);
        do {

            $value = match ($field->ask) {
                Question::CHOICE => $io->choice($ask, $field->choices, $default_value),
                Question::CONFIRMATION => $io->confirm("$ask (y/n)?", $default_value ?? true),
                Question::DEFAULT => $io->ask($ask, $default_value)
            };
            if (!call_user_func($field->validation, $value)) {
                $io->error("Input failed validation");
                continue;
            }
            break;
        }
        while (true);
        return $value;
    }
}
