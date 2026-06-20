<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\RegistryPurityProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class RegistryPurityProphetTest extends TestCase
{
    private RegistryPurityProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new RegistryPurityProphet;
    }

    public function test_flags_a_foreign_return_resolution_method(): void
    {
        $judgment = $this->judge(<<<'PHP'
        /** @extends Registry<NodeDescriptor> */
        class NodeDescriptorRegistry extends Registry
        {
            public function get(string $k): NodeDescriptor { return $this->items[$k]; }
            /** @return \Option<OutputSocket> */
            public function findWiredSourceSocket($a, $b): \Option { return \Option::none(); }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('registry-purity:findWiredSourceSocket', $judgment->warnings[0]->symbol);
        $this->assertStringContainsString('foreign type `OutputSocket`', $judgment->warnings[0]->message);
    }

    public function test_flags_a_target_resolved_from_a_non_key_object_input(): void
    {
        $judgment = $this->judge(<<<'PHP'
        /** @extends Registry<NodeDescriptor> */
        class NodeDescriptorRegistry extends Registry
        {
            public function get(string $k): NodeDescriptor { return $this->items[$k]; }
            public function descriptorForNode(WorkflowNode $node): NodeDescriptor { return $this->get($node->key); }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('non-key', $judgment->warnings[0]->message);
    }

    public function test_leaves_the_pure_keyed_store_surface(): void
    {
        $judgment = $this->judge(<<<'PHP'
        /** @extends Registry<NodeDescriptor> */
        class NodeDescriptorRegistry extends Registry
        {
            public function get(string $k): NodeDescriptor { return $this->items[$k]; }
            public function has(string $k): bool { return isset($this->items[$k]); }
            public function all(): array { return $this->items; }
            public function register(string $k, NodeDescriptor $d): void { $this->items[$k] = $d; }
            public function count(): int { return count($this->items); }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_leaves_a_registry_with_no_declared_target_type(): void
    {
        // LEAVE-WHEN: no @extends Registry<T> → nothing to be incoherent with.
        $judgment = $this->judge(<<<'PHP'
        class Thing extends Registry
        {
            public function resolveFor(WorkflowNode $n): NodeDescriptor { return new NodeDescriptor(); }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_leaves_a_class_string_keyed_lookup(): void
    {
        // Regression: `class-string` is a string pseudo-type, not a foreign class —
        // a keyed `classForKey(): class-string` lookup must NOT be misread as foreign.
        $judgment = $this->judge(<<<'PHP'
        /** @extends Registry<Trigger> */
        class TriggerRegistry extends Registry
        {
            public function get(string $k): Trigger { return $this->items[$k]; }
            /** @return class-string<Trigger> */
            public function classForKey(string $k): string { return $this->items[$k]; }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_self_match_a_prose_mention_of_extends(): void
    {
        // Regression: a docblock that mentions "@extends Registry<T>" in PROSE (not a
        // real tag at line-start) must NOT make the class read as a registry.
        $judgment = $this->judge(<<<'PHP'
        /**
         * A helper that reads the @extends Registry<T> tag from other classes.
         */
        class RegistryDocReader
        {
            public function inspect(SomeClass $c): SomeResult { return new SomeResult(); }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_describes_itself(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertNotNull($this->prophet->advisory());
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n" . $body);
    }
}
