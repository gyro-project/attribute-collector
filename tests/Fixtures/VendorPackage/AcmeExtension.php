<?php

namespace Gyro\Component\AttributeCollector\Tests\Fixtures\VendorPackage;

use Gyro\Component\AttributeCollector\Tests\Fixtures\CollectMe;

/**
 * Stands in for a class shipped by a third party Composer package.
 */
#[CollectMe(name: 'acme')]
class AcmeExtension
{
}
