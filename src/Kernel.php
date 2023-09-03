<?php

namespace Drutiny;

use Drutiny\Attribute\AsSource;
use Drutiny\Attribute\Name;
use Drutiny\Console\Application;
use Drutiny\DependencyInjection\AddConsoleCommandPass;
use Drutiny\DependencyInjection\AddPluginCommandsPass;
use Drutiny\DependencyInjection\AddSourcesCachePass;
use Drutiny\DependencyInjection\InstalledPluginPass;
use Drutiny\DependencyInjection\PluginArgumentsPass;
use Drutiny\DependencyInjection\TagCollectionPass;
use Drutiny\DependencyInjection\TwigEvaluatorPass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Symfony\Component\EventDispatcher\GenericEvent;
use Drutiny\DependencyInjection\TwigLoaderPass;
use Drutiny\DependencyInjection\UseServiceAttributePass;
use Monolog\ErrorHandler;
use ProjectServiceContainer;
use Psr\EventDispatcher\StoppableEventInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\Finder\Finder;

class Kernel
{
    private const CONFIG_EXTS = '.{php,yaml,yml}';
    private const CONTAINER_SUFFIX = '.container.php';
    public  const CONTAINER_EXTENSIONS = '.container-extensions.json';
    private ContainerInterface $container;
    private bool $initialized = false;
    private array $compilers = [];
    private string $containerFilepath;
    private array $extensionFilepaths = [];

    public function __construct(private string $environment, private string $version)
    {
    }

    /**
     * Add a compiler pass to the container.
     */
    public function addCompilerPass(CompilerPassInterface $compiler, string $type = PassConfig::TYPE_BEFORE_OPTIMIZATION, int $priority = 0):self
    {
        if (isset($this->container)) {
            throw new \Exception("Cannot add compiler pass. Container already initialized.");
        }
        $this->compilers[] = [$compiler, $type, $priority];
        return $this;
    }

    /**
     * Get the dependency injection container.
     */
    public function getContainer():ContainerInterface
    {
        if (!isset($this->container)) {
            $this->container = $this->initializeContainer($this->environment != 'production');
        }
        return $this->container;
    }

    public function getApplication():Application
    {
        return $this->getContainer()->get(Application::class);
    }

    /**
     * Get the project directory of the drutiny project.
     */
    public function getProjectDir(): string
    {
        return DRUTINY_LIB;
    }

    /**
     * Initializes the service container.
     *
     * The cached version of the service container is used when fresh, otherwise the
     * container is built.
     */
    protected function initializeContainer($rebuild = false):ContainerInterface
    {
        // Any change to the config files will generate a new container build.
        $config_files = $this->findExtensionConfigFiles();
        $id = hash('md5', implode('-', array_keys($config_files)));

        $this->containerFilepath = $this->getProjectDir() . '/.cache/' . $id . self::CONTAINER_SUFFIX;
        is_dir(dirname($this->containerFilepath)) || mkdir(dirname($this->containerFilepath));

        if (file_exists($this->containerFilepath) && !$rebuild) {
            require_once $this->containerFilepath;
            $this->container = new ProjectServiceContainer();
        } else {
            $this->container = $this->buildContainer($config_files);
            $this->container->setParameter('environment', $this->environment);
            $this->container->setParameter('version', $this->version);
            $this->container->compile();

            $this->writePhpContainer();
        }
        $this->initialized = true;
        $this->cleanOldContainers();

        ErrorHandler::register($this->container->get(LoggerInterface::class)->withName('php'));

        return $this->container;
    }

    protected function writePhpContainer():void
    {
        // Ensure the Drutiny config directory is available.
        is_dir($this->container->getParameter('drutiny_config_dir')) or
        mkdir($this->container->getParameter('drutiny_config_dir'), 0744, true);

        if (is_writeable(dirname($this->containerFilepath))) {
            $dumper = new PhpDumper($this->container);
            file_put_contents($this->containerFilepath, $dumper->dump());
        }
    }

    /**
     * Clean up old generated containers.
     */
    protected function cleanOldContainers():void {
        $files = glob($this->getProjectDir() .'/.*'.self::CONTAINER_SUFFIX);
        foreach ($files as $file) {
            $age = time() - filemtime($file);
            // Delete files that are not the current container file and
            // are older than 30 days old.
            if ($file != $this->containerFilepath && $age > 2592000) {
                unlink($file);
            }
        }
    }

    /**
     * Clear the container and force a rebuild.
     */
    public function refresh():void
    {
        unlink($this->containerFilepath);
        unlink($this->getProjectDir() . '/' . self::CONTAINER_EXTENSIONS);
        $this->initialized = false;
        unset($this->container);
    }

    /**
       * Builds the service container.
       *
       * @return ContainerBuilder The compiled service container
       *
       * @throws \RuntimeException
       */
    protected function buildContainer(array $config_files):ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->addObjectResource($this);

        $loader = $this->getContainerLoader($container);

        $container->addCompilerPass(new RegisterListenersPass('event_dispatcher', 'kernel.event_listener', 'drutiny.event_subscriber'));
        $container->addCompilerPass(new TwigLoaderPass);
        $container->addCompilerPass(new AddConsoleCommandPass);
        $container->addCompilerPass(new TagCollectionPass('cache', 'cache.registry'));
        $container->addCompilerPass(new TagCollectionPass('http.middleware', 'http.middleware.registry'));
        $container->addCompilerPass(new TagCollectionPass('source.cache', 'source.cache.registry'));
        $container->addCompilerPass(new TagCollectionPass('format', 'format.registry'));
        $container->addCompilerPass(new TagCollectionPass('service', 'service.registry'));
        $container->addCompilerPass(new TagCollectionPass('target', 'target.registry'));
        $container->addCompilerPass(new TagCollectionPass('policy.source', 'policy.source.registry', AsSource::class));
        $container->addCompilerPass(new TagCollectionPass('profile.source', 'profile.source.registry', AsSource::class));
        $container->addCompilerPass(new TagCollectionPass('domain_list', 'domain_list.registry', Name::class));
        $container->addCompilerPass(new PluginArgumentsPass());
        $container->addCompilerPass(new TwigEvaluatorPass());
        $container->addCompilerPass(new InstalledPluginPass(), PassConfig::TYPE_OPTIMIZE);
        $container->addCompilerPass(new AddPluginCommandsPass(), PassConfig::TYPE_OPTIMIZE);
        $container->addCompilerPass(new AddSourcesCachePass());
        $container->addCompilerPass(new UseServiceAttributePass());

        foreach ($this->compilers as [$pass, $type, $priority]) {
            $container->addCompilerPass($pass, $type, $priority);
        }

        $container->setParameter('user_home_dir', getenv('HOME'));
        $container->setParameter('drutiny_core_dir', \dirname(__DIR__));
        $container->setParameter('project_dir', $this->getProjectDir());
        $container->setParameter('extension.files', $config_files);
        $container->setParameter('extension.dirs', $this->findExtensionDirectories());

        // Create config loader.
        foreach (array_reverse($config_files) as $config_file) {
            $loader->load($config_file);
        }

        return $container;
    }

    /**
     * Get the current working directory.
     */
    protected function getWorkingDirectory(): string
    {
        return getcwd();
    }

    protected function getHomeDirectory(): string
    {
        return getenv('HOME').'/.drutiny';
    }

    /**
     * Locate where all drutiny extensions are in the project.
     */
    protected function findExtensionDirectories():array
    {
        return array_values(array_unique(array_map(fn ($f) => dirname($f), $this->findExtensionConfigFiles())));
    }

    protected function findExtensionConfigFiles(): array
    {
        if (!empty($this->extensionFilepaths)) {
            return $this->extensionFilepaths;
        }

        // Load environment files as priority followed by env agnostic configuration.
        $names = ['drutiny.' . $this->environment, 'drutiny'];
        $types = ['.yml', '.yaml', '.php'];
        $filenames = [];
        foreach ($names as $name) {
            foreach ($types as $type) {
                $filenames[] = $name . $type;
            }
        }

        // Load any drutiny config files from the working directory as highest priority, then the home directory.
        // These are not cached because they can change based on install environment. But the config file locations
        // are fast to check so caching wouldn't have a large impact anyway.
        $files = [];
        foreach ([$this->getWorkingDirectory(), $this->getHomeDirectory(), $this->getProjectDir()] as $directory) {
            $files = array_merge($files, array_filter(
                array_map(
                    fn ($filename) => "$directory/$filename", 
                    $filenames
                ), 
            'file_exists'));
        }

        $cache_file = $this->getProjectDir() . '/' . self::CONTAINER_EXTENSIONS;
        if (file_exists($cache_file)) {
            $cache_files = json_decode(file_get_contents($cache_file), true);
        }
        else {
            $finder = new Finder;
            $finder->in($this->getProjectDir().'/vendor')->exclude('tests')->files()->name('drutiny'.self::CONFIG_EXTS);

            $cache_files = [];
            foreach ($finder as $file) {
                $cache_files[] = implode(DIRECTORY_SEPARATOR, array_filter([$file->getRelativePath(), $file->getFilename()]));
            }
            file_put_contents($cache_file, json_encode($cache_files));
        }
        $files = array_merge($files, array_map(fn($p) => $this->getProjectDir() . "/vendor/$p", $cache_files));

        $hashes = array_map(fn ($f) => substr(hash('md5', file_get_contents($f)), 0, 8), $files);
        
        // Ensure array_unqiue choses latest duplicate as order priority.
        $this->extensionFilepaths = array_combine($hashes, $files);

        return $this->extensionFilepaths;
    }

    /**
       * Returns a loader for the container.
       *
       * @return DelegatingLoader The loader
       */
    protected function getContainerLoader(ContainerInterface $container):LoaderInterface
    {
        $locator = new FileLocator([$this->getProjectDir()]);
        $resolver = new LoaderResolver([
            new YamlFileLoader($container, $locator),
            new PhpFileLoader($container, $locator),
        ]);

        return new DelegatingLoader($resolver);
    }

    /**
     * Use the EventDispatcher to dispatch an event.
     */
    public function dispatchEvent($subject = null, array $arguments = []):StoppableEventInterface
    {
        return $this->getContainer()
          ->get('event_dispatcher')
          ->dispatch(new GenericEvent($subject, $arguments), $subject);
    }
}
