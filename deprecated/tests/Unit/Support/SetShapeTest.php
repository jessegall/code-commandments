<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\SetShape;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class SetShapeTest extends TestCase
{
    public function test_detects_an_append_and_iterate_collection(): void
    {
        $shape = SetShape::detect($this->classNode(<<<'PHP'
<?php
class EmitterSet {
    private array $items = [];
    public function add(object $e): void { $this->items[] = $e; }
    public function all(): array { return $this->items; }
}
PHP));

        $this->assertNotNull($shape);
        $this->assertContains('items', $shape->storeProperties());
    }

    public function test_detects_a_dedup_by_key_collection_with_bulk_read(): void
    {
        $shape = SetShape::detect($this->classNode(<<<'PHP'
<?php
class EmitterSet {
    private array $items = [];
    public function add(object $e): void { $this->items[$e::class] = $e; }
    public function has(string $c): bool { return isset($this->items[$c]); }
    public function all(): array { return array_values($this->items); }
}
PHP));

        $this->assertNotNull($shape);
    }

    public function test_does_not_detect_a_registry_with_keyed_value_lookup(): void
    {
        $shape = SetShape::detect($this->classNode(<<<'PHP'
<?php
class ThingRegistry {
    private array $items = [];
    public function register(string $k, $v): void { $this->items[$k] = $v; }
    public function get(string $k) { return $this->items[$k]; }
}
PHP));

        $this->assertNull($shape, 'a keyed value lookup is a registry, not a set');
    }

    public function test_does_not_detect_a_keyed_registration_store_without_a_forward_getter(): void
    {
        // #190: a keyed store registered BY an external key param (register($k,$v))
        // with keyed membership + a reverse lookup, whose forward per-key read was
        // extracted elsewhere, is a Registry — NOT a set — even with no get(string).
        $shape = SetShape::detect($this->classNode(<<<'PHP'
<?php
class DefinitionRegistry {
    private array $pipes = [];
    public function registerPipe(string $class, $def): void { $this->pipes[$class] = $def; }
    public function hasPipe(string $class): bool { return isset($this->pipes[$class]); }
    public function classForKey(string $key): ?string { return null; }
    public function all(): array { return array_values($this->pipes); }
}
PHP));

        $this->assertNull($shape, 'register-by-key is a Registry, not a set (issue #190)');
    }

    public function test_does_not_detect_a_memo(): void
    {
        $shape = SetShape::detect($this->classNode(<<<'PHP'
<?php
class Cache {
    private array $c = [];
    public function get(string $k) { return $this->c[$k] ??= $this->compute($k); }
    private function compute(string $k) { return strlen($k); }
}
PHP));

        $this->assertNull($shape);
    }

    public function test_does_not_detect_a_plain_value_object(): void
    {
        $shape = SetShape::detect($this->classNode(<<<'PHP'
<?php
class Point { public function __construct(public readonly int $x, public readonly int $y) {} }
PHP));

        $this->assertNull($shape);
    }

    public function test_does_not_detect_a_service_provider(): void
    {
        $shape = SetShape::detect($this->classNode(<<<'PHP'
<?php
class ThingServiceProvider extends ServiceProvider {
    private array $items = [];
    public function register(): void { $this->items[] = 1; }
    public function boot(): array { return $this->items; }
}
PHP));

        $this->assertNull($shape);
    }

    private function classNode(string $code): Node\Stmt\Class_
    {
        $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($code);
        $class = (new NodeFinder)->findFirstInstanceOf($ast ?? [], Node\Stmt\Class_::class);
        $this->assertInstanceOf(Node\Stmt\Class_::class, $class);

        return $class;
    }
}
