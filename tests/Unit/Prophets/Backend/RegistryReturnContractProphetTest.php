<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\RegistryReturnContractProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class RegistryReturnContractProphetTest extends TestCase
{
    private RegistryReturnContractProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new RegistryReturnContractProphet;
    }

    public function test_flags_an_option_getter_on_a_marked_registry(): void
    {
        $judgment = $this->judge('class R implements Registry { public function pipeline(string $c): Option { return $this->p($c); } }');

        $this->assertCount(1, $judgment->sins);
        $this->assertStringContainsString('returns an Option', $judgment->sins[0]->message);
    }

    public function test_flags_a_nullable_getter_on_an_attribute_marked_registry(): void
    {
        $judgment = $this->judge('#[Registry] class R { public function tag(string $k): ?Tag { return $this->tags[$k] ?? null; } }');

        $this->assertCount(1, $judgment->sins);
    }

    public function test_flags_a_getter_on_a_class_extending_a_registry_base(): void
    {
        // #103: the idiomatic abstract-base convention — `class FooRegistry
        // extends Registry` — carries the marker via the base class name.
        $judgment = $this->judge('class TriggerRegistry extends Registry { public function pipeline(string $k): Option { return $this->resolve($k); } }');

        $this->assertCount(1, $judgment->sins);
        $this->assertStringContainsString('returns an Option', $judgment->sins[0]->message);
    }

    public function test_flags_a_getter_on_the_registry_base_class_itself(): void
    {
        // #103: a class literally named `Registry` (the abstract base) is a
        // registry — its own non-finder Option getters fire.
        $judgment = $this->judge('abstract class Registry { public function pipeline(string $k): Option { return $this->resolve($k); } }');

        $this->assertCount(1, $judgment->sins);
    }

    public function test_an_option_return_is_a_sin_even_for_a_predicate_scan(): void
    {
        // A registry must NOT hand an Option across its boundary — not even from a
        // predicate scan. An Option return is the sin regardless of shape.
        $this->assertCount(
            1,
            $this->judge('abstract class Registry { public function first(callable $p): Option { return Option::none(); } }')->sins,
            'first(callable): Option must fire — a registry never returns an Option',
        );

        // A NULLABLE predicate scan still announces genuine value-or-nothing → exempt.
        $this->assertTrue(
            $this->judge('class FooRegistry extends Registry { public function firstWhere(\Closure $p): ?Foo { return null; } }')->isRighteous(),
        );
    }

    public function test_an_option_finder_on_a_registry_subclass_is_a_sin_but_nullable_is_exempt(): void
    {
        // find(): Option is the sin regardless of the finder name — renaming won't help.
        $this->assertCount(
            1,
            $this->judge('class FooRegistry extends Registry { public function find(string $k): Option { return Option::none(); } }')->sins,
        );

        // A NULLABLE find* stays exempt via the base-class marker.
        $this->assertTrue($this->judge('class BarRegistry extends Registry { public function find(string $k): ?Foo { return null; } }')->isRighteous());
    }

    public function test_ignores_a_class_extending_an_unrelated_base(): void
    {
        $this->assertTrue($this->judge('class FooService extends BaseService { public function thing(string $k): Option { return Option::none(); } }')->isRighteous());
    }

    public function test_leaves_finder_named_getters(): void
    {
        $this->assertTrue($this->judge('class R implements Registry { public function findByEmail(string $e): ?User { return null; } }')->isRighteous());
        $this->assertTrue($this->judge('class R implements Registry { public function tryGet(string $k): ?T { return null; } }')->isRighteous());
        $this->assertTrue($this->judge('class R implements Registry { public function tagOrNull(string $k): ?T { return null; } }')->isRighteous());
    }

    public function test_leaves_unmarked_classes_and_non_public_or_bool_methods(): void
    {
        $this->assertTrue($this->judge('class Plain { public function get(string $k): ?T { return null; } }')->isRighteous());
        $this->assertTrue($this->judge('class R implements Registry { private function memo(string $k): ?T { return null; } }')->isRighteous());
        $this->assertTrue($this->judge('class R implements Registry { public function has(string $k): bool { return true; } }')->isRighteous());
    }

    public function test_leaves_nullable_directional_for_lookups_but_option_fires(): void
    {
        // #114: a NULLABLE `<thing>For<Other>` reverse/directional lookup is a finder
        // — its miss is a real, handled outcome — not a registry must-exist getter.
        $this->assertTrue($this->judge('class R implements Registry { public function keyForClass(string $c): ?string { return $this->keys[$c] ?? null; } }')->isRighteous());
        $this->assertTrue($this->judge('class R implements Registry { public function resourceTypeForModel(string $m): ?string { return null; } }')->isRighteous());

        // …but an Option directional lookup still fires — a registry never hands out
        // an Option, finder name or not.
        $this->assertCount(1, $this->judge('class R implements Registry { public function classForKey(string $k): Option { return $this->byKey($k); } }')->sins);
    }

    public function test_is_not_auto_fixable(): void
    {
        // #114: retyping a maybe-getter to throw changes runtime behaviour, so
        // the sin is deliberately NOT auto-fixed — it must be resolved by hand.
        $judgment = $this->judge('class R implements Registry { public function pipeline(string $c): Option { return $this->resolve($c); } }');

        $this->assertCount(1, $judgment->sins);
        $this->assertFalse($judgment->sins[0]->autoFixable);
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\nnamespace App;\ninterface Registry {}\n" . $body);
    }

    public function test_resolves_the_marker_transitively_through_an_ancestor(): void
    {
        // #84: a subclass of a base that carries the marker (interface OR
        // #[Registry] attribute) is a registry too — the marker need not be on
        // every leaf.
        $dir = sys_get_temp_dir() . '/cc-reg84-' . uniqid();
        @mkdir($dir, 0755, true);

        file_put_contents("$dir/Base.php", "<?php\nnamespace App;\ninterface Registry {}\nabstract class KeyedRegistry implements Registry {}\n");
        $sub = "$dir/ResourceRegistry.php";
        file_put_contents($sub, "<?php\nnamespace App;\nfinal class ResourceRegistry extends KeyedRegistry { public function pipeline(string \$k): Option { return \$this->r(\$k); } }\n");
        $plain = "$dir/Plain.php";
        file_put_contents($plain, "<?php\nnamespace App;\nfinal class Plain { public function pipeline(string \$k): Option { return \$this->r(\$k); } }\n");

        $index = \JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex::build(glob("$dir/*.php") ?: []);
        $prophet = new RegistryReturnContractProphet;
        $prophet->setCodebaseIndex($index);

        $this->assertTrue($prophet->judge($sub, file_get_contents($sub))->isFallen(), 'subclass of a marked base is a registry.');
        $this->assertTrue($prophet->judge($plain, file_get_contents($plain))->isRighteous(), 'unmarked class is not.');

        foreach (glob("$dir/*.php") ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }
}
