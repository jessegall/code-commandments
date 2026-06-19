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
        $judgment = $this->judge('class TriggerRegistry extends Registry { public function classForKey(string $k): Option { return $this->find($k); } }');

        $this->assertCount(1, $judgment->sins);
        $this->assertStringContainsString('returns an Option', $judgment->sins[0]->message);
    }

    public function test_flags_a_getter_on_the_registry_base_class_itself(): void
    {
        // #103: a class literally named `Registry` (the abstract base) is a
        // registry — its own non-finder Option getters fire.
        $judgment = $this->judge('abstract class Registry { public function first(callable $p): Option { return Option::none(); } }');

        $this->assertCount(1, $judgment->sins);
    }

    public function test_leaves_finder_getters_on_a_registry_base_subclass(): void
    {
        // find* stays exempt even via the base-class marker.
        $this->assertTrue($this->judge('class FooRegistry extends Registry { public function find(string $k): Option { return Option::none(); } }')->isRighteous());
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

    public function test_repent_retypes_and_wraps_an_option_getter(): void
    {
        $src = "<?php\nclass R implements Registry {\n /** @return Option<PipelineSpec> */\n public function pipeline(string \$c): Option { return \$this->resolve(\$c); }\n}\n";

        $result = $this->prophet->repent('/x.php', $src);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('public function pipeline(string $c): PipelineSpec', $result->newContent);
        $this->assertStringContainsString('return ($this->resolve($c))->getOrThrow();', $result->newContent);
    }

    public function test_repent_retypes_and_throws_for_a_nullable_getter(): void
    {
        $src = "<?php\nclass R implements Registry {\n public function tag(string \$k): ?Tag { return \$this->tags[\$k] ?? null; }\n}\n";

        $result = $this->prophet->repent('/x.php', $src);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('public function tag(string $k): Tag', $result->newContent);
        $this->assertStringContainsString('?? throw new \\RuntimeException(', $result->newContent);
        $this->assertStringNotContainsString('?? null ??', $result->newContent);
        $this->assertNotFalse((new \PhpParser\ParserFactory)->createForNewestSupportedVersion()->parse($result->newContent));
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
