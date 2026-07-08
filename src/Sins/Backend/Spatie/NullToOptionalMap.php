<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Spatie;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Scaffold;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Spatie\SpatieData;

final class NullToOptionalMap extends Sin implements RequiresComposerPackage
{
    use RequiresSpatieData;

    public function __construct()
    {
        parent::__construct(
            name: 'null-to-optional-map',
            skill: SpatieData::class,
            description: "A producer hand-maps null→`new Optional` — `\$x === null ? new Optional : Foo::from(\$x)` or `expr() ?? new Optional` — instead of one named factory (Spatie's `optional()` maps null→null, the opposite of what a `T|Optional` slot needs)",
            rule: "Map an absent value onto `Optional` in ONE named factory, not a ternary at every producer. Use the scaffolded `optionalOrMissing()` (null → `new Optional`, else `::from`), the omit-from-wire counterpart to Spatie's `optional()`.",
            suggestion: "Replace `\$x === null ? new Optional : Foo::from(\$x)` / `expr() ?? new Optional` with `Foo::optionalOrMissing(\$x)` (scaffold the `OptionalOrMissing` trait).",
        );
    }

    /**
     * The `optionalOrMissing()` named factory the fix uses — scaffolded as a trait into the consumer's own
     * namespace (`use OptionalOrMissing;` on the Data class), so the null→Optional map has one home.
     */
    public function scaffolds(): array
    {
        return [
            new Scaffold('Support/OptionalOrMissing.php', 'OptionalOrMissing.php.stub'),
        ];
    }
}
