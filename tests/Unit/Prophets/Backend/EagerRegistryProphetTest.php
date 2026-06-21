<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\EagerRegistryProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class EagerRegistryProphetTest extends TestCase
{
    private EagerRegistryProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new EagerRegistryProphet;
    }

    public function test_flags_lazy_hydration_in_a_lookup(): void
    {
        $j = $this->judge('<?php namespace App; class ThingRegistry { private $items; public function all(): array { return $this->items ??= $this->build(); } private function build(): array { return []; } }');

        $this->assertTrue($j->hasWarnings());
        $this->assertSame('eager-registry:all', $j->warnings[0]->symbol);
        $this->assertStringContainsString('registered eagerly', $j->warnings[0]->message);
    }

    public function test_flags_populate_on_miss(): void
    {
        $j = $this->judge('<?php namespace App; class ThingRegistry { private $items = []; public function for(string $k) { return $this->items[$k] ??= $this->make($k); } private function make($k) {} }');
        $this->assertTrue($j->hasWarnings());
    }

    public function test_flags_self_mutator_or_discover_or_reflect_in_a_lookup(): void
    {
        $this->assertTrue($this->judge('<?php namespace App; class ARegistry { private $items=[]; public function get($k){ if(!isset($this->items[$k])) $this->register($k); return $this->items[$k]; } public function register($k){} }')->hasWarnings(), 'self-mutator in get()');
        $this->assertTrue($this->judge('<?php namespace App; use Spatie\StructureDiscoverer\Discover; class BRegistry { public function all(){ return Discover::in("x")->get(); } }')->hasWarnings(), 'Discover on read');
        $this->assertTrue($this->judge('<?php namespace App; class CRegistry { public function get($k){ return new \ReflectionClass($k); } }')->hasWarnings(), 'reflect on read');
    }

    public function test_flags_a_subclass_of_a_registry_base_with_a_lazy_lookup(): void
    {
        $j = $this->judge('<?php namespace App; class ResourceRegistry extends Registry { private $resources; public function all(): array { return $this->resources ??= $this->discover(); } private function discover(){ return []; } }');
        $this->assertTrue($j->hasWarnings());
    }

    public function test_does_not_flag_a_read_only_registry(): void
    {
        $j = $this->judge('<?php namespace App; class ThingRegistry { private $items = []; public function register($k,$v){ $this->items[$k]=$v; } public function get($k){ return $this->items[$k] ?? throw new \Exception($k); } public function has($k): bool { return isset($this->items[$k]); } public function all(): array { return $this->items; } }');
        $this->assertTrue($j->isRighteous());
    }

    public function test_does_not_flag_lazy_value_instantiation_of_a_registered_key(): void
    {
        // Membership is fixed at boot in $this->classes; get() defers only object
        // CONSTRUCTION via the container and memoises it. Legitimate (issue #187).
        $j = $this->judge('<?php namespace App; class NodeRegistry { private array $classes = []; private array $instances = []; public function __construct(private $container){} public function get(string $k){ return $this->instances[$k] ??= $this->container->make($this->classes[$k]); } }');
        $this->assertTrue($j->isRighteous(), 'container value-instantiation of a registered key is allowed');
    }

    public function test_does_not_flag_lazy_instantiation_via_new_in_a_loop(): void
    {
        $j = $this->judge('<?php namespace App; class NodeRegistry { private array $classes = []; private array $instances = []; public function all(): array { foreach ($this->classes as $k => $class) { $this->instances[$k] ??= new $class(); } return $this->instances; } }');
        $this->assertTrue($j->isRighteous(), 'new-ing already-registered class-strings is value-instantiation');
    }

    public function test_still_flags_self_make_populate_on_miss(): void
    {
        // A bare $this->make($k) self-call is ambiguous (could discover) — stays flagged.
        $j = $this->judge('<?php namespace App; class ThingRegistry { private $items = []; public function for(string $k) { return $this->items[$k] ??= $this->make($k); } private function make($k) {} }');
        $this->assertTrue($j->hasWarnings());
    }

    public function test_still_flags_lazy_value_instantiation_when_method_also_discovers(): void
    {
        // Even with a container make memo, a discovery self-call in the same lookup
        // means membership is built on read → flagged.
        $j = $this->judge('<?php namespace App; class NodeRegistry { private array $classes = []; private array $instances = []; public function __construct(private $container){} public function all(): array { $this->classes = $this->discover(); foreach ($this->classes as $k => $class) { $this->instances[$k] ??= $this->container->make($class); } return $this->instances; } private function discover(){ return []; } }');
        $this->assertTrue($j->hasWarnings(), 'discovery on read is still membership-build');
    }

    public function test_does_not_flag_a_cache_that_does_not_claim_the_registry_name(): void
    {
        // Same populate-on-miss shape, but honestly named *Cache → not a registry claim.
        $j = $this->judge('<?php namespace App; class ProductCache { private $items = []; public function get($k){ return $this->items[$k] ??= $this->make($k); } private function make($k){} }');
        $this->assertTrue($j->isRighteous(), 'an honestly-named cache is not a registry');
    }

    public function test_does_not_flag_a_service_provider(): void
    {
        $j = $this->judge('<?php namespace App; class ThingRegistryServiceProvider extends ServiceProvider { public function register(){ $this->app->singleton("x", fn()=>1); } public function boot(){} }');
        $this->assertTrue($j->isRighteous());
    }

    public function test_describes_itself(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertNotNull($this->prophet->advisory());
    }

    private function judge(string $code): Judgment
    {
        return $this->prophet->judge('/tmp/x.php', $code);
    }
}
