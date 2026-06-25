<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Attributes;

use Attribute;

/**
 * Marks the package version a prophet was first shipped in.
 *
 * Used by `commandments sync --after=<version>` to add only prophets
 * introduced after the given version, so consumers upgrading from an
 * older release don't have their intentionally-removed prophets
 * re-added.
 *
 * Example:
 *     #[IntroducedIn('1.5.0')]
 *     class NoArrayStringIndexingProphet extends PhpCommandment { ... }
 *
 * Prophets without this attribute are treated as predating the
 * versioning scheme — sync will still add them on first run (no
 * `--after`), but `sync --after=<any-version>` will skip them.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class IntroducedIn
{
    public function __construct(
        public string $version,
    ) {}
}
