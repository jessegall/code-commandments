<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\RegistryShape;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class RegistryShapeTest extends TestCase
{
    public function test_detects_a_keyed_store_with_public_write_and_lookup(): void
    {
        $class = $this->classNode(<<<'PHP'
<?php
class GatewayRegistry {
    private array $gateways = [];
    public function register(string $key, $g): void { $this->gateways[$key] = $g; }
    public function find(string $key) { return $this->gateways[$key] ?? null; }
}
PHP);

        $shape = RegistryShape::detect($class);

        $this->assertNotNull($shape);
        $this->assertContains('gateways', $shape->storeProperties());
    }

    public function test_detects_keyed_read_through_a_lookup_helper_issue_222(): void
    {
        // The by-key read is wrapped in a helper — `Option::find($this->nodes, $key)`
        // / `data_get($this->store, $key)` — with no literal `$this->store[$key]`.
        // It is still a Registry (keyed value lookup), not a Set.
        $class = $this->classNode(<<<'PHP'
<?php
final class NodeRepository {
    private array $nodes = [];
    public function register($node): void { $this->nodes[$node->id->value] = $node; }
    public function find($id) { return Option::find($this->nodes, $id->value); }
    public function all(): array { return $this->nodes; }
}
PHP);

        $shape = RegistryShape::detect($class);

        $this->assertNotNull($shape, 'A helper-wrapped keyed read is still a Registry.');
        $this->assertContains('nodes', $shape->storeProperties());
    }

    public function test_detects_add_verb_too_name_free(): void
    {
        // The "put things in" signal is the AST write, not the method name.
        $class = $this->classNode(<<<'PHP'
<?php
class UserDirectory {
    private array $byId = [];
    public function add($user): void { $this->byId[$user->id] = $user; }
    public function getById(int $id) { return $this->byId[$id] ?? null; }
}
PHP);

        $this->assertNotNull(RegistryShape::detect($class));
    }

    public function test_readsStore_true_for_lookup_false_for_writer(): void
    {
        $class = $this->classNode(<<<'PHP'
<?php
class R {
    private array $items = [];
    public function register(string $k, $v): void { $this->items[$k] = $v; }
    public function find(string $k) { return $this->items[$k] ?? null; }
}
PHP);

        $shape = RegistryShape::detect($class);
        $this->assertNotNull($shape);

        $methods = [];
        foreach ($class->getMethods() as $m) {
            $methods[$m->name->toString()] = $m;
        }

        $this->assertTrue($shape->readsStore($methods['find']));
        $this->assertFalse($shape->readsStore($methods['register']));
    }

    public function test_not_registry_when_no_keyed_store(): void
    {
        $class = $this->classNode(<<<'PHP'
<?php
class Calculator {
    public function add(int $a, int $b): int { return $a + $b; }
}
PHP);

        $this->assertNull(RegistryShape::detect($class));
    }

    public function test_service_provider_is_excluded(): void
    {
        // A framework register() hook binds via $this->app, not a keyed store —
        // and a *ServiceProvider base is excluded outright.
        $class = $this->classNode(<<<'PHP'
<?php
class CacheServiceProvider extends ServiceProvider {
    private array $bindings = [];
    public function register(): void { $this->bindings['x'] = 1; }
    public function get(string $k) { return $this->bindings[$k] ?? null; }
}
PHP);

        $this->assertNull(RegistryShape::detect($class));
    }

    public function test_dedup_set_with_membership_test_is_not_registry_shape(): void
    {
        // A Set keys a map purely for dedup and only TESTS membership (isset) +
        // iterates — there is no keyed VALUE lookup, so it must not read as a
        // registry (issue #188 false positive).
        $class = $this->classNode(<<<'PHP'
<?php
class EmitterSet {
    private array $items = [];
    public function add(object $e): void { if (! isset($this->items[$e::class])) { $this->items[$e::class] = $e; } }
    public function has(string $c): bool { return isset($this->items[$c]); }
    public function all(): array { return array_values($this->items); }
}
PHP);

        $this->assertNull(RegistryShape::detect($class));
    }

    public function test_dedup_set_via_coalesce_assign_is_not_registry_shape(): void
    {
        $class = $this->classNode(<<<'PHP'
<?php
class EmitterSet {
    private array $items = [];
    public function add(object $e): void { $this->items[$e::class] ??= $e; }
    public function all(): array { return array_values($this->items); }
}
PHP);

        $this->assertNull(RegistryShape::detect($class));
    }

    public function test_genuine_keyed_value_lookup_still_detected_alongside_a_membership_test(): void
    {
        // has() tests membership, but get() retrieves a VALUE by key — still a registry.
        $class = $this->classNode(<<<'PHP'
<?php
class ThingRegistry {
    private array $items = [];
    public function register(string $k, $v): void { $this->items[$k] = $v; }
    public function has(string $k): bool { return isset($this->items[$k]); }
    public function get(string $k) { return $this->items[$k]; }
}
PHP);

        $this->assertNotNull(RegistryShape::detect($class));
    }

    private function classNode(string $code): Node\Stmt\Class_
    {
        $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($code);
        $class = (new NodeFinder)->findFirstInstanceOf($ast ?? [], Node\Stmt\Class_::class);
        $this->assertInstanceOf(Node\Stmt\Class_::class, $class);

        return $class;
    }
}
