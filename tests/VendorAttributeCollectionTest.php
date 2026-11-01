<?php

namespace Gyro\Component\AttributeCollector\Tests;

use Composer\Autoload\ClassLoader;
use Gyro\Component\AttributeCollector\InstalledPackage;
use Gyro\Component\AttributeCollector\Options;
use Gyro\Component\AttributeCollector\PackageIncludeResolver;
use Gyro\Component\AttributeCollector\Tests\Fixtures\CollectMe;
use Gyro\Component\AttributeCollector\Tests\Fixtures\VendorPackage\AcmeExtension;
use LogicException;
use olvlvl\ComposerAttributeCollector\Attributes;
use olvlvl\ComposerAttributeCollector\Collector;
use olvlvl\ComposerAttributeCollector\Config;
use olvlvl\ComposerAttributeCollector\Logger;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Stringable;

/**
 * The feature itself: an attribute declared by a class that an installed package ships has to be
 * discoverable through `Attributes::findTargetClasses()`.
 *
 * The upstream pipeline is driven in-process here rather than through `Plugin::dump()`, which forks
 * a subprocess that expects to be started by Composer from the project root.
 */
class VendorAttributeCollectionTest extends TestCase
{
    private string $attributesFile;

    protected function setUp(): void
    {
        $attributesFile = tempnam(sys_get_temp_dir(), 'gyro-attributes-');

        if ($attributesFile === false) {
            self::fail('Unable to create a temporary attributes file');
        }

        $this->attributesFile = $attributesFile;
    }

    protected function tearDown(): void
    {
        if (file_exists($this->attributesFile)) {
            unlink($this->attributesFile);
        }
    }

    public function testAttributesOfAnInstalledPackageAreCollected(): void
    {
        $package = new InstalledPackage(
            'acme/extension',
            'gyro-extension',
            realpath(__DIR__ . '/Fixtures') ?: self::fail('Missing fixtures directory'),
            autoload: ['psr-4' => ['Acme\\Extension\\' => 'VendorPackage']],
        );

        $resolution = (new PackageIncludeResolver(new Options(packageTypes: ['gyro-extension'])))
            ->resolve([$package]);

        $this->assertNotSame([], $resolution->paths, 'The fixture package contributed no paths');

        $this->collect($resolution->paths);

        $targets = $this->withGeneratedAttributes(
            fn (): array => Attributes::findTargetClasses(CollectMe::class),
        );

        $this->assertCount(1, $targets);
        $this->assertSame(AcmeExtension::class, $targets[0]->name);
        $this->assertInstanceOf(CollectMe::class, $targets[0]->attribute);
        $this->assertSame('acme', $targets[0]->attribute->name);
    }

    /** @param list<non-empty-string> $include */
    private function collect(array $include): void
    {
        $log = new class implements Logger {
            /** @var list<string> */
            public array $problems = [];

            public function debug(Stringable|string $message): void
            {
            }

            public function warning(Stringable|string $message): void
            {
                $this->problems[] = (string) $message;
            }

            public function error(Stringable|string $message): void
            {
                $this->problems[] = (string) $message;
            }
        };

        $config = new Config(
            vendorDir: self::vendorDir(),
            attributesFile: $this->attributesFile,
            include: $include,
            exclude: [],
            useCache: false,
            isDebug: false,
        );

        (new Collector($config, $log))->dump();

        $this->assertSame([], $log->problems);
    }

    /**
     * `Collector` never reads `vendorDir`, but `Config` insists on a non-empty one. Deriving it
     * from the loaded autoloader keeps the test working both inside the monorepo and in the
     * package's own repository.
     *
     * @return non-empty-string
     */
    private static function vendorDir(): string
    {
        $classLoader = (new ReflectionClass(ClassLoader::class))->getFileName()
            ?: self::fail('Unable to locate the Composer autoloader');

        return dirname($classLoader, 2);
    }

    /**
     * Loads the generated file, which swaps the global attribute provider, and puts the original
     * one back so the rest of the suite keeps seeing `vendor/attributes.php`.
     *
     * @param callable():T $assert
     *
     * @return T
     *
     * @template T
     */
    private function withGeneratedAttributes(callable $assert): mixed
    {
        $unreachable = static fn () => throw new LogicException('Attribute provider was not restored');
        $original = Attributes::with($unreachable);

        try {
            require $this->attributesFile;

            return $assert();
        } finally {
            Attributes::with($original ?? $unreachable);
        }
    }
}
