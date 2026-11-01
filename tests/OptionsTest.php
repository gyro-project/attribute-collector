<?php

namespace Gyro\Component\AttributeCollector\Tests;

use Gyro\Component\AttributeCollector\Options;
use PHPUnit\Framework\TestCase;

class OptionsTest extends TestCase
{
    public function testDefaultsAreOptIn(): void
    {
        $options = Options::fromExtra([]);

        $this->assertFalse($options->includeVendorDependencies);
        $this->assertSame([], $options->packagePaths);
        $this->assertSame([], $options->packageTypes);
        $this->assertSame([], $options->excludedPackages);
    }

    public function testFullConfiguration(): void
    {
        $options = Options::fromExtra([
            Options::EXTRA => [
                Options::EXTRA_INCLUDE => ['packages/framework/src'],
                Options::INCLUDE_VENDOR_DEPENDENCIES => true,
                Options::VENDOR => [
                    Options::VENDOR_PACKAGES => ['acme/foo' => ['src', '/lib']],
                    Options::VENDOR_TYPES => ['gyro-extension'],
                    Options::VENDOR_EXCLUDE_PACKAGES => ['phpunit/phpunit'],
                ],
            ],
        ]);

        $this->assertTrue($options->includeVendorDependencies);
        $this->assertSame(['acme/foo' => ['src', 'lib']], $options->packagePaths);
        $this->assertSame(['gyro-extension'], $options->packageTypes);
        $this->assertTrue($options->isExcluded('phpunit/phpunit'));
        $this->assertFalse($options->isExcluded('acme/foo'));
    }

    public function testASinglePathMayBeGivenAsString(): void
    {
        $options = Options::fromExtra([
            Options::EXTRA => [
                Options::VENDOR => [Options::VENDOR_PACKAGES => ['acme/foo' => 'src']],
            ],
        ]);

        $this->assertSame(['acme/foo' => ['src']], $options->packagePaths);
    }

    /**
     * The `extra` section is user-authored JSON, so every branch has to survive the wrong shape
     * rather than fail the whole `composer install`.
     *
     * @param array<string, mixed> $extra
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideMalformedExtra')]
    public function testMalformedConfigurationFallsBackToDefaults(array $extra): void
    {
        $options = Options::fromExtra($extra);

        $this->assertFalse($options->includeVendorDependencies);
        $this->assertSame([], $options->packagePaths);
        $this->assertSame([], $options->packageTypes);
        $this->assertSame([], $options->excludedPackages);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function provideMalformedExtra(): iterable
    {
        yield 'section is a string' => [[Options::EXTRA => 'yes']];
        yield 'vendor is a string' => [[Options::EXTRA => [Options::VENDOR => 'yes']]];
        yield 'packages is a list' => [
            [Options::EXTRA => [Options::VENDOR => [Options::VENDOR_PACKAGES => ['acme/foo']]]],
        ];

        yield 'types is a map' => [
            [Options::EXTRA => [Options::VENDOR => [Options::VENDOR_TYPES => ['a' => 'b']]]],
        ];

        yield 'types contains non-strings' => [
            [Options::EXTRA => [Options::VENDOR => [Options::VENDOR_TYPES => [1, null, '']]]],
        ];

        yield 'package paths are empty' => [
            [Options::EXTRA => [Options::VENDOR => [Options::VENDOR_PACKAGES => ['acme/foo' => ['']]]]],
        ];
    }
}
