<?php

namespace Gyro\Component\AttributeCollector;

use function file_exists;
use function in_array;
use function realpath;
use function rtrim;

use const DIRECTORY_SEPARATOR;

/**
 * Works out which directories of the installed packages should be handed to the upstream collector.
 *
 * For every installed package the first matching rule wins:
 *
 * 1. an explicit `vendor.packages` entry;
 * 2. the package's own `extra.composer-attribute-collector.include`;
 * 3. a `type` listed in `vendor.types`, scanning the package's autoload paths;
 * 4. `include-vendor-dependencies: true`, scanning every package's autoload paths;
 * 5. otherwise the package is skipped.
 *
 * `vendor.exclude-packages` wins over all of them.
 *
 * @readonly
 */
final class PackageIncludeResolver
{
    public function __construct(
        private Options $options,
    ) {
    }

    /** @param list<InstalledPackage> $packages */
    public function resolve(array $packages): IncludeResolution
    {
        $paths = [];
        $seen = [];
        $debug = [];
        $warnings = [];

        foreach ($packages as $package) {
            if ($this->options->isExcluded($package->name)) {
                $debug[] = "Skipping '{$package->name}': excluded by configuration";

                continue;
            }

            $candidate = $this->candidateFor($package);

            if ($candidate === null) {
                continue;
            }

            [$relativePaths, $source] = $candidate;

            if ($package->installPath === null) {
                $message = "Cannot scan '{$package->name}': the package is not installed on disk";

                if ($source->isExplicit()) {
                    $warnings[] = $message;
                } else {
                    $debug[] = $message;
                }

                continue;
            }

            $base = rtrim($package->installPath, DIRECTORY_SEPARATOR);

            foreach ($relativePaths as $relativePath) {
                $path = $base . DIRECTORY_SEPARATOR . $relativePath;

                // Upstream throws from `ClassMapGenerator::scanPaths()` on a path that is neither a
                // file nor a directory, and asserts that `filemtime()` returned an int while
                // deciding whether its class map is stale.
                if (!file_exists($path)) {
                    $message = "Cannot scan '$path' of '{$package->name}': no such file or directory";

                    if ($source->isExplicit()) {
                        $warnings[] = $message;
                    } else {
                        $debug[] = $message;
                    }

                    continue;
                }

                // Path repositories are symlinked into the vendor directory, so the same directory
                // can be reached under more than one name. Upstream keys its class map and its
                // mtime bookkeeping by path, so a duplicate would be scanned twice.
                $path = realpath($path) ?: $path;

                if (isset($seen[$path])) {
                    continue;
                }

                $seen[$path] = true;
                $paths[] = $path;
                $debug[] = "Scanning '$path' of '{$package->name}' ({$source->value})";
            }
        }

        return new IncludeResolution($paths, $debug, $warnings);
    }

    /**
     * @return array{non-empty-list<string>, IncludeSource}|null
     *     Paths relative to the package's install path, or null when the package is not of
     *     interest.
     */
    private function candidateFor(InstalledPackage $package): ?array
    {
        $configured = $this->options->packagePaths[$package->name] ?? [];

        if ($configured !== []) {
            return [$configured, IncludeSource::Configured];
        }

        $declared = $package->declaredIncludePaths();

        if ($declared !== []) {
            return [$declared, IncludeSource::Declared];
        }

        $byType = in_array($package->type, $this->options->packageTypes, true);

        if (!$byType && !$this->options->includeVendorDependencies) {
            return null;
        }

        $autoloadPaths = $package->autoloadPaths();

        if ($autoloadPaths === []) {
            return null;
        }

        return [
            $autoloadPaths,
            $byType ? IncludeSource::PackageType : IncludeSource::AllDependencies,
        ];
    }
}
