# gyro/attribute-collector

A Composer plugin that collects PHP attribute targets from the root package **and from its installed
dependencies**, so a third party package can ship an attribute-annotated class and have the
application find it.

It is a thin layer on top of [olvlvl/composer-attribute-collector][upstream], which only indexes
paths listed in the root package. Everything else — scanning, filtering, reflection, rendering — is
upstream's work, reused as-is; see [olvlvl/composer-attribute-collector#75][issue].

## Installation

```shell
composer require gyro/attribute-collector
```

Composer will ask to allow the plugin. Your application's `composer.json` needs three things:

```json
{
    "autoload": {
        "files": ["vendor/attributes.php"]
    },
    "config": {
        "allow-plugins": {
            "gyro/attribute-collector": true,
            "olvlvl/composer-attribute-collector": false
        }
    }
}
```

- **`autoload.files`** registers the generated file. Composer ignores `autoload` of a dependency for
  this purpose, so the entry has to live in your root `composer.json`.
- **`allow-plugins`** has to be in your root `composer.json` too, for the same reason. Disabling
  upstream's plugin is optional: leaving it enabled simply generates the file twice, because this
  plugin runs last and overwrites it. Disabling it is the tidier choice.

Without the `allow-plugins` entry nothing is generated and the first `Attributes::` call throws
`LogicException("provider not set yet")`.

## Configuration

Configuration lives under the **upstream** `extra.composer-attribute-collector` key, so it carries
over unchanged if upstream ever implements this itself.

```json
{
    "extra": {
        "composer-attribute-collector": {
            "include": ["src"],
            "exclude": [],

            "include-vendor-dependencies": false,
            "vendor": {
                "packages":         { "acme/foo": ["src"] },
                "types":            ["acme-plugin"],
                "exclude-packages": ["phpunit/phpunit"]
            }
        }
    }
}
```

`include` and `exclude` keep their upstream meaning and apply to the root package. The `vendor`
section and `include-vendor-dependencies` are this plugin's additions.

For each installed package, the first matching rule wins:

| Rule | Behaviour |
| --- | --- |
| `vendor.packages` | Scans the listed paths, relative to the package's install path. |
| the package's own `extra.composer-attribute-collector.include` | How a package opts itself in, without the application naming it. |
| `vendor.types` | Scans the autoload paths of packages whose Composer `type` is listed. |
| `include-vendor-dependencies: true` | Scans the autoload paths of every installed package. |
| — | The package is skipped. |

`vendor.exclude-packages` wins over all of them. Autoload paths mean `psr-4`, `psr-0` and
`classmap`: `files` and `exclude-from-classmap` are not trees to scan, and `autoload-dev` never
applies to a dependency.

Run `composer dump-autoload -vvv` to see which package contributed which path and why.

### Choosing a mode

`vendor.types` is usually the right default for a framework. Scanning everything works and is
cheap — roughly 0.2 s on a 3000-file vendor tree, because upstream rejects any file without a
literal `#[` before reflection ever runs — but it also hands you attribute targets from packages
that never asked to participate. If your container auto-registers everything it is given, keep the
opt-in narrow.

A package opts in with no configuration on the application's side by declaring a type:

```json
{
    "name": "acme/foo",
    "type": "acme-plugin",
    "autoload": { "psr-4": { "Acme\\Foo\\": "src/" } }
}
```

## Usage

Nothing changes at runtime — this plugin only widens what ends up in `vendor/attributes.php`:

```php
use olvlvl\ComposerAttributeCollector\Attributes;

foreach (Attributes::findTargetClasses(MyAttribute::class) as $target) {
    $instance = new ($target->name)();
}
```

## Without the plugin

`Dumper::run` also works as a Composer script callable, which needs no `allow-plugins` consent. This
only works from a root `composer.json`:

```json
{
    "scripts": {
        "post-autoload-dump": ["Gyro\\Component\\AttributeCollector\\Dumper::run"]
    }
}
```

## Caveats

- Filtering the class map calls `class_exists()`, which executes the file. Upstream catches and
  warns about the fallout (a class whose parent lives in an unmet optional dependency, for
  instance), but a genuine PHP fatal — a parse error under your PHP version, a duplicate
  declaration — fails the whole `composer install`. That is what `exclude` and
  `vendor.exclude-packages` are for.
- `composer install --no-plugins` leaves the generated file stale or empty. Upstream behaves the
  same way.
- This plugin drives APIs upstream marks `@internal`, so the dependency is pinned to `~2.1.0`.
  `UpstreamCompatibilityTest` asserts every signature relied upon, so an upstream bump fails a test
  run rather than someone's install.

## Licence

MIT. See [LICENSE](LICENSE).

[upstream]: https://github.com/olvlvl/composer-attribute-collector
[issue]: https://github.com/olvlvl/composer-attribute-collector/issues/75
