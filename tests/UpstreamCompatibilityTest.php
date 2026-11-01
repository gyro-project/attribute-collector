<?php

namespace Gyro\Component\AttributeCollector\Tests;

use Gyro\Component\AttributeCollector\Options;
use olvlvl\ComposerAttributeCollector\Attributes;
use olvlvl\ComposerAttributeCollector\Collector;
use olvlvl\ComposerAttributeCollector\Config;
use olvlvl\ComposerAttributeCollector\Logger;
use olvlvl\ComposerAttributeCollector\Plugin;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * gyro/attribute-collector drives APIs that olvlvl/composer-attribute-collector marks `@internal`.
 * The dependency is pinned to `~2.1.0` for that reason, and these assertions make an upstream bump
 * fail a test run instead of failing someone's `composer install`.
 *
 * When this test breaks, re-read the upstream diff before widening the constraint.
 */
class UpstreamCompatibilityTest extends TestCase
{
    public function testDumpIsPublicStaticAndTakesAConfig(): void
    {
        $method = new ReflectionMethod(Plugin::class, 'dump');

        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
        $this->assertEquals(['config' => Config::class], self::parameterTypes($method));
    }

    /**
     * The config travels to the collector subprocess through `serialize()`, and `collector.php`
     * unserializes it with `allowed_classes: [Config::class]`. A subclass would arrive as
     * `__PHP_Incomplete_Class`, so every value has to be passed through the real constructor.
     */
    public function testConfigConstructorAcceptsTheArgumentsWePass(): void
    {
        $constructor = new ReflectionMethod(Config::class, '__construct');

        $this->assertTrue($constructor->isPublic());
        $this->assertEquals([
            'vendorDir' => 'string',
            'attributesFile' => 'string',
            'include' => 'array',
            'exclude' => 'array',
            'useCache' => 'bool',
            'isDebug' => 'bool',
        ], self::parameterTypes($constructor));
    }

    #[DataProvider('provideConfigProperties')]
    public function testConfigExposesTheBaselineWeCopy(string $property): void
    {
        $this->assertTrue(
            (new \ReflectionProperty(Config::class, $property))->isPublic(),
            "Config::\$$property is no longer public",
        );
    }

    /** @return iterable<array{string}> */
    public static function provideConfigProperties(): iterable
    {
        yield ['vendorDir'];
        yield ['attributesFile'];
        yield ['include'];
        yield ['exclude'];
        yield ['useCache'];
        yield ['isDebug'];
    }

    public function testConfigFactoriesArePublicStatic(): void
    {
        foreach (['from', 'resolveVendorDir'] as $name) {
            $method = new ReflectionMethod(Config::class, $name);

            $this->assertTrue($method->isPublic(), "Config::$name() is no longer public");
            $this->assertTrue($method->isStatic(), "Config::$name() is no longer static");
        }
    }

    /**
     * {@see \Gyro\Component\AttributeCollector\Options} names these keys itself so that the value
     * objects stay independent of upstream; they still have to agree.
     */
    public function testExtraKeysMatchUpstream(): void
    {
        $this->assertSame(Config::EXTRA, Options::EXTRA);
        $this->assertSame(Config::EXTRA_INCLUDE, Options::EXTRA_INCLUDE);
        $this->assertSame('attributes.php', Config::FILENAME);
    }

    /**
     * Used by {@see VendorAttributeCollectionTest} to run the pipeline in-process.
     */
    public function testCollectorCanBeDrivenDirectly(): void
    {
        $constructor = new ReflectionMethod(Collector::class, '__construct');

        $this->assertTrue($constructor->isPublic());
        $this->assertEquals([
            'config' => Config::class,
            'log' => Logger::class,
        ], self::parameterTypes($constructor));

        $this->assertTrue((new ReflectionMethod(Collector::class, 'dump'))->isPublic());
    }

    public function testAttributesProviderCanBeSwappedAndRestored(): void
    {
        $with = new ReflectionMethod(Attributes::class, 'with');

        $this->assertTrue($with->isPublic());
        $this->assertTrue($with->isStatic());
        $this->assertEquals(['provider' => \Closure::class], self::parameterTypes($with));

        $returnType = $with->getReturnType();
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        $this->assertTrue($returnType->allowsNull(), 'with() must keep returning the previous provider');
    }

    /** @return array<string, string> */
    private static function parameterTypes(ReflectionMethod $method): array
    {
        $types = [];

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            $types[$parameter->getName()] = $type instanceof ReflectionNamedType
                ? $type->getName()
                : (string) $type;
        }

        return $types;
    }
}
