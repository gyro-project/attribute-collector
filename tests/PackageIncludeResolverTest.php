<?php

namespace Gyro\Component\AttributeCollector\Tests;

use Gyro\Component\AttributeCollector\InstalledPackage;
use Gyro\Component\AttributeCollector\Options;
use Gyro\Component\AttributeCollector\PackageIncludeResolver;
use PHPUnit\Framework\TestCase;

class PackageIncludeResolverTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = realpath(__DIR__ . '/Fixtures') ?: self::fail('Missing fixtures directory');
    }

    public function testPackagesAreSkippedByDefault(): void
    {
        $resolution = $this->resolve(new Options(), [$this->package('acme/foo')]);

        $this->assertSame([], $resolution->paths);
        $this->assertSame([], $resolution->warnings);
    }

    public function testPackageOptsItselfInThroughItsOwnExtra(): void
    {
        $package = $this->package('acme/foo', extra: [
            Options::EXTRA => [Options::EXTRA_INCLUDE => ['VendorPackage']],
        ]);

        $resolution = $this->resolve(new Options(), [$package]);

        $this->assertSame([$this->root . '/VendorPackage'], $resolution->paths);
    }

    public function testRootConfigurationOverridesWhatThePackageDeclares(): void
    {
        $package = $this->package('acme/foo', extra: [
            Options::EXTRA => [Options::EXTRA_INCLUDE => ['DoesNotExist']],
        ]);
        $options = new Options(packagePaths: ['acme/foo' => ['VendorPackage']]);

        $resolution = $this->resolve($options, [$package]);

        $this->assertSame([$this->root . '/VendorPackage'], $resolution->paths);
    }

    public function testPackageTypeOptsInThroughAutoloadPaths(): void
    {
        $package = $this->package('acme/foo', type: 'gyro-extension', autoload: [
            'psr-4' => ['Acme\\Foo\\' => 'VendorPackage'],
        ]);

        $resolution = $this->resolve(new Options(packageTypes: ['gyro-extension']), [$package]);

        $this->assertSame([$this->root . '/VendorPackage'], $resolution->paths);
    }

    public function testPackageTypeDoesNotOptInOtherTypes(): void
    {
        $package = $this->package('acme/foo', autoload: [
            'psr-4' => ['Acme\\Foo\\' => 'VendorPackage'],
        ]);

        $resolution = $this->resolve(new Options(packageTypes: ['gyro-extension']), [$package]);

        $this->assertSame([], $resolution->paths);
    }

    public function testIncludeVendorDependenciesScansEveryPackage(): void
    {
        $package = $this->package('acme/foo', autoload: [
            'psr-4' => ['Acme\\Foo\\' => 'VendorPackage'],
        ]);

        $resolution = $this->resolve(new Options(includeVendorDependencies: true), [$package]);

        $this->assertSame([$this->root . '/VendorPackage'], $resolution->paths);
    }

    /**
     * `files` entries are files to require, not trees to scan, and `exclude-from-classmap` is the
     * opposite of an include.
     */
    public function testOnlyScannableAutoloadSectionsAreUsed(): void
    {
        $package = $this->package('acme/foo', autoload: [
            'psr-4' => ['Acme\\Foo\\' => ['VendorPackage']],
            'psr-0' => ['Acme_' => 'VendorPackage'],
            'classmap' => ['VendorPackage/AcmeExtension.php'],
            'files' => ['VendorPackage/AcmeExtension.php'],
            'exclude-from-classmap' => ['VendorPackage'],
        ]);

        $resolution = $this->resolve(new Options(includeVendorDependencies: true), [$package]);

        $this->assertSame([
            $this->root . '/VendorPackage',
            $this->root . '/VendorPackage/AcmeExtension.php',
        ], $resolution->paths);
    }

    public function testExcludedPackagesAreNeverScanned(): void
    {
        $package = $this->package('acme/foo', extra: [
            Options::EXTRA => [Options::EXTRA_INCLUDE => ['VendorPackage']],
        ]);
        $options = new Options(excludedPackages: ['acme/foo' => true]);

        $resolution = $this->resolve($options, [$package]);

        $this->assertSame([], $resolution->paths);
        $this->assertContains("Skipping 'acme/foo': excluded by configuration", $resolution->debug);
    }

    /**
     * Upstream's `ClassMapGenerator::scanPaths()` throws on a path that is neither a file nor a
     * directory, so a missing path has to be dropped before it reaches the collector.
     */
    public function testMissingPathsAreDropped(): void
    {
        $package = $this->package('acme/foo', extra: [
            Options::EXTRA => [Options::EXTRA_INCLUDE => ['DoesNotExist', 'VendorPackage']],
        ]);

        $resolution = $this->resolve(new Options(), [$package]);

        $this->assertSame([$this->root . '/VendorPackage'], $resolution->paths);
    }

    /**
     * A dependency describing paths its dist does not ship is not the user's problem — upstream
     * itself declares `include: ["tests"]` and ships no `tests` directory.
     */
    public function testAMissingDeclaredPathIsOnlyReportedAsDebug(): void
    {
        $package = $this->package('acme/foo', extra: [
            Options::EXTRA => [Options::EXTRA_INCLUDE => ['DoesNotExist']],
        ]);

        $resolution = $this->resolve(new Options(), [$package]);

        $this->assertSame([], $resolution->warnings);
        $this->assertCount(1, $resolution->debug);
    }

    public function testAMissingConfiguredPathIsReportedAsWarning(): void
    {
        $options = new Options(packagePaths: ['acme/foo' => ['DoesNotExist']]);

        $resolution = $this->resolve($options, [$this->package('acme/foo')]);

        $this->assertSame([], $resolution->paths);
        $this->assertCount(1, $resolution->warnings);
        $this->assertStringContainsString('no such file or directory', $resolution->warnings[0]);
    }

    /**
     * Path repositories are symlinked into the vendor directory, so the same tree is reachable
     * under more than one name. Upstream keys its class map by path and would scan it twice.
     */
    public function testTheSameDirectoryIsScannedOnce(): void
    {
        $packages = [
            $this->package('acme/foo', extra: [
                Options::EXTRA => [Options::EXTRA_INCLUDE => ['VendorPackage']],
            ]),
            $this->package('acme/bar', extra: [
                Options::EXTRA => [Options::EXTRA_INCLUDE => ['VendorPackage/../VendorPackage']],
            ]),
        ];

        $resolution = $this->resolve(new Options(), $packages);

        $this->assertSame([$this->root . '/VendorPackage'], $resolution->paths);
    }

    public function testMetapackagesAreReportedInsteadOfCrashing(): void
    {
        $package = new InstalledPackage(
            'acme/meta',
            'metapackage',
            null,
            [Options::EXTRA => [Options::EXTRA_INCLUDE => ['src']]],
        );

        $resolution = $this->resolve(new Options(), [$package]);

        $this->assertSame([], $resolution->paths);
        $this->assertSame([], $resolution->warnings);
        $this->assertStringContainsString('not installed on disk', $resolution->debug[0]);
    }

    public function testTrailingSeparatorsInInstallPathsDoNotDoubleUp(): void
    {
        $package = new InstalledPackage(
            'acme/foo',
            'library',
            $this->root . '/',
            [Options::EXTRA => [Options::EXTRA_INCLUDE => ['VendorPackage']]],
        );

        $resolution = $this->resolve(new Options(), [$package]);

        $this->assertSame([$this->root . '/VendorPackage'], $resolution->paths);
    }

    /** @param list<InstalledPackage> $packages */
    private function resolve(Options $options, array $packages): \Gyro\Component\AttributeCollector\IncludeResolution
    {
        return (new PackageIncludeResolver($options))->resolve($packages);
    }

    /**
     * @param array<string, mixed> $extra
     * @param array<string, mixed> $autoload
     */
    private function package(
        string $name,
        string $type = 'library',
        array $extra = [],
        array $autoload = [],
    ): InstalledPackage {
        return new InstalledPackage($name, $type, $this->root, $extra, $autoload);
    }
}
