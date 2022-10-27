<?php

namespace Drutiny;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Psr\EventDispatcher\StoppableEventInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Drutiny\DependencyInjection\TwigLoaderPass;

class Kernel
{
    private const CONFIG_EXTS = '.{php,yaml,yml}';
    private const CACHED_CONTAINER = 'local.container.php';
    private $container;
    private $environment;
    private $loadingPaths = [];
    private $initialized = false;

    public function __construct($environment)
    {
        $this->environment = $environment;
        $this->addServicePath($this->getProjectDir());
        $this->addServicePath('./vendor/*/*');
    }

    public function addServicePath($path)
    {
        if ($this->initialized) {
            throw new \RuntimeException("Cannot add $path as service path. Container already initialized.");
        }
        $this->loadingPaths[] = $path;
        return $this;
    }

    public function getContainer()
    {
        if (!$this->container) {
            return $this->initializeContainer();
        }
        return $this->container;
    }

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
    protected function initializeContainer()
    {
        $file = DRUTINY_LIB . '/' . self::CACHED_CONTAINER;
        if (file_exists($file)) {
            require_once $file;
            $this->container = new ProjectServiceContainer();
        } else {
            $this->container = $this->buildContainer();
            $this->container->compile();

            // Ensure the Drutiny config directory is available.
            is_dir($this->container->getParameter('drutiny_config_dir')) or
          mkdir($this->container->getParameter('drutiny_config_dir'), 0744, true);

            // TODO: cache container. Need workaround for Twig.
          // if (is_writeable(dirname($file))) {
          //     $dumper = new PhpDumper($this->container);
          //     file_put_contents($file, $dumper->dump());
          // }
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
    protected function buildContainer()
    {
        $container = new ContainerBuilder();
        $container->addObjectResource($this);

        $loader = $this->getContainerLoader($container);

        $container->addCompilerPass(new RegisterListenersPass('event_dispatcher', 'kernel.event_listener', 'drutiny.event_subscriber'));
        $container->addCompilerPass(new TwigLoaderPass());

        $container->setParameter('user_home_dir', getenv('HOME'));
        $container->setParameter('drutiny_core_dir', \dirname(__DIR__));
        $container->setParameter('project_dir', $this->getProjectDir());

        // Remove duplicates.
        $idx = array_search($this->getProjectDir(), $this->loadingPaths);
        if ($idx !== false) {
            unset($this->loadingPaths[$idx]);
        }

        // Create config loader.
        $load = function () use ($loader) {
            $args = func_get_args();
            $args[] = '{drutiny}'.self::CONFIG_EXTS;
            $loading_path = implode('/', $args);
            $loader->load($loading_path, 'glob');
        };

        foreach ($this->loadingPaths as $path) {
            $load($this->getProjectDir(), $path);
        }

        // Load project level config last as it should override all others.
        $load($this->getProjectDir());

        // Load any available global configuration. This should really use
        // user_home_dir but since the container isn't compiled we can't.
        if (file_exists(getenv('HOME').'/.drutiny')) {
            $load(getenv('HOME').'/.drutiny');
        }

        // If we're in a different working directory (e.g. executing from phar)
        // then there may be one last level of config we should inherit from.
        if ($this->getProjectDir() != getcwd()) {
            $load(getcwd());
        }

        return $container;
    }

    /**
       * Returns a loader for the container.
       *
       * @return DelegatingLoader The loader
       */
    protected function getContainerLoader(ContainerInterface $container)
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
    public function dispatchEvent($subject = null, array $arguments = [])
    {
        return $this->getContainer()
          ->get('event_dispatcher')
          ->dispatch(new GenericEvent($subject, $arguments), $subject);
    }
}
