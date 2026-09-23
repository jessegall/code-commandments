<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cs;

use JesseGall\CodeCommandments\Cs\Bridge;
use PHPUnit\Framework\TestCase;

/**
 * The Roslyn bridge's output is the contract bridge/roslyn/CONTRACT.md writes down: each file's tree,
 * node by node, with the facts the compiler resolved and nothing it did not. Run against the real
 * bridge; skipped where `dotnet` is not installed.
 */
final class BridgeContractTest extends TestCase
{
    private const string SOURCE = <<<'CS'
        namespace Shop;

        public abstract class Shape
        {
            public abstract double Area();
        }

        public sealed class Square(double side) : Shape
        {
            public override double Area() => side * side;

            public string Label(string? name)
            {
                var label = name ?? "square";
                return string.Concat(label, "!");
            }
        }
        CS;

    /**
     * @var array<string, mixed>
     */
    private static array $read = [];

    public static function setUpBeforeClass(): void
    {
        $bridge = Bridge::located();

        if ($bridge->isNone()) {
            return;
        }

        $dir = sys_get_temp_dir() . '/cc-bridge-' . uniqid();
        mkdir($dir);
        file_put_contents("{$dir}/Shapes.cs", self::SOURCE);
        self::$read = $bridge->unwrap()->read([$dir]);
        exec('rm -rf ' . escapeshellarg($dir));
    }

    protected function setUp(): void
    {
        if (self::$read === []) {
            $this->markTestSkipped('needs the dotnet SDK to build the Roslyn bridge');
        }
    }

    public function test_the_document_names_its_version_and_each_file(): void
    {
        $this->assertSame(Bridge::VERSION, self::$read['version']);
        $this->assertCount(1, self::$read['files']);
        $this->assertStringEndsWith('/Shapes.cs', self::$read['files'][0]['path']);
        $this->assertSame(0, self::$read['files'][0]['errors']);
        $this->assertSame('CompilationUnit', self::$read['files'][0]['root']['kind']);
    }

    public function test_a_declaration_carries_its_name_modifiers_and_whether_it_is_inherited(): void
    {
        $area = $this->first('MethodDeclaration', 'Area', 1);

        $this->assertSame(['public', 'override'], $area['modifiers']);
        $this->assertTrue($area['inherited']);
        $this->assertArrayNotHasKey('inherited', $this->first('MethodDeclaration', 'Label'), 'a member that overrides nothing carries no flag');
    }

    public function test_a_resolved_call_names_its_target_and_parameter_types(): void
    {
        $call = $this->first('InvocationExpression');

        $this->assertSame(['type' => 'global::System.String', 'name' => 'Concat', 'parameters' => ['global::System.String?', 'global::System.String?']], $call['target']);
    }

    public function test_literals_operators_and_types_are_facts_of_the_node(): void
    {
        $coalesce = $this->first('CoalesceExpression');

        $this->assertSame('??', $coalesce['operator']);
        $this->assertSame('global::System.String', $coalesce['type']);
        $this->assertSame('square', $this->first('StringLiteralExpression')['text']);
        $this->assertTrue($this->first('IdentifierName', 'name')['nullable'], 'string? reads as nullable');
    }

    /**
     * The first node of $kind (named $name) in source order, skipping $skip matches.
     *
     * @return array<string, mixed>
     */
    private function first(string $kind, ?string $name = null, int $skip = 0): array
    {
        $found = array_values(array_filter(self::walk(self::$read['files'][0]['root']), static fn (array $node): bool => $node['kind'] === $kind && ($name === null || ($node['name'] ?? null) === $name)));

        return $found[$skip] ?? $this->fail("no {$kind}" . ($name === null ? '' : " named {$name}"));
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<array<string, mixed>>
     */
    private static function walk(array $node): array
    {
        return [$node, ...array_merge([], ...array_map(self::walk(...), $node['children'] ?? []))];
    }
}
