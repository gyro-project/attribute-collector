<?php

namespace Gyro\Component\AttributeCollector;

/**
 * The outcome of {@see PackageIncludeResolver::resolve()}.
 *
 * Diagnostics are returned rather than logged so that the resolver stays free of any I/O
 * dependency; {@see Dumper} writes them to Composer's output.
 *
 * @readonly
 */
final class IncludeResolution
{
    /**
     * @param list<non-empty-string> $paths
     *     Absolute paths to scan, de-duplicated, every one of them existing on disk.
     * @param list<string>           $debug
     *     Which package contributed which path.
     * @param list<string>           $warnings
     *     Paths that were asked for but could not be scanned.
     */
    public function __construct(
        public array $paths = [],
        public array $debug = [],
        public array $warnings = [],
    ) {
    }
}
