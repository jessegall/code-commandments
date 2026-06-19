<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\ResolverNamingHonestyProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class ResolverNamingHonestyProphetTest extends TestCase
{
    private ResolverNamingHonestyProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new ResolverNamingHonestyProphet;
    }

    public function test_flags_a_registry_lookup_named_resolver(): void
    {
        $judgment = $this->judge('class TriggerEventKeyResolver { public function resolve(string $k): mixed { return $this->map[$k] ?? throw new \Exception($k); } }');

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('misnamed', $judgment->warnings[0]->message);
    }

    public function test_flags_a_reflection_reader_named_resolver(): void
    {
        $judgment = $this->judge('class AttributeResolver { public function get($x): mixed { return (new \ReflectionClass($x))->getAttributes(); } }');

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_flags_a_string_interpolator_named_resolver(): void
    {
        $judgment = $this->judge('class BagTokenResolver { public function resolve(string $t): string { return str_replace("{x}", $this->v, $t); } }');

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_leaves_a_kernel_resolver(): void
    {
        $this->assertTrue($this->judge('class FieldTypeResolver { public function resolve($r) { return Resolver::firstResultWins(IsScalar::make()->then(F::scalar())); } }')->isRighteous());
    }

    public function test_leaves_hand_rolled_dispatch_to_resolver_pattern(): void
    {
        // match(true), ??-candidate chain, and guard-return chains are dispatch —
        // ResolverPattern's concern, not a naming problem. Stay silent.
        $this->assertTrue($this->judge('class ConnectVerdictResolver { public function resolve($x) { return match(true) { $x->a() => 1, default => 2 }; } }')->isRighteous());
        $this->assertTrue($this->judge('class CandidateResolver { public function resolve($x) { return $this->fieldCandidate($x) ?? $this->handleCandidate($x); } }')->isRighteous());
        $this->assertTrue($this->judge('class PipeResolver { public function resolve($x) { if ($x instanceof A) { return 1; } return 2; } }')->isRighteous());
        // a ?? b ?? c first-non-null candidate CHAIN (right-associative) is dispatch.
        $this->assertTrue($this->judge('class ControlContinuationResolver { public function resolve($d): string { return $d->primary()[0] ?? $d->fallback()[0] ?? "out"; } }')->isRighteous());
    }

    public function test_leaves_non_resolver_names_and_abstract_bases(): void
    {
        $this->assertTrue($this->judge('class PlainService { public function get($k) { return $this->map[$k]; } }')->isRighteous());
        $this->assertTrue($this->judge('abstract class Resolver { public function resolve($x) { return $this->map[$x]; } }')->isRighteous());
    }

    public function test_leaves_a_subclass_of_a_resolver_base(): void
    {
        $this->assertTrue($this->judge('class CachingResolver extends Resolver { public function resolve($x): mixed { return $this->cache[$x]; } }')->isRighteous());
    }

    public function test_leaves_a_member_of_a_resolver_strategy_family(): void
    {
        // Implements a `*ResolverStrategy` interface — the parent dispatches
        // between these strategies; the strategy itself does the work. Domain
        // ubiquitous language wins.
        $this->assertTrue($this->judge('class FlatPaginatedResolver implements ReportResolverStrategy { public function resolve($c): mixed { return $this->build($c)->paginate(); } }')->isRighteous());
    }

    public function test_describes_itself(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertNotNull($this->prophet->advisory());
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\nnamespace App;\n" . $body);
    }
}
