<?php

namespace Gyro\Component\AttributeCollector;

use function array_filter;
use function array_is_list;
use function array_map;
use function array_values;
use function is_array;
use function is_string;
use function ltrim;

/**
 * The Gyro additions to the `extra.composer-attribute-collector` section of the root package.
 *
 * The upstream `include` and `exclude` keys are intentionally not represented here: they keep their
 * upstream meaning and are resolved by `Config::from()`.
 *
 * @readonly
 */
final class Options
{
    public const EXTRA = 'composer-attribute-collector';
    public const EXTRA_INCLUDE = 'include';
    public const INCLUDE_VENDOR_DEPENDENCIES = 'include-vendor-dependencies';
    public const VENDOR = 'vendor';
    public const VENDOR_PACKAGES = 'packages';
    public const VENDOR_TYPES = 'types';
    public const VENDOR_EXCLUDE_PACKAGES = 'exclude-packages';

    /**
     * @param bool                                  $includeVendorDependencies
     *     Whether every installed package should be scanned, even when it does not opt in.
     * @param array<non-empty-string, list<string>> $packagePaths
     *     Where _key_ is a package name and _value_ paths relative to its install path.
     * @param list<non-empty-string>                $packageTypes
     *     Composer package types that should be scanned through their autoload paths.
     * @param array<non-empty-string, true>         $excludedPackages
     *     Where _key_ is a package name that must never be scanned.
     */
    public function __construct(
        public bool $includeVendorDependencies = false,
        public array $packagePaths = [],
        public array $packageTypes = [],
        public array $excludedPackages = [],
    ) {
    }

    /**
     * @param array<string, mixed> $extra
     *     The `extra` section of the root package.
     */
    public static function fromExtra(array $extra): self
    {
        $config = $extra[self::EXTRA] ?? [];

        if (!is_array($config)) {
            return new self();
        }

        $vendor = $config[self::VENDOR] ?? [];

        if (!is_array($vendor)) {
            $vendor = [];
        }

        return new self(
            includeVendorDependencies: (bool) ($config[self::INCLUDE_VENDOR_DEPENDENCIES] ?? false),
            packagePaths: self::normalizePackagePaths($vendor[self::VENDOR_PACKAGES] ?? []),
            packageTypes: self::normalizeNames($vendor[self::VENDOR_TYPES] ?? []),
            excludedPackages: self::index(self::normalizeNames($vendor[self::VENDOR_EXCLUDE_PACKAGES] ?? [])),
        );
    }

    public function isExcluded(string $packageName): bool
    {
        return isset($this->excludedPackages[$packageName]);
    }

    /** @return array<non-empty-string, list<string>> */
    private static function normalizePackagePaths(mixed $packages): array
    {
        if (!is_array($packages)) {
            return [];
        }

        $normalized = [];

        foreach ($packages as $name => $paths) {
            if (!is_string($name) || $name === '') {
                continue;
            }

            $paths = array_values(array_filter(
                array_map(
                    static fn (mixed $path): string => is_string($path) ? ltrim($path, '/') : '',
                    is_array($paths) ? $paths : [$paths],
                ),
                static fn (string $path): bool => $path !== '',
            ));

            if ($paths === []) {
                continue;
            }

            $normalized[$name] = $paths;
        }

        return $normalized;
    }

    /** @return list<non-empty-string> */
    private static function normalizeNames(mixed $names): array
    {
        if (!is_array($names) || !array_is_list($names)) {
            return [];
        }

        /** @var list<non-empty-string> $filtered */
        $filtered = array_values(array_filter(
            $names,
            static fn (mixed $name): bool => is_string($name) && $name !== '',
        ));

        return $filtered;
    }

    /**
     * @param list<non-empty-string> $names
     *
     * @return array<non-empty-string, true>
     */
    private static function index(array $names): array
    {
        $indexed = [];

        foreach ($names as $name) {
            $indexed[$name] = true;
        }

        return $indexed;
    }
}
