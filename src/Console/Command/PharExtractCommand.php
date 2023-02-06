<?php

namespace Drutiny\Console\Command;

use Composer\Semver\Comparator;
use Phar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Self update command.
 */
class PharExtractCommand extends DrutinyBaseCommand
{

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
        ->setName('phar-extract')
        ->setHidden(true)
        ->setDescription('Extract phar release file.');
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled()
    {
        return !empty(Phar::running());
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $release_dir = $this->getContainer()->getParameter('drutiny_releases_dir');
        $version = $this->getApplication()->getVersion();
        $extraction_location = "$release_dir/$version";
        $fs = new Filesystem;

        if ($fs->exists($extraction_location)) {
            $io->warning("$version already exists at $extraction_location. Will not override.");
            return 1;
        }
        $fs->mkdir($extraction_location);

        $phar = new Phar(Phar::running());
        $phar->extractTo($extraction_location);
        $io->success("$version extracted to $extraction_location.");

        $bin = $this->getContainer()->getParameter('drutiny_release_bin');
        $bin_dir = dirname($bin);
        $fs->mkdir($bin_dir);

        $release_bin = "$extraction_location/vendor/bin/drutiny";

        if (!$fs->exists($release_bin)) {
            $io->error("Cannot find drutiny bin in release. Expecting $release_bin.");
            return 2;
        }

        if ($fs->exists($bin)) {
            unlink($bin);
        }
        symlink($release_bin, $bin);
        $io->success("$version is now accessible from $bin.");

        return 0;
    }
}
