<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\Archetype;
use JesseGall\CodeCommandments\Support\RoleInference;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

/**
 * Markerless, framework-AGNOSTIC structural role inference (#135 Tier B). Every
 * fixture here is plain PHP — no Laravel, no Spatie, no base class, no name
 * suffix the inferer leans on. The archetype must come from the SHAPE alone.
 */
class RoleInferenceTest extends TestCase
{
    // -------- StoreRegistry (the strong store fingerprint) --------

    public function test_classifies_a_keyed_store_written_by_a_public_mutator_and_read_back_as_store_registry(): void
    {
        // A keyed array written by a public mutator AND read by lookups on the
        // SAME prop — the full encapsulated-store fingerprint.
        $role = $this->infer(<<<'PHP'
<?php
class Container {
    private array $bindings = [];
    public function bind(string $key, $value): void { $this->bindings[$key] = $value; }
    public function lookup(string $key) { return $this->bindings[$key] ?? null; }
}
PHP);

        $this->assertSame(Archetype::StoreRegistry, $role->archetype());
        $this->assertTrue($role->isStore());
        $this->assertSame('bindings', $role->storeProperty());
    }

    public function test_a_lone_array_property_with_no_lookup_is_not_a_store(): void
    {
        // A public mutator writes the keyed array, but NOTHING reads it back as a
        // lookup. A write-only array is an accumulator/sink, NOT a store you look
        // things up from — the fingerprint must require BOTH halves.
        $role = $this->infer(<<<'PHP'
<?php
class EventLog {
    private array $events = [];
    public function record(string $key, $event): void { $this->events[$key] = $event; }
    public function flush(): void { $this->events = []; }
}
PHP);

        $this->assertNotSame(Archetype::StoreRegistry, $role->archetype());
        $this->assertFalse($role->isStore());
    }

    public function test_a_plain_array_property_never_written_publicly_is_not_a_store(): void
    {
        // The array is read in a lookup, but only the constructor ever writes it —
        // there is no public mutator, so it is not the "you put things in" store.
        $role = $this->infer(<<<'PHP'
<?php
class Lookup {
    private array $table;
    public function __construct(array $table) { $this->table = $table; }
    public function at(string $key) { return $this->table[$key] ?? null; }
}
PHP);

        $this->assertFalse($role->isStore());
    }

    // -------- Memo / lazy cache --------

    public function test_classifies_a_coalesce_assigned_keyed_array_as_memo(): void
    {
        // `$this->p[$k] ??= compute()` only — a populate-on-read memo, never
        // registered into by a plain mutator.
        $role = $this->infer(<<<'PHP'
<?php
class Memoizer {
    private array $cache = [];
    public function value(string $key): int {
        return $this->cache[$key] ??= $this->compute($key);
    }
    private function compute(string $key): int { return strlen($key); }
}
PHP);

        $this->assertSame(Archetype::Memo, $role->archetype());
        $this->assertSame('cache', $role->storeProperty());
        $this->assertFalse($role->isStore());
    }

    public function test_a_coalesce_keyed_array_also_written_plainly_is_a_store_not_a_memo(): void
    {
        // It both `??=` populates AND is written/read as a plain keyed store, so
        // the strong store fingerprint wins — it is a StoreRegistry, not a pure memo.
        $role = $this->infer(<<<'PHP'
<?php
class Pool {
    private array $items = [];
    public function put(string $key, $v): void { $this->items[$key] = $v; }
    public function getOrMake(string $key) { return $this->items[$key] ??= new \stdClass(); }
}
PHP);

        $this->assertSame(Archetype::StoreRegistry, $role->archetype());
    }

    // -------- ImmutableValue (never written after construction) --------

    public function test_classifies_readonly_promoted_value_object_as_immutable_value(): void
    {
        // All state is readonly-promoted ctor params; nothing is ever written in a
        // method body — the immutable provenance.
        $role = $this->infer(<<<'PHP'
<?php
class Money {
    public function __construct(
        public readonly int $amount,
        public readonly string $currency,
    ) {}
    public function add(Money $other): Money {
        return new Money($this->amount + $other->amount, $this->currency);
    }
}
PHP);

        $this->assertSame(Archetype::ImmutableValue, $role->archetype());
        $this->assertNull($role->storeProperty());
        $this->assertFalse($role->isStore());
    }

    public function test_classifies_a_class_written_only_in_the_constructor_as_immutable_value(): void
    {
        // State assigned only inside __construct, never mutated afterwards.
        $role = $this->infer(<<<'PHP'
<?php
class Point {
    private int $x;
    private int $y;
    public function __construct(int $x, int $y) {
        $this->x = $x;
        $this->y = $y;
    }
    public function distanceTo(Point $other): float {
        return sqrt(($this->x - $other->x) ** 2 + ($this->y - $other->y) ** 2);
    }
}
PHP);

        $this->assertSame(Archetype::ImmutableValue, $role->archetype());
    }

    // -------- MutableBag (public setter writes a whole property directly) --------

    public function test_classifies_a_public_setter_writing_a_whole_property_as_mutable_bag(): void
    {
        // A public, non-constructor method assigns `$this->prop = …` directly — a
        // mutable bag / config object, not an encapsulated keyed store.
        $role = $this->infer(<<<'PHP'
<?php
class Settings {
    private string $theme = 'light';
    private bool $debug = false;
    public function setTheme(string $theme): void { $this->theme = $theme; }
    public function enableDebug(): void { $this->debug = true; }
    public function theme(): string { return $this->theme; }
}
PHP);

        $this->assertSame(Archetype::MutableBag, $role->archetype());
        $this->assertFalse($role->isStore());
    }

    public function test_a_class_with_only_a_private_setter_is_not_a_mutable_bag(): void
    {
        // The whole-property write is funnelled through a PRIVATE method, so it is
        // encapsulated, not a public-setter bag.
        $role = $this->infer(<<<'PHP'
<?php
class Clock {
    private int $now = 0;
    public function tick(): void { $this->advance(); }
    private function advance(): void { $this->now = $this->now + 1; }
}
PHP);

        $this->assertNotSame(Archetype::MutableBag, $role->archetype());
    }

    // -------- Manual enum (closed set of static cases) --------

    public function test_classifies_a_private_ctor_with_static_cases_as_manual_enum(): void
    {
        // A non-public ctor + >= 2 parameterless static factories each building an
        // instance — the pre-8.1 enum idiom. More specific than ImmutableValue.
        $role = $this->infer(<<<'PHP'
<?php
final class Suit {
    private function __construct(public readonly string $value) {}
    public static function hearts(): self { return new self('H'); }
    public static function spades(): self { return new self('S'); }
}
PHP);

        $this->assertSame(Archetype::ManualEnum, $role->archetype());
    }

    public function test_a_value_object_with_a_parameterised_factory_is_not_a_manual_enum(): void
    {
        // An open value type (a parameterised factory) is an immutable VALUE, not a
        // closed enumeration — must not be classified ManualEnum.
        $role = $this->infer(<<<'PHP'
<?php
final class Money {
    private function __construct(public int $cents) {}
    public static function zero(): self { return new self(0); }
    public static function fromCents(int $cents): self { return new self($cents); }
}
PHP);

        $this->assertNotSame(Archetype::ManualEnum, $role->archetype());
    }

    // -------- Unknown (no confident structural signal) --------

    public function test_a_stateless_helper_is_unknown(): void
    {
        // No instance state, no store, no provenance signal — nothing to classify.
        $role = $this->infer(<<<'PHP'
<?php
class Slugifier {
    public function slugify(string $text): string {
        return strtolower(trim(preg_replace('/\s+/', '-', $text)));
    }
}
PHP);

        $this->assertSame(Archetype::Unknown, $role->archetype());
        $this->assertFalse($role->isStore());
    }

    private function infer(string $code): RoleInference
    {
        $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($code);
        $class = (new NodeFinder)->findFirstInstanceOf($ast ?? [], Node\Stmt\Class_::class);
        $this->assertInstanceOf(Node\Stmt\Class_::class, $class);

        return RoleInference::infer($class);
    }
}
