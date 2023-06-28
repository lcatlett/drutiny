<?php

namespace Drutiny\Policy;

use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use Drutiny\Attribute\Version;
use Drutiny\Policy\Compatibility\IncompatibleVersionException;
use Drutiny\Policy\Compatibility\NoRuntimeVersionException;
use Drutiny\Policy\Compatibility\RuntimeOutdatedException;
use ReflectionClass;
use ReflectionException;

class AuditClass {
    public readonly Version|null $version;

    public function __construct(
        public readonly string $name,
        string|Version|null $version = null
    )
    {
        if (is_string($version)) {
            $version = new Version($version);
        }
        $this->version = $version;
    }

    /**
     * Create a new AuditClass instance from an given class name.
     */
    public static function fromClass(string $class_name): static {
        try {
            $reflection = new ReflectionClass($class_name);
            $attributes = $reflection->getAttributes(Version::class);
    
            foreach ($attributes as $attribute) {
                return new static($class_name, $attribute->newInstance());
            }
        }
        catch (ReflectionException $e) {
            // Class doesn't exist so can't pull local version information.
        }
       
        return new static($class_name);
    }

    public static function fromBuilt(string $requirement): static {
        list($name, $version) = explode(':', $requirement, 2);
        // When building from a requirement string, the actually version doesn't matter.
        return new static($name, new Version($version));
    }

    public function export(): array|string {
        if ($this->version == null) {
            return $this->name;
        }
        $vars = get_object_vars($this);
        $vars['version'] = $this->version->version;
        return $vars;
    }

    public function asBuilt(): string {
        return sprintf('%s:%s', $this->name, $this->version?->version ?? '');
    }

    public function isCompatible(): bool {
        // If version is not available then this is not a real compatibility check.
        // For backwards compatibility we'll return "true".
        if ($this->version === null) {
            return true;
        }

        $runtime = static::fromClass($this->name);

        // If we require a version but a version is not present in the runtime
        // then we are not compatible.
        if ($runtime->version === null) {
            throw new NoRuntimeVersionException($this, "Runtime {$runtime->name} requires version {$this->version->compatibilty} but none is specified. An upgrade is required.");
        }

        if ($runtime->version->compatibleWith($this->version->version)) {
            return true;
        }
        if (Comparator::greaterThan($this->version->version, $runtime->version->version)) {
            throw new RuntimeOutdatedException($this, "Runtime {$runtime->name} requires version {$this->version->compatibilty} but {$runtime->version->version} is specified. An upgrade is required.");
        }
        throw new IncompatibleVersionException($this, "Runtime {$runtime->name} requires version {$runtime->version->compatibilty} but {$this->version->version} is specified in your policy. This policy is too old for your runtime.");
    }
}