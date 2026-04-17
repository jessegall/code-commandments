<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support\CallGraph;

use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\CallGraph\OriginTracer;
use JesseGall\CodeCommandments\Tests\TestCase;

class OriginTracerTest extends TestCase
{
    private CodebaseIndex $index;
    private string $ns = 'JesseGall\\CodeCommandments\\Tests\\Fixtures\\Backend\\Sinful\\CrossFileCallChain';

    protected function setUp(): void
    {
        parent::setUp();

        $files = glob(
            realpath(__DIR__ . '/../../../Fixtures/Backend/Sinful/CrossFileCallChain')
            . '/*.php'
        );

        $this->index = CodebaseIndex::build($files);
    }

    public function test_traces_two_hops_to_json_decode_origin(): void
    {
        $tracer = new OriginTracer($this->index, 10);

        // C::finalize receives $payload — trace back to A::ingest
        $trace = $tracer->trace($this->ns . '\\C', 'finalize', 'payload');

        $this->assertNotNull($trace);
        $this->assertSame($this->ns . '\\A', $trace->originClassFqcn);
        $this->assertSame('ingest', $trace->originMethod);
        $this->assertSame('json_decode', $trace->reason);
        $this->assertSame(2, $trace->hops);
    }

    public function test_traces_single_hop_from_middle_of_chain(): void
    {
        $tracer = new OriginTracer($this->index, 10);

        // B::relay receives $payload from A::ingest, one hop away
        $trace = $tracer->trace($this->ns . '\\B', 'relay', 'payload');

        $this->assertNotNull($trace);
        $this->assertSame($this->ns . '\\A', $trace->originClassFqcn);
        $this->assertSame('ingest', $trace->originMethod);
        $this->assertSame(1, $trace->hops);
    }

    public function test_returns_null_when_max_depth_reached(): void
    {
        $tracer = new OriginTracer($this->index, 1);

        // C → B (1 hop, within limit) → A (would be 2nd hop, over limit)
        $trace = $tracer->trace($this->ns . '\\C', 'finalize', 'payload');

        $this->assertNull($trace);
    }

    public function test_returns_null_when_callers_have_conflicting_origins(): void
    {
        // Build a combined index that includes both the clean A→B→C chain
        // AND a ChainBreaker caller that uses a ternary expression for the
        // argument. The complex argument aborts the whole trace.
        $breakerNs = 'JesseGall\\CodeCommandments\\Tests\\Fixtures\\Backend\\Sinful\\CrossFileChainBreaker';

        $files = array_merge(
            glob(realpath(__DIR__ . '/../../../Fixtures/Backend/Sinful/CrossFileCallChain') . '/*.php'),
            glob(realpath(__DIR__ . '/../../../Fixtures/Backend/Sinful/CrossFileChainBreaker') . '/*.php'),
        );

        $combined = CodebaseIndex::build($files);

        // In the breaker namespace, C::finalize is called with a ternary
        $tracer = new OriginTracer($combined, 10);
        $this->assertNull($tracer->trace($breakerNs . '\\C', 'finalize', 'payload'));
    }

    public function test_returns_null_for_unknown_class(): void
    {
        $tracer = new OriginTracer($this->index, 10);

        $this->assertNull($tracer->trace('Nope\\Nothing', 'foo', 'x'));
    }

    public function test_returns_null_when_param_not_found(): void
    {
        $tracer = new OriginTracer($this->index, 10);

        $this->assertNull($tracer->trace($this->ns . '\\C', 'finalize', 'doesNotExist'));
    }

    public function test_returns_null_when_no_callers(): void
    {
        $tracer = new OriginTracer($this->index, 10);

        // A::ingest is never called inside the scroll
        $this->assertNull($tracer->trace($this->ns . '\\A', 'ingest', 'raw'));
    }
}
