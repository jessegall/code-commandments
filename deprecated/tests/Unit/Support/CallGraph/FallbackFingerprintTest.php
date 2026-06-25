<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support\CallGraph;

use JesseGall\CodeCommandments\Support\CallGraph\FallbackFingerprint;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class FallbackFingerprintTest extends TestCase
{
    public function test_qualifies_coalesce_with_static_call_chain(): void
    {
        $this->assertTrue($this->qualifies('Pipeline::current()?->child() ?? Pipeline::make()'));
    }

    public function test_qualifies_elvis_with_static_call(): void
    {
        $this->assertTrue($this->qualifies('Pipeline::current() ?: Pipeline::make()'));
    }

    public function test_qualifies_func_call_on_left(): void
    {
        $this->assertTrue($this->qualifies("config('a.b') ?? config('a.c')"));
    }

    public function test_qualifies_full_ternary_identical_null(): void
    {
        $this->assertTrue($this->qualifies('Pipeline::current() === null ? Pipeline::make() : Pipeline::current()'));
    }

    public function test_qualifies_full_ternary_not_identical_null(): void
    {
        $this->assertTrue($this->qualifies('Pipeline::current() !== null ? Pipeline::current() : Pipeline::make()'));
    }

    public function test_qualifies_full_ternary_is_null_call(): void
    {
        $this->assertTrue($this->qualifies('is_null(Pipeline::current()) ? Pipeline::make() : Pipeline::current()'));
    }

    public function test_does_not_qualify_bare_nullable_variable(): void
    {
        $this->assertFalse($this->qualifies('$user?->profile() ?? $default'));
    }

    public function test_does_not_qualify_trivial_array_access(): void
    {
        $this->assertFalse($this->qualifies("\$config['timeout'] ?? 30"));
    }

    public function test_does_not_qualify_plain_variable_coalesce(): void
    {
        $this->assertFalse($this->qualifies('$a ?? $b'));
    }

    public function test_does_not_qualify_null_fallback(): void
    {
        $this->assertFalse($this->qualifies('Pipeline::current()?->child() ?? null'));
    }

    public function test_does_not_qualify_a_plain_general_ternary(): void
    {
        $this->assertFalse($this->qualifies('Pipeline::current() ? Pipeline::a() : Pipeline::b()'));
    }

    public function test_coalesce_and_elvis_have_distinct_fingerprints(): void
    {
        $coalesce = $this->fingerprint('Pipeline::current() ?? Pipeline::make()');
        $elvis = $this->fingerprint('Pipeline::current() ?: Pipeline::make()');

        $this->assertNotSame($coalesce, $elvis);
    }

    public function test_fingerprint_ignores_whitespace(): void
    {
        $a = $this->fingerprint('Pipeline::current()   ??   Pipeline::make()');
        $b = $this->fingerprint("Pipeline::current()\n    ?? Pipeline::make()");

        $this->assertSame($a, $b);
    }

    private function qualifies(string $expr): bool
    {
        $node = $this->parseFallback($expr);

        return $node !== null && FallbackFingerprint::qualifies($node);
    }

    private function fingerprint(string $expr): ?string
    {
        $content = "<?php\n\$x = {$expr};\n";
        $node = $this->parseFallback($expr, $content);

        return $node === null ? null : FallbackFingerprint::fingerprint($node, $content);
    }

    private function parseFallback(string $expr, ?string $content = null): ?Node
    {
        $content ??= "<?php\n\$x = {$expr};\n";
        $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($content);
        $this->assertNotNull($ast);

        return (new NodeFinder)->findFirst(
            $ast,
            static fn (Node $n): bool => FallbackFingerprint::parts($n) !== null,
        );
    }
}
