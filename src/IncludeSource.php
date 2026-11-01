<?php

namespace Gyro\Component\AttributeCollector;

/**
 * Why a package's paths ended up being scanned.
 *
 * This decides how loudly a missing path is reported: paths the root package asked for by name are
 * a configuration mistake worth a warning, whereas a dependency describing paths that its dist
 * does not ship is none of the user's business. The latter is not hypothetical —
 * olvlvl/composer-attribute-collector itself declares `include: ["tests"]` and ships no `tests`.
 */
enum IncludeSource: string
{
    /**
     * An explicit `vendor.packages` entry in the root package.
     */
    case Configured = 'configured';

    /**
     * The package's own `extra.composer-attribute-collector.include`.
     */
    case Declared = 'declared';

    /**
     * The package's `type` is listed in `vendor.types`.
     */
    case PackageType = 'package-type';

    /**
     * `include-vendor-dependencies` is enabled.
     */
    case AllDependencies = 'all-dependencies';

    public function isExplicit(): bool
    {
        return $this === self::Configured;
    }
}
