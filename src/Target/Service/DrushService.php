<?php

namespace Drutiny\Target\Service;

use Drutiny\Target\Service\ExecutionInterface;
use Drutiny\Target\Service\RemoteService;
use JsonException;

class DrushService
{
    protected string $alias;
    protected string $url;
    protected string $bin;
    protected ExecutionInterface $execService;

    protected const LAUNCHERS = ['../vendor/drush/drush/drush', 'drush-launcher', 'drush.launcher', 'drush'];
    protected $supportedCommandMap = [
      'configGet' => 'config:get',
      'pmList' => 'pm-list',
      'pmSecurity' => 'pm:security',
      'stateGet' => 'state:get',
      'status' => 'status',
      'userInformation' => 'user:information',
      'sqlq' => 'sqlq',
      'updb' => 'updb',
      'updatedbStatus' => 'updatedb:status',
      'variableGet' => 'vget',
    ];
    public function __construct(ExecutionInterface $service)
    {
        $this->execService = $service;
        // Load and cache the remote bin path for Drush.
        $cmd = 'which ' . implode(' || which ', static::LAUNCHERS);
        $this->bin = $this->execService->run($cmd, function ($output) {
            return trim($output);
        });
    }

    public function setUrl(string $url): DrushService
    {
        $this->url = $url;
        return $this;
    }

    public function isRemote()
    {
        return $this->execService instanceof RemoteService;
    }

    public static function decodeDirtyJson(string $output)
    {
        while (true) {
            $result = json_decode($output, true, JSON_THROW_ON_ERROR);

            if ($result === null) {
                $cleaned = substr($output, strpos($output, '{'));

                // Remove garbage from beginning of line and try again.
                if ($cleaned != $output) {
                    $output = $cleaned;
                    continue;
                }
            }
            return $result;
        }
    }

    public function runtime(\Closure $func, ...$args)
    {
        $reflection = new \ReflectionFunction($func);
        $filename = $reflection->getFileName();

        // it's actually - 1, otherwise you wont get the function() block
        $start_line = $reflection->getStartLine();
        $end_line = $reflection->getEndLine();
        $length = $end_line - $start_line;
        $source = file($filename);
        $body = array_slice($source, $start_line, $length);
        $body[0] = substr($body[0], strpos($body[0], 'function'));
        array_pop($body);

        $body = array_map('trim', $body);

        // // Compress code.
        $code = implode('', array_filter($body, function ($line) {
            // Ignore empty lines.
            if (empty($line)) {
                return false;
            }
            // Ignore comments. /* style will still be allowed.
            if (strpos($line, '//') === false) {
                return true;
            }
            return false;
        }));

        // Build code to pass in parameters
        $initCode = '';
        foreach ($reflection->getParameters() as $idx => $param) {
            $initCode .= strtr('$var = value;', [
          'var' => $param->name,
          'value' => var_export($args[$idx], true)
        ]);
        }
        // Compress.
        $initCode = str_replace(PHP_EOL, '', $initCode);
        $wrapper = strtr('function __d__(){@code}; echo "__DSTART".json_encode(__d__(), JSON_PARTIAL_OUTPUT_ON_ERROR)."__DEND";', [
        '@code' => $initCode.$code
      ]);
        $wrapper = base64_encode($wrapper);

        $options = ['--root=$DRUSH_ROOT'];
        if (isset($this->url)) {
            $options[] = '--uri='.escapeshellarg($this->url);
        }

        $command = strtr('echo @code | base64 --decode | @launcher @options php-script -', [
        '@code' => $wrapper,
        '@launcher' => $this->bin,
        '@options' => implode(' ', $options),
      ]);
        return $this->execService->run($command, function ($output) {
            // Drush may spit out garbage around the json output, so we put markers`
            // in the output so we can clearly see where the json response should be.
            $start = strpos($output, "__DSTART") + strlen("__DSTART");
            $end = strpos($output, "__DEND");

            $length = $end - $start;

            // Disgard all other output;
            $json = substr($output, $start, $length);
            try {
                return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                drutiny()->get('logger')->error("Failed to parse json output starting with: ".reset($lines).".");
                drutiny()->get('logger')->info($json);
                throw $e;
            }
        });
    }

    /**
     * Usage: ->configGet('system.settings', ['format' => 'json'])
     * Executes: drush config:get 'system.settings' --format=json
     */
    public function __call($cmd, $args)
    {
        if (!isset($this->supportedCommandMap[$cmd])) {
            throw new \RuntimeException("Drush command not supported: $cmd.");
        }
        // If the last argument is an array, it is an array of options.
        $options = is_array(end($args)) ? array_pop($args) : [];

        // Ensure the root argument is set unless drush.root is not available.
        if (!isset($options['root']) && !isset($options['r']) && $this->execService->hasEnvVar('DRUSH_ROOT')) {
            $options['root'] = '$DRUSH_ROOT';
        }

        if (isset($this->url)) {
            $options['uri'] = $this->url;
        }

        // Quote all arguments.
        array_walk($args, function (&$arg) {
            $arg = escapeshellarg($arg);
        });

        // Setup the options to pass into the command.
        foreach ($options as $key => $value) {
            $is_short = strlen($key) == 1;
            $opt = $is_short ? '-'.$key : '--'.$key;
            // Consider as flag. e.g. --debug.
            if (is_bool($value) && $value) {
                $args[] = $opt;
                continue;
            }
            $delimiter = $is_short ? ' ' : "=";
            // Key/value option. E.g. --format='json'
            $args[] = $opt.$delimiter.escapeshellarg($value);
        }

        array_unshift($args, $this->bin, $this->supportedCommandMap[$cmd]);

        $command = implode(' ', $args);

        // Return an object ready to run the command. This allows the caller
        // of this command to be able to specify the preprocess function easily.
        return (new class ($command, $this->execService) {
            protected $cmd;
            protected $service;
            public function __construct($cmd, ExecutionInterface $service)
            {
                $this->cmd = $cmd;
                $this->service = $service;
            }
            public function run(callable $outputProcessor = null)
            {
                return $this->service->run($this->cmd, $outputProcessor);
            }
        });
    }
}
