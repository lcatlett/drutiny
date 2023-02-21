<?php

namespace Drutiny\Console\Command;

use Composer\Semver\Comparator;
use Drutiny\Http\Client;
use Drutiny\Plugin\GithubPlugin;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Self update command.
 */
class SelfUpdateCommand extends DrutinyBaseCommand
{
    public function __construct(
      protected LoggerInterface $logger,
      protected GithubPlugin $githubPlugin,
      protected Client $drutinyHttpClient
    )
    {
      parent::__construct();
    }
    public const GITHUB_API_URL = 'https://api.github.com';
    public const GITHUB_ACCEPT_VERSION = 'application/vnd.github.v3+json';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
        ->setName('self-update')
        ->setDescription('Update Drutiny by downloading latest phar release.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $current_version = $this->getApplication()->getVersion();

        $composer_json = json_decode(file_get_contents(DRUTINY_LIB . '/composer.json'), true);

        $headers = [
          'User-Agent' => $this->getApplication()->getName() . ' drutiny-phar/' . $current_version,
          'Accept' => self::GITHUB_ACCEPT_VERSION,
          'Accept-Encoding' => 'gzip',
        ];

        try {
            $creds = $this->githubPlugin->load();
            $headers['Authorization'] = 'token ' . $creds['personal_access_token'];
        } catch (\Exception $e) {
            $io->warning($e->getMessage());
        }

        $client = $this->drutinyHttpClient->create([
          'base_uri' => self::GITHUB_API_URL,
          'headers' => $headers,
          'decode_content' => 'gzip',
        ]);

        $response = $client->get('repos/' . $composer_json['name'] . '/releases');
        $releases = json_decode($response->getBody(), true);

        $latest_release = current($releases);
        $new_version = $latest_release['tag_name'];

        if (!Comparator::greaterThan($new_version, $current_version)) {
            $io->success("No new updates.");
            return 0;
        }
        $this->logger->notice('New update available: ' . $new_version);

        if (!$io->confirm('Would you like to download and install the newest version ('.$new_version.')?')) {
            return 0;
        }
        $asset = $io->choice('Which version would you like to download?', array_map(fn ($a) => $a['name'], $latest_release['assets']));

        $download = array_filter($latest_release['assets'], function ($a) use ($asset) {
            return $a['name'] == $asset;
        });
        $download = reset($download);

        $tmpfile = tempnam(sys_get_temp_dir(), $download['name']);
        $resource = fopen($tmpfile, 'w');

        $this->logger->notice("Downloading {$download['name']}...");

        $response = $client->get('repos/' . $composer_json['name'] . '/releases/assets/' . $download['id'], [
          'headers' => [
            'Accept' => $download['content_type'],
          ],
        ]);

        fwrite($resource, $response->getBody());
        fclose($resource);

        chmod($tmpfile, 0766);

        $process = new Process([$tmpfile, '--version']);
        $this->logger->notice("{$download['name']} downloaded to $tmpfile.");
        $status = $process->setTty(true)->setPty(true)->run();
        unlink($tmpfile);
        return $status;
    }
}
