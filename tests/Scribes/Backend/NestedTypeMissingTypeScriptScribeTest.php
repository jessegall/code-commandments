<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Scribes\Backend;

use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\NestedTypeMissingTypeScriptDetector;
use JesseGall\CodeCommandments\Scribes\Backend\NestedTypeMissingTypeScriptScribe;
use JesseGall\CodeCommandments\Scribes\RepentScribe;

final class NestedTypeMissingTypeScriptScribeTest extends ScribeTestCase
{
    protected function detector(): Detector
    {
        return new NestedTypeMissingTypeScriptDetector();
    }

    protected function scribe(): RepentScribe
    {
        return new NestedTypeMissingTypeScriptScribe();
    }

    public function test_stamps_typescript_on_the_nested_data_class_the_property_points_at(): void
    {
        $php = <<<'PHP'
        <?php

        namespace Spatie\LaravelData { class Data {} }
        namespace Spatie\TypeScriptTransformer\Attributes { #[\Attribute] class TypeScript {} }

        namespace App {
            use Spatie\LaravelData\Data;
            use Spatie\TypeScriptTransformer\Attributes\TypeScript;

            final class Leaf extends Data { public function __construct(public readonly string $v = '') {} }

            #[TypeScript]
            final class Page extends Data
            {
                public function __construct(
                    public readonly string $id,
                    public readonly Leaf $leaf,
                ) {}
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        $this->assertStringContainsString("#[TypeScript]\n    final class Leaf extends Data", $fixed);
    }

    public function test_leaves_a_page_whose_nested_types_are_all_tagged(): void
    {
        $php = <<<'PHP'
        <?php

        namespace Spatie\LaravelData { class Data {} }
        namespace Spatie\TypeScriptTransformer\Attributes { #[\Attribute] class TypeScript {} }

        namespace App {
            use Spatie\LaravelData\Data;
            use Spatie\TypeScriptTransformer\Attributes\TypeScript;

            #[TypeScript]
            final class Leaf extends Data { public function __construct(public readonly string $v = '') {} }

            #[TypeScript]
            final class Page extends Data
            {
                public function __construct(
                    public readonly string $id,
                    public readonly Leaf $leaf,
                ) {}
            }
        }
        PHP;

        $this->assertFalse($this->rewrote($php));
    }
}
