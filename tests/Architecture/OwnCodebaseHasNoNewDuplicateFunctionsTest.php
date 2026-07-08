<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Architecture;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\DuplicateFunctionDetector;
use PHPUnit\Framework\TestCase;

/**
 * We eat our own dog food: this runs our {@see DuplicateFunctionDetector} over our OWN `src/`, so an
 * exact-duplicate method can never be ADDED without a test failing. The current duplicates are a documented
 * BASELINE (the cleanup backlog) — the test fails the moment a NEW one appears OR a listed one is removed,
 * so the list only shrinks. Consolidate a `Class::method` below (a shared helper/trait) and delete its line.
 */
final class OwnCodebaseHasNoNewDuplicateFunctionsTest extends TestCase
{
    /**
     * Known exact-duplicate methods, awaiting consolidation. NEVER add to this list — extract the shared
     * code instead. (Removing an entry as you consolidate is expected and keeps the baseline honest.)
     *
     * @var list<string>
     */
    private const array BASELINE = [
        'JesseGall\CodeCommandments\Ast\ClassField::shortName',
        'JesseGall\CodeCommandments\Ast\Spatie\DataConstructions::forCodebase',
        'JesseGall\CodeCommandments\Ast\Support\DataClassShape::forCodebase',
        'JesseGall\CodeCommandments\Ast\Support\DataClassShape::shortName',
        'JesseGall\CodeCommandments\Ast\Support\FeatureEnvy::constructs',
        'JesseGall\CodeCommandments\Ast\Support\FeatureEnvy::typeName',
        'JesseGall\CodeCommandments\Ast\Support\LookupEnvy::constructs',
        'JesseGall\CodeCommandments\Ast\Support\LookupEnvy::typeName',
        'JesseGall\CodeCommandments\Ast\Support\ResponseSurface::forCodebase',
        'JesseGall\CodeCommandments\Ast\Support\RouteActions::forCodebase',
        'JesseGall\CodeCommandments\Ast\Support\StructuralHash::shortName',
        'JesseGall\CodeCommandments\Ast\Support\TypeResolver::forCodebase',
        'JesseGall\CodeCommandments\Bridge\Backend\DataTypeProvider::shortName',
        'JesseGall\CodeCommandments\Cli\Benchmark::shortName',
        'JesseGall\CodeCommandments\Cli\Hints\DataHintScribe::slice',
        'JesseGall\CodeCommandments\Cli\Hints\Hints::relative',
        'JesseGall\CodeCommandments\Cli\Judge\DetectorRunner::shortName',
        'JesseGall\CodeCommandments\Cli\Judge\Judge::shortName',
        'JesseGall\CodeCommandments\Cli\Repent::relative',
        'JesseGall\CodeCommandments\Scribes\Backend\ConstructorOrchestrationScribe::slice',
        'JesseGall\CodeCommandments\Scribes\Backend\NestedTernaryScribe::slice',
        'JesseGall\CodeCommandments\Scribes\Backend\NewDataObjectScribe::slice',
        'JesseGall\CodeCommandments\Scribes\Backend\NullObjectDefaultScribe::slice',
        'JesseGall\CodeCommandments\Scribes\Backend\RedundantElseScribe::rewrite',
        'JesseGall\CodeCommandments\Scribes\Backend\RedundantEnumUnwrapScribe::rewrite',
        'JesseGall\CodeCommandments\Scribes\Backend\RedundantNativeCastScribe::rewrite',
        'JesseGall\CodeCommandments\Scribes\Backend\RedundantNestedFromScribe::rewrite',
        'JesseGall\CodeCommandments\Scribes\RepentScribe::name',
        'JesseGall\CodeCommandments\Scribes\Scribe::name',
        'JesseGall\CodeCommandments\Testing\FixtureExamples::dedent',
        'JesseGall\CodeCommandments\Testing\FixtureExamples::forKeys',
        'JesseGall\CodeCommandments\Testing\VueFixtureExamples::dedent',
        'JesseGall\CodeCommandments\Testing\VueFixtureExamples::forKeys',
        'JesseGall\CodeCommandments\Vue\Script::skipString',
        'JesseGall\CodeCommandments\Vue\Ts\Lexer::skipString',
        'JesseGall\CodeCommandments\Vue\Ts\Node\CompositeType::references',
        'JesseGall\CodeCommandments\Vue\Ts\Node\InterfaceDecl::fields',
        'JesseGall\CodeCommandments\Vue\Ts\Node\InterfaceDecl::references',
        'JesseGall\CodeCommandments\Vue\Ts\Node\ObjectType::fields',
        'JesseGall\CodeCommandments\Vue\Ts\Node\ObjectType::references',
    ];

    public function test_no_exact_duplicate_functions_outside_the_shrinking_baseline(): void
    {
        $codebase = Codebase::scan(__DIR__ . '/../../src');

        $found = [];

        foreach (new DuplicateFunctionDetector()->find($codebase) as $match) {
            $found[(string) $match->scope()] = true;
        }

        $found = array_keys($found);
        sort($found);

        $new = array_values(array_diff($found, self::BASELINE));
        $fixed = array_values(array_diff(self::BASELINE, $found));

        $this->assertSame([], $new, "NEW exact-duplicate function(s) in src/ — extract the shared code (do NOT add to the baseline):\n" . implode("\n", $new));
        $this->assertSame([], $fixed, "These baseline duplicates are gone — delete them from BASELINE to keep it honest:\n" . implode("\n", $fixed));
    }
}
