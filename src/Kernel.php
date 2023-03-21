<?php

namespace Drutiny;

use Drutiny\Attribute\AsSource;
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
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Symfony\Component\EventDispatcher\GenericEvent;
use Drutiny\DependencyInjection\TwigLoaderPass;
use Drutiny\DependencyInjection\UseServiceAttributePass;
use Psr\EventDispatcher\StoppableEventInterface;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\Finder\Finder;

class Kernel
{
    private const CONFIG_EXTS = '.{php,yaml,yml}';
    private const CACHED_CONTAINER = 'local.container.php';
    private ContainerInterface $container;
    private array $loadingPaths = [];
    private bool $initialized = false;
    private array $compilers = [];

    public function __construct(private string $environment, private string $version)
    {
        // If Drutiny is located elsewhere, lets load her in.
        if ($this->getProjectDir() != $this->getWorkingDirectory()) {
            $this->addServicePath($this->getProjectDir());
        }
        $this->addServicePath($this->getProjectDir().'/vendor/*/*');
    }

    /**
     * Add a service path to the loading paths array.
     */
    public function addServicePath($path)
    {
        if ($this->initialized) {
            throw new \RuntimeException("Cannot add $path as service path. Container already initialized.");
        }
        $this->loadingPaths[] = $path;
        return $this;
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
            return $this->initializeContainer();
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
    protected function initializeContainer():ContainerInterface
    {
        $file = DRUTINY_LIB . '/' . self::CACHED_CONTAINER;
        if (file_exists($file)) {
            require_once $file;
            $this->container = new ProjectServiceContainer();
        } else {
            $this->container = $this->buildContainer();
            $this->container->setParameter('environment', $this->environment);
            $this->container->setParameter('version', $this->version);
            $this->container->compile();

            // Ensure the Drutiny config directory is available.
            is_dir($this->container->getParameter('drutiny_config_dir')) or
          mkdir($this->container->getParameter('drutiny_config_dir'), 0744, true);

        // TODO: cache container. Need workaround for Twig.
        //   if (is_writeable(dirname($file))) {
        //       $dumper = new PhpDumper($this->container);
        //       file_put_contents($file, $dumper->dump());
        //   }
        }
        $this->initialized = true;
        return $this->container;
    }

    /**
       * Builds the service container.
       *
       * @return ContainerBuilder The compiled service container
       *
       * @throws \RuntimeException
       */
    protected function buildContainer():ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->addObjectResource($this);

        $loader = $this->getContainerLoader($container);

        $container->addCompilerPass(new RegisterListenersPass('event_dispatcher', 'kernel.event_listener', 'drutiny.event_subscriber'));
        $container->addCompilerPass(new TwigLoaderPass);
        $container->addCompilerPass(new AddConsoleCommandPass);
        $container->addCompilerPass(new TagCollectionPass('cache', 'cache.registry'));
        $container->addCompilerPass(new TagCollectionPass('source.cache', 'source.cache.registry'));
        $container->addCompilerPass(new TagCollectionPass('format', 'format.registry'));
        $container->addCompilerPass(new TagCollectionPass('service', 'service.registry'));
        $container->addCompilerPass(new TagCollectionPass('target', 'target.registry'));
        $container->addCompilerPass(new TagCollectionPass('policy.source', 'policy.source.registry', AsSource::class));
        $container->addCompilerPass(new TagCollectionPass('profile.source', 'profile.source.registry', AsSource::class));
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
        $container->setParameter('extension.dirs', $this->findExtensionDirectories());

        // Create config loader.
        $load = function (...$args) use ($loader) {
            $args[] = '{drutiny}'.self::CONFIG_EXTS;
            $loading_path = implode('/', $args);
            $loader->load($loading_path, 'glob');
        };

        foreach ($this->loadingPaths as $path) {
            $load($path);
        }

        // Load any available global configuration. This should really use
        // user_home_dir but since the container isn't compiled we can't.
        if (file_exists(getenv('HOME').'/.drutiny')) {
            $load(getenv('HOME').'/.drutiny');
        }

        // If we're in a different working directory (e.g. executing from phar)
        // then there may be one last level of config we should inherit from.
        $load($this->getWorkingDirectory());

        return $container;
    }

    /**
     * Get the current working directory.
     */
    protected function getWorkingDirectory(): string
    {
        return getcwd();
    }

    /**
     * Locate where all drutiny extensions are in the project.
     */
    protected function findExtensionDirectories():array
    {
        $finder = new Finder;
        $finder
            ->in($this->getProjectDir())
            ->files()
            ->name('drutiny.{yaml,yml,php}');
            
        $dirs = [];
        foreach ($finder as $file) {
            $dir = $file->getRelativePath();
            $dirs[] = $dir ?: '.';
        }
        return array_values(array_unique($dirs));
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
            new GlobFileLoader($container, $locator),
            new DirectoryLoader($container, $locator),
            new ClosureLoader($container),
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
