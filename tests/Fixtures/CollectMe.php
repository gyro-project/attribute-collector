<?php

namespace Gyro\Component\AttributeCollector\Tests\Fixtures;

use Attribute;

/**
 * The attribute the collection tests look for.
 *
 * Declared here rather than reusing `Gyro\Framework\Attribute\GyroExtension` so the package can be
 * tested without depending on gyro/framework — which depends on this package in turn.
 *
 * It deliberately lives outside `Fixtures/VendorPackage`, the only directory the tests ever scan:
 * upstream discards files that look like an attribute declaration.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class CollectMe
{
    public function __construct(
        public string $name = '',
    ) {
    }
}
