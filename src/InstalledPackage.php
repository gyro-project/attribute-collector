<?php

namespace Gyro\Component\AttributeCollector;

use Composer\Composer;
use Composer\Package\PackageInterface;

use function is_array;
use function is_string;
use function realpath;

/**
 * The subset of an installed package the resolver needs, detached from Composer's object graph so
 * that {@see PackageIncludeResolver} stays a pure function of plain data.
 *
 * @readonly
 */
final class InstalledPackage
{
    /**
     * Autoload sections that do not describe directories to scan.
     */
    private const NON_SCANNABLE_AUTOLOAD = [
        'files' => true,
        'exclude-from-classmap' => true,
    ];

    /**
     * @param non-empty-string      $name
     * @param string                $type
     *     The Composer package type, e.g. `library` or `gyro-extension`.
     * @param non-empty-string|null $installPath
     *     Absolute install path, or null for packages that are not installed on disk
     *     (metapackages).
     * @param array<string, mixed>  $extra
     * @param array<string, mixed>  $autoload
     *     The package's production autoload section.
     */
    public function __construct(
        public string $name,
        public string $type,
        public ?string $installPath,
        public array $extra = [],
        public array $autoload = [],
    ) {
    }

    /** @return list<self> */
    public static function allFrom(Composer $composer): array
    {
        $installationManager = $composer->getInstallationManager();
        $packages = [];

        foreach ($composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages() as $package) {
            $packages[] = self::fromPackage($package, $installationManager->getInstallPath($package));
        }

        return $packages;
    }

    /**
     * @param string|null $installPath
     *     As reported by Composer; relative paths and symlinks are resolved.
     */
    public static function fromPackage(PackageInterface $package, ?string $installPath): self
    {
        $name = $package->getName();
        \assert(\is_string($name) && $name !== '');

        if ($installPath !== null && $installPath !== '') {
            $resolved = realpath($installPath);
            $installPath = $resolved === false ? $installPath : $resolved;
            \assert((\is_string($installPath) && $installPath !== '') || $installPath === null);
        } else {
            $installPath = null;
        }

        return new self(
            $name,
            $package->getType(),
            $installPath,
            $package->getExtra(),
            $package->getAutoload(),
        );
    }

    /**
     * The paths the package asks for itself through `extra.composer-attribute-collector.include`.
     *
     * @return list<string>
     */
    public function declaredIncludePaths(): array
    {
        $config = $this->extra[Options::EXTRA] ?? null;

        if (!is_array($config)) {
            return [];
        }

        return self::toPathList($config[Options::EXTRA_INCLUDE] ?? null);
    }

    /**
     * The directories and files the package maps through `autoload`.
     *
     * @return list<string>
     */
    public function autoloadPaths(): array
    {
        $paths = [];

        foreach ($this->autoload as $type => $map) {
            if (isset(self::NON_SCANNABLE_AUTOLOAD[$type]) || !is_array($map)) {
                continue;
            }

            foreach ($map as $entry) {
                foreach (self::toPathList($entry) as $path) {
                    $paths[] = $path;
                }
            }
        }

        return $paths;
    }

    /**
     * A PSR-4 prefix may map to a single path or to a list of paths; a classmap entry is a plain
     * path.
     *
     * @return list<string>
     */
    private static function toPathList(mixed $value): array
    {
        if (is_string($value)) {
            return $value === '' ? [] : [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        $paths = [];

        foreach ($value as $path) {
            if (is_string($path) && $path !== '') {
                $paths[] = $path;
            }
        }

        return $paths;
    }
}
