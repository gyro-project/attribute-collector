<?php

namespace Gyro\Component\AttributeCollector;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Script\Event;
use olvlvl\ComposerAttributeCollector\Config;
use olvlvl\ComposerAttributeCollector\Plugin as UpstreamPlugin;

use function array_keys;
use function array_values;
use function count;
use function microtime;
use function number_format;
use function sprintf;

use const DIRECTORY_SEPARATOR;

/**
 * Generates `vendor/attributes.php` from the root package *and* its installed dependencies.
 *
 * Usable both from {@see Plugin} and directly as a Composer script callable:
 *
 * ```json
 * "scripts": { "post-autoload-dump": ["Gyro\\Component\\AttributeCollector\\Dumper::run"] }
 * ```
 */
final class Dumper
{
    public static function run(Event $event): void
    {
        self::dump($event->getComposer(), $event->getIO());
    }

    public static function dump(Composer $composer, IOInterface $io): void
    {
        $config = self::buildConfig($composer, $io);

        $io->write('<info>Generating attributes file</info>');

        $start = microtime(true);
        // Upstream runs the collection in a subprocess on purpose: filtering the class map calls
        // `class_exists()`, and loading arbitrary project and vendor classes into the Composer
        // process would be asking for trouble.
        UpstreamPlugin::dump($config);
        $elapsed = number_format((microtime(true) - $start) * 1000, 3);

        $io->write(sprintf(
            '<info>Generated attributes file from %d path(s) in %s ms</info>',
            count($config->include),
            $elapsed,
        ));
    }

    /**
     * Takes upstream's resolution of the root package and widens `include` with the paths
     * contributed by the installed packages.
     */
    public static function buildConfig(Composer $composer, IOInterface $io): Config
    {
        $base = Config::from($composer, isDebug: $io->isDebug());

        // The plugin is loaded by Composer's own autoloader, which does not know about the
        // project's classes.
        require_once $base->vendorDir . DIRECTORY_SEPARATOR . 'autoload.php';

        $options = Options::fromExtra($composer->getPackage()->getExtra());
        $resolver = new PackageIncludeResolver($options);
        $resolution = $resolver->resolve(InstalledPackage::allFrom($composer));

        if ($io->isDebug()) {
            foreach ($resolution->debug as $message) {
                $io->write($message);
            }
        }

        foreach ($resolution->warnings as $message) {
            $io->write("<warning>$message</warning>");
        }

        return new Config(
            vendorDir: $base->vendorDir,
            attributesFile: $base->attributesFile,
            include: self::merge($base->include, $resolution->paths),
            exclude: $base->exclude,
            useCache: $base->useCache,
            isDebug: $base->isDebug,
        );
    }

    /**
     * @param non-empty-string[]     $rootPaths
     * @param list<non-empty-string> $vendorPaths
     *
     * @return list<non-empty-string>
     */
    private static function merge(array $rootPaths, array $vendorPaths): array
    {
        $merged = [];

        foreach ([$rootPaths, $vendorPaths] as $paths) {
            foreach ($paths as $path) {
                $merged[$path] = true;
            }
        }

        /** @var list<non-empty-string> $keys */
        $keys = array_values(array_keys($merged));

        return $keys;
    }
}
