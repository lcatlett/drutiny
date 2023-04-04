<?php

use Drutiny\Kernel;
use Symfony\Component\Console\Input\ArgvInput;

if (!in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
    echo 'Warning: The console should be invoked via the CLI version of PHP, not the '.PHP_SAPI.' SAPI'.PHP_EOL;
}

list($major, $minor, ) = explode('.', phpversion());

// Identify PHP versions lower than PHP 7.4.
if ($major < 7 || ($major == 7 && $minor < 4)) {
  echo "ERROR: Application requires PHP 7.4 or later. Currently running: ".phpversion()."\n";
  exit;
}

set_time_limit(0);

$project_dir = Phar::running() ?: getcwd();

// Drutiny is installed as a composer dependency and the project directory
// is further up.
if (preg_match('#^(.*)/vendor/drutiny/drutiny$#', __DIR__, $matches)) {
  $project_dir = $matches[1];
}

define('DRUTINY_LIB', $project_dir);

require DRUTINY_LIB.'/vendor/autoload.php';

$version_files = [DRUTINY_LIB.'/VERSION', dirname(__DIR__).'/VERSION'];

// Load in the version if it can be found.
$versions = array_filter(array_map(function($file) {
  return file_exists($file) ? file_get_contents($file) : FALSE;
}, $version_files));

// Load from git.
if (empty($versions) && file_exists(DRUTINY_LIB . '/.git') && $git_bin = exec('which git')) {
  $versions[] = exec(sprintf('%s -C %s branch --no-color | cut -b 3-', $git_bin, DRUTINY_LIB)) . '-dev';
}

// Fallback option.
$versions[] = 'dev';

$suffix = '';
if (file_exists(DRUTINY_LIB.'/BUILD_DATETIME')) {
  $date = unserialize(file_get_contents(DRUTINY_LIB.'/BUILD_DATETIME'));
  $suffix = $date->format(' (Y-m-d H:i:s T)');
}

$installed = require DRUTINY_LIB . '/vendor/composer/installed.php';
$environment = $installed['root']['dev'] ? 'dev' : 'production';

$kernel = new Kernel($environment, reset($versions).$suffix);

$kernel->getApplication()->run(
  // If this is a phar file, then run the extraction command. Otherwise behave as normal.
  Phar::running() ? new ArgvInput([$_SERVER['argv'][0], 'phar-extract', '-vvv']) : null
);
