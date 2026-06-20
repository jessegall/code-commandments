<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoArrayBagProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoArrayBagProphetTest extends TestCase
{
    private NoArrayBagProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoArrayBagProphet();
    }

    // ────────────────────────────────────────────────────────────────
    // Parameters
    // ────────────────────────────────────────────────────────────────

    public function test_flags_bag_parameter(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        /**
         * @param array<string, mixed> $staticInputs
         */
        public function resolveFor(object $port, array $staticInputs): bool {
            return true;
        }
        PHP);

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('Parameter $staticInputs of resolveFor()', $judgment->sins[0]->message);
        $this->assertStringContainsString('array<string, mixed>', $judgment->sins[0]->message);
        $this->assertStringContainsString('StaticInputs extends Fluent', $judgment->sins[0]->suggestion);
    }

    public function test_flags_each_bag_parameter_separately(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        /**
         * @param array<string, mixed> $metadata
         * @param array<string, mixed> $inputs
         */
        public function compile(array $metadata, array $inputs): void {}
        PHP);

        $this->assertFallen($judgment, 2);
    }

    public function test_flags_nullable_array_parameter(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        /**
         * @param array<string, mixed>|null $metadata
         */
        public function compile(?array $metadata): void {}
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_snake_case_name_with_studly_suggestion(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        /**
         * @param array<string, mixed> $static_inputs
         */
        public function compile(array $static_inputs): void {}
        PHP);

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('StaticInputs', $judgment->sins[0]->suggestion);
    }

    public function test_flags_int_string_keyed_bag(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        /**
         * @param array<int|string, mixed> $rows
         */
        public function compile(array $rows): void {}
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_bare_array_value_bag(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        /**
         * @param array<string, array> $config
         */
        public function compile(array $config): void {}
        PHP);

        $this->assertFallen($judgment, 1);
    }

    // ────────────────────────────────────────────────────────────────
    // Properties — plain and constructor-promoted
    // ────────────────────────────────────────────────────────────────

    public function test_flags_property_with_bag_var_tag(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        /** @var array<string, mixed> */
        private array $metadata = [];
        PHP);

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('Property $metadata of Spec', $judgment->sins[0]->message);
    }

    public function test_flags_promoted_constructor_property(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        final class WorkflowNode {
            /**
             * @param array<string, mixed> $staticInputs
             */
            public function __construct(
                public readonly string $id,
                public readonly array $staticInputs = [],
            ) {}
        }
        PHP;

        $judgment = $this->prophet->judge('/x.php', $content);
        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('Property $staticInputs of WorkflowNode', $judgment->sins[0]->message);
    }

    public function test_promoted_property_on_data_class_suggests_castable(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        final class WorkflowNode extends Data {
            /**
             * @param array<string, mixed> $metadata
             */
            public function __construct(
                public readonly string $id,
                public readonly array $metadata = [],
            ) {}
        }
        PHP;

        $judgment = $this->prophet->judge('/x.php', $content);
        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('Metadata extends Fluent implements Castable', $judgment->sins[0]->suggestion);
        $this->assertStringContainsString('dataCastUsing()', $judgment->sins[0]->suggestion);
        $this->assertStringContainsString('#[WithCastable(Metadata::class)]', $judgment->sins[0]->suggestion);
    }

    public function test_property_on_data_class_with_var_tag_suggests_castable(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        final class WorkflowNode extends Data {
            /** @var array<string, mixed> */
            public array $metadata = [];
        }
        PHP;

        $judgment = $this->prophet->judge('/x.php', $content);
        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('Castable', $judgment->sins[0]->suggestion);
    }

    // ────────────────────────────────────────────────────────────────
    // Returns
    // ────────────────────────────────────────────────────────────────

    public function test_flags_bag_return(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        /**
         * @return array<string, mixed>
         */
        public function snapshot(): array {
            return ['name' => $this->name, 'type' => $this->type];
        }
        PHP);

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('snapshot() returns a raw array<string, mixed> bag', $judgment->sins[0]->message);
        $this->assertStringContainsString('Fluent', $judgment->sins[0]->suggestion);
    }

    public function test_flags_a_json_decode_bag_return(): void
    {
        // A JSON reader that decodes to an ASSOC array and returns it under an
        // array<string, mixed> contract is a bag ORIGIN — even with no array literal
        // in the body. It belongs in a Fluent value bag (e.g. ValueBag), not a raw
        // array (the readJson regression).
        $judgment = $this->judgeClass(<<<'PHP'
        /**
         * @return array<string, mixed>
         */
        public function readJson(string $path): array {
            $decoded = json_decode(file_get_contents($path), true);

            return is_array($decoded) ? $decoded : [];
        }
        PHP);

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('readJson() returns a raw array<string, mixed> bag', $judgment->sins[0]->message);
    }

    public function test_does_not_flag_a_non_associative_json_decode_return(): void
    {
        // json_decode WITHOUT associative:true yields an object, not a string-keyed
        // bag — no literal, no assoc decode → not a bag origin.
        $judgment = $this->judgeClass(<<<'PHP'
        /**
         * @return array<string, mixed>
         */
        public function readObjects(string $path): array {
            return (array) json_decode(file_get_contents($path));
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_dynamic_dictionary_return(): void
    {
        // Keys are computed at runtime (reflection) — a genuine dictionary,
        // not a record-shaped bag. The unpacker contract.
        $judgment = $this->judgeClass(<<<'PHP'
        /**
         * @return array<string, mixed>
         */
        public function reflectValues(object $instance): array {
            $values = [];

            foreach ((new \ReflectionClass($instance))->getProperties() as $property) {
                $values[$property->getName()] = $property->getValue($instance);
            }

            return $values;
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_delegating_dictionary_return(): void
    {
        // Builds no string-keyed array itself — forwards another call's result.
        $judgment = $this->judgeClass(<<<'PHP'
        /**
         * @return array<string, mixed>
         */
        public function extract(object $instance): array {
            return $this->reflectValues($instance);
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_to_array_return(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        /**
         * @return array<string, mixed>
         */
        public function toArray(): array {
            return [];
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_json_serialize_return(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        /**
         * @return array<string, mixed>
         */
        public function jsonSerialize(): array {
            return [];
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_respects_exempt_methods_config(): void
    {
        $this->prophet->configure(['exempt_methods' => ['definition']]);

        $judgment = $this->judgeClass(<<<'PHP'
        /**
         * @return array<string, mixed>
         */
        public function definition(): array {
            return [];
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    // ────────────────────────────────────────────────────────────────
    // Non-flagging — genuine dictionaries and typed declarations
    // ────────────────────────────────────────────────────────────────

    public function test_does_not_flag_concrete_value_dictionary(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        /**
         * @param array<string, PortRef> $ports
         */
        public function wire(array $ports): void {}
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_nested_generic_dictionary(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        /**
         * @param array<string, list<int>> $edges
         */
        public function wire(array $edges): void {}
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_list_annotations(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        /**
         * @param list<string> $names
         * @param array<int, string> $labels
         */
        public function wire(array $names, array $labels): void {}
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_stale_annotation_on_typed_parameter(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        /**
         * @param array<string, mixed> $metadata
         */
        public function compile(NodeMetadata $metadata): void {}
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_undocumented_array_parameter(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function compile(array $rows): void {}
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_flags_bag_branch_of_top_level_union(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        /**
         * @param int|float|string|array<string, mixed>|null $seconds
         */
        public function delay(int | float | string | array | null $seconds): void {}
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_does_not_flag_bag_nested_inside_generic(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        /**
         * @param list<array<string, mixed>> $rows
         * @param array<string, array<string, mixed>> $framesByNode
         */
        public function seed(array $rows, array $framesByNode): void {}
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_variadic_bag_parameter(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        /**
         * @param array<string, mixed> ...$bags
         */
        public function merge(array ...$bags): void {}
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    // ────────────────────────────────────────────────────────────────
    // Non-flagging — the bag class itself and vendor boundaries
    // ────────────────────────────────────────────────────────────────

    public function test_does_not_flag_inside_fluent_subclass(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Support\Fluent;
        final class StaticInputs extends Fluent {
            /**
             * @param array<string, mixed> $expected
             */
            public function matches(array $expected): bool {
                return true;
            }
        }
        PHP;

        $this->assertTrue($this->prophet->judge('/x.php', $content)->isRighteous());
    }

    public function test_does_not_flag_class_composing_a_fluent(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Support\Fluent;
        final class PortValues {
            /** @var Fluent<string, mixed> */
            private readonly Fluent $attributes;

            /**
             * @param array<string, mixed> $attributes
             */
            public function __construct(array $attributes = []) {
                $this->attributes = new Fluent($attributes);
            }

            public function get(string $key, mixed $default = null): mixed {
                return $this->attributes->get($key, $default);
            }
        }
        PHP;

        $this->assertTrue($this->prophet->judge('/x.php', $content)->isRighteous());
    }

    public function test_does_not_flag_class_with_promoted_fluent_property(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Support\Fluent;
        final class PortValues {
            /**
             * @param array<string, mixed> $defaults
             */
            public function __construct(
                private readonly Fluent $attributes,
                array $defaults = [],
            ) {}
        }
        PHP;

        $this->assertTrue($this->prophet->judge('/x.php', $content)->isRighteous());
    }

    public function test_composing_an_unrelated_class_is_not_an_exemption(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        final class Compiler {
            private readonly NodeRegistry $registry;

            /**
             * @param array<string, mixed> $metadata
             */
            public function __construct(NodeRegistry $registry, array $metadata) {
                $this->registry = $registry;
            }
        }
        PHP;

        $this->assertFallen($this->prophet->judge('/x.php', $content), 1);
    }

    public function test_does_not_flag_inside_anonymous_class(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Support\Fluent;
        final class NodeMetadata extends Fluent {
            public static function dataCastUsing(mixed ...$arguments): object {
                return new class {
                    /**
                     * @param array<string, mixed> $properties
                     */
                    public function cast(object $property, mixed $value, array $properties): NodeMetadata {
                        return new NodeMetadata(is_array($value) ? $value : []);
                    }
                };
            }
        }
        PHP;

        $this->assertTrue($this->prophet->judge('/x.php', $content)->isRighteous());
    }

    public function test_does_not_flag_cast_method_in_named_class(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        /**
         * @param array<string, mixed> $properties
         */
        public function cast(object $property, mixed $value, array $properties): object {
            return (object) $value;
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    // ────────────────────────────────────────────────────────────────
    // Interfaces and traits
    // ────────────────────────────────────────────────────────────────

    public function test_flags_bag_parameter_on_interface_method(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        interface CompilesNodes {
            /**
             * @param array<string, mixed> $metadata
             */
            public function compile(array $metadata): void;
        }
        PHP;

        $this->assertFallen($this->prophet->judge('/x.php', $content), 1);
    }

    public function test_flags_bag_parameter_in_trait(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        trait CompilesNodes {
            /**
             * @param array<string, mixed> $metadata
             */
            public function compile(array $metadata): void {}
        }
        PHP;

        $this->assertFallen($this->prophet->judge('/x.php', $content), 1);
    }

    // ────────────────────────────────────────────────────────────────
    // Robustness
    // ────────────────────────────────────────────────────────────────

    public function test_reports_line_numbers(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        final class Spec {
            /**
             * @param array<string, mixed> $metadata
             */
            public function compile(array $metadata): void {}
        }
        PHP;

        $judgment = $this->prophet->judge('/x.php', $content);
        $this->assertFallen($judgment, 1);
        $this->assertSame(7, $judgment->sins[0]->line);
    }

    public function test_handles_empty_file(): void
    {
        $this->assertTrue($this->prophet->judge('/x.php', '<?php')->isRighteous());
    }

    public function test_handles_invalid_php_gracefully(): void
    {
        $this->assertTrue($this->prophet->judge('/x.php', '<?php this is not <<< valid')->isRighteous());
    }

    // ────────────────────────────────────────────────────────────────
    // Real-world fixture — the workflows WorkflowNode shape
    // ────────────────────────────────────────────────────────────────

    public function test_flags_real_world_workflow_node(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        final class WorkflowNode extends Data {
            /**
             * @param array<string, mixed> $staticInputs
             * @param array<string, mixed> $metadata
             */
            public function __construct(
                public readonly string $id,
                public readonly string $descriptorKey,
                public readonly array $staticInputs = [],
                public readonly array $metadata = [],
            ) {}
        }
        PHP;

        $judgment = $this->prophet->judge('/x.php', $content);
        $this->assertFallen($judgment, 2);
        $this->assertStringContainsString('WithCastable', $judgment->sins[0]->suggestion);
        $this->assertStringContainsString('WithCastable', $judgment->sins[1]->suggestion);
    }

    // ────────────────────────────────────────────────────────────────
    // Real-world fixture — a hand-rolled bag abstraction stays clean
    // ────────────────────────────────────────────────────────────────

    public function test_does_not_flag_scope_frames_style_bag_abstraction(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        final class ScopeFrames {
            /** @var list<array<string, array<string, mixed>>> */
            private array $frames = [[]];

            public function set(string $nodeId, string $port, mixed $value): void {
                $this->frames[count($this->frames) - 1][$nodeId][$port] = $value;
            }

            /**
             * @return array<string, array<string, mixed>>
             */
            public function snapshot(): array {
                return array_merge(...$this->frames);
            }

            /**
             * @return array<string, array<string, mixed>>
             */
            public function base(): array {
                return $this->frames[0];
            }
        }
        PHP;

        $this->assertTrue($this->prophet->judge('/x.php', $content)->isRighteous());
    }

    // ────────────────────────────────────────────────────────────────
    // Description sanity
    // ────────────────────────────────────────────────────────────────

    public function test_provides_helpful_descriptions(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertStringContainsString('Fluent', $this->prophet->description());
        $this->assertStringContainsString('Castable', $this->prophet->detailedDescription());
        $this->assertStringContainsString('WithCastable', $this->prophet->detailedDescription());
        $this->assertStringContainsString('dataCastUsing', $this->prophet->detailedDescription());
    }

    public function test_exempts_a_data_class_array_hydration_factory(): void
    {
        // #104: forArray(array<string,mixed> $d) whose only use is self::from($d)
        // is a hydration boundary (the array is being given the Data type), not a
        // bag in flight.
        $judgment = $this->prophet->judge('/x.php', <<<'PHP'
        <?php
        use Spatie\LaravelData\Data;
        final class Thing extends Data {
            public function __construct(public readonly string $a) {}
            /** @param array<string, mixed> $data */
            public static function forArray(array $data): self { return self::from($data); }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_exempts_the_from_array_only_trait_delegate(): void
    {
        // #104: the FromArrayOnly::forArray() delegate forwards straight to
        // static::from() — same hydration boundary, in a trait.
        $judgment = $this->prophet->judge('/x.php', <<<'PHP'
        <?php
        trait FromArrayOnly {
            /** @param array<array-key, mixed> $payload */
            public static function forArray(array $payload): static { return static::from($payload); }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_still_flags_a_param_indexed_as_a_bag(): void
    {
        // A param that is actually consumed as a bag stays flagged.
        $judgment = $this->prophet->judge('/x.php', <<<'PHP'
        <?php
        final class S {
            /** @param array<string, mixed> $opts */
            public static function forArray(array $opts): self { $x = $opts['k'] ?? null; return self::from($opts); }
        }
        PHP);

        $this->assertFalse($judgment->isRighteous());
    }

    // ────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────

    private function judgeClass(string $members): Judgment
    {
        $content = <<<PHP
        <?php
        namespace App;
        use Illuminate\Support\Arr;
        final class Spec {
            {$members}
        }
        PHP;

        return $this->prophet->judge('/x.php', $content);
    }

    private function assertFallen(Judgment $judgment, ?int $expectedSins = null): void
    {
        $this->assertTrue(
            $judgment->isFallen(),
            'Expected judgment to be fallen. Sins: ' . json_encode(array_map(
                fn ($s) => $s->message,
                $judgment->sins,
            ))
        );

        if ($expectedSins !== null) {
            $this->assertCount(
                $expectedSins,
                $judgment->sins,
                'Sins: ' . json_encode(array_map(fn ($s) => $s->message, $judgment->sins))
            );
        }
    }
}
