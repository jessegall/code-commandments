<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support\CallGraph;

use JesseGall\CodeCommandments\Support\CallGraph\CallSite;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Tests\TestCase;

class CodebaseIndexTest extends TestCase
{
    private CodebaseIndex $index;
    private string $fixtureDir;
    private string $ns = 'JesseGall\\CodeCommandments\\Tests\\Fixtures\\Backend\\Sinful\\CrossFileCallChain';

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureDir = realpath(__DIR__ . '/../../../Fixtures/Backend/Sinful/CrossFileCallChain');
        $this->assertNotFalse($this->fixtureDir, 'Fixture dir missing');

        $files = glob($this->fixtureDir . '/*.php');
        $this->assertNotEmpty($files);

        $this->index = CodebaseIndex::build($files);
    }

    public function test_collects_classes(): void
    {
        $this->assertNotNull($this->index->classByFqcn($this->ns . '\\A'));
        $this->assertNotNull($this->index->classByFqcn($this->ns . '\\B'));
        $this->assertNotNull($this->index->classByFqcn($this->ns . '\\C'));
    }

    public function test_resolves_constructor_promoted_property_types(): void
    {
        $a = $this->index->classByFqcn($this->ns . '\\A');

        $this->assertSame($this->ns . '\\B', $a->propertyTypes['b']);
    }

    public function test_indexes_method_call_with_this_property_receiver(): void
    {
        // A::ingest -> $this->b->relay(...)
        $callers = $this->index->callersOf($this->ns . '\\B', 'relay');

        $this->assertCount(1, $callers);
        $this->assertInstanceOf(CallSite::class, $callers[0]);
        $this->assertSame($this->ns . '\\A', $callers[0]->callerClassFqcn);
        $this->assertSame('ingest', $callers[0]->callerMethod);
        $this->assertSame('method', $callers[0]->calleeKind);
    }

    public function test_fingerprints_variable_argument(): void
    {
        $callers = $this->index->callersOf($this->ns . '\\B', 'relay');

        $this->assertSame(
            [['kind' => 'var', 'name' => 'payload']],
            $callers[0]->argExprs,
        );
    }

    public function test_fingerprints_complex_argument(): void
    {
        // ChainBreaker passes a ternary — should fingerprint as complex.
        $breakerDir = realpath(__DIR__ . '/../../../Fixtures/Backend/Sinful/CrossFileChainBreaker');
        $breakerIndex = CodebaseIndex::build(glob($breakerDir . '/*.php'));

        $breakerNs = 'JesseGall\\CodeCommandments\\Tests\\Fixtures\\Backend\\Sinful\\CrossFileChainBreaker';
        $callers = $breakerIndex->callersOf($breakerNs . '\\C', 'finalize');

        $this->assertCount(1, $callers);
        $this->assertSame('complex', $callers[0]->argExprs[0]['kind']);
    }

    public function test_records_json_decode_assignment(): void
    {
        $a = $this->index->classByFqcn($this->ns . '\\A');
        $ingest = $a->methods['ingest'];

        $this->assertSame(
            ['kind' => 'external_origin', 'reason' => 'json_decode'],
            $ingest->assignments['payload'],
        );
    }

    public function test_records_method_params_with_types(): void
    {
        $b = $this->index->classByFqcn($this->ns . '\\B');
        $relay = $b->methods['relay'];

        $this->assertSame('payload', $relay->params[0]['name']);
        $this->assertSame('array', $relay->params[0]['type']);
    }

    public function test_skips_unresolvable_callees(): void
    {
        // A::ingest also calls json_decode() — a global function we shouldn't
        // index as a method call since we never query for it.
        $this->assertSame([], $this->index->callersOf('json_decode', ''));
    }

    public function test_no_callers_when_not_invoked(): void
    {
        // Nothing calls A::ingest in the fixtures.
        $this->assertSame([], $this->index->callersOf($this->ns . '\\A', 'ingest'));
    }

    public function test_ignores_nonexistent_files(): void
    {
        $index = CodebaseIndex::build(['/nonexistent/file.php']);

        $this->assertNull($index->classByFqcn('Nope'));
    }

    public function test_ignores_non_php_files(): void
    {
        $index = CodebaseIndex::build([__DIR__ . '/../../../../README.md']);

        $this->assertNull($index->classByFqcn('Anything'));
    }

    public function test_handles_parse_errors_gracefully(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cg-') . '.php';
        file_put_contents($tmp, '<?php this is not valid php');

        try {
            $index = CodebaseIndex::build([$tmp]);
            $this->assertNull($index->classByFqcn('X'));
        } finally {
            @unlink($tmp);
        }
    }
}
