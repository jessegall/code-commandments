<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoManualHydrationProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoManualHydrationProphetTest extends TestCase
{
    private NoManualHydrationProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoManualHydrationProphet();
    }

    // ────────────────────────────────────────────────────────────────
    // Core flagging — static creators hydrating self
    // ────────────────────────────────────────────────────────────────

    public function test_flags_from_array_with_subscript_reads(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public static function fromArray(array $row): self {
            return new self(
                name: $row['name'],
                label: $row['label'],
            );
        }
        PHP);

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('Spec::fromArray()', $judgment->sins[0]->message);
        $this->assertStringContainsString('2 array keys', $judgment->sins[0]->message);
    }

    public function test_flags_from_array_with_arr_get_reads(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public static function fromArray(array $row): self {
            return new self(
                name: Arr::get($row, 'name'),
                label: Arr::get($row, 'label'),
            );
        }
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_from_array_with_data_get_reads(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public static function fromArray(array $row): self {
            return new self(
                name: data_get($row, 'name'),
                label: data_get($row, 'label'),
            );
        }
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_mixed_subscript_and_wrapper_reads(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public static function fromArray(array $row): self {
            return new self(
                name: $row['name'],
                label: Arr::get($row, 'label'),
            );
        }
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_reads_assigned_to_variables_first(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public static function fromArray(array $row): self|null {
            $name = Arr::get($row, 'name');

            if (! is_string($name)) {
                return null;
            }

            $label = Arr::get($row, 'label');

            return new self(
                name: $name,
                label: is_string($label) ? $label : null,
            );
        }
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_reads_with_null_coalescing_defaults(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public static function fromArray(array $row): self {
            return new self(
                name: $row['name'] ?? '',
                label: $row['label'] ?? null,
            );
        }
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_new_static_instantiation(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public static function make(array $row): static {
            return new static($row['name'], $row['label']);
        }
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_instantiation_via_own_class_name(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public static function make(array $row): self {
            return new Spec($row['name'], $row['label']);
        }
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_class_constant_keys(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public static function fromArray(array $row): self {
            return new self(
                name: $row[self::NAME_KEY],
                label: $row[self::LABEL_KEY],
            );
        }
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_enum_value_keys(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public static function fromArray(array $row): self {
            return new self(
                name: $row[Field::Name->value],
                label: $row[Field::Label->value],
            );
        }
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_destructuring_hydration(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public static function fromArray(array $row): self {
            ['name' => $name, 'label' => $label] = $row;

            return new self($name, $label);
        }
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_list_destructuring_hydration(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public static function fromArray(array $row): self {
            list('name' => $name, 'label' => $label) = $row;

            return new self($name, $label);
        }
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_foreach_destructuring_hydration(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public static function collectRows(array $rows): array {
            $specs = [];

            foreach ($rows as ['name' => $name, 'label' => $label]) {
                $specs[] = new self($name, $label);
            }

            return $specs;
        }
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_dotted_path_reads(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public static function fromArray(array $row): self {
            return new self(
                name: data_get($row, 'meta.name'),
                label: data_get($row, 'meta.label'),
            );
        }
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_regardless_of_shape_annotation(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        /**
         * @param array{name?: mixed, label?: mixed} $row
         */
        public static function fromArray(array $row): self {
            return new self(
                name: Arr::get($row, 'name'),
                label: Arr::get($row, 'label'),
            );
        }
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_regardless_of_honest_dict_annotation(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        /**
         * @param array<string, string> $row
         */
        public static function fromArray(array $row): self {
            return new self($row['name'], $row['label']);
        }
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_instance_method_hydrating_self(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function withOverrides(array $row): self {
            return new self(
                name: $row['name'] ?? $this->name,
                label: $row['label'] ?? $this->label,
            );
        }
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_aliased_arr_import(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Support\Arr as ArrHelper;
        final readonly class Spec {
            public function __construct(public string $name, public ?string $label) {}
            public static function fromArray(array $row): self {
                return new self(
                    name: ArrHelper::get($row, 'name'),
                    label: ArrHelper::get($row, 'label'),
                );
            }
        }
        PHP;

        $this->assertFallen($this->prophet->judge('/x.php', $content), 1);
    }

    // ────────────────────────────────────────────────────────────────
    // Flagging — hydrating ANOTHER class inline
    // ────────────────────────────────────────────────────────────────

    public function test_flags_inline_hydration_of_other_dto(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public static function decode(array $raw): object {
            return new MessageAction(
                id: $raw['id'] ?? '',
                markdown: $raw['markdown'] ?? '',
            );
        }
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_inline_hydration_inside_loop(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public static function decodeAll(array $rows): array {
            $out = [];

            foreach ($rows as $raw) {
                $out[] = new MessageAction(id: $raw['id'], markdown: $raw['markdown']);
            }

            return $out;
        }
        PHP);

        $this->assertFallen($judgment, 1);
    }

    // ────────────────────────────────────────────────────────────────
    // Multiple sins / counting
    // ────────────────────────────────────────────────────────────────

    public function test_flags_each_hydrating_method_separately(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public static function fromArray(array $row): self {
            return new self($row['name'], $row['label']);
        }

        public static function fromPayload(array $payload): self {
            return new self(Arr::get($payload, 'name'), Arr::get($payload, 'label'));
        }
        PHP);

        $this->assertFallen($judgment, 2);
    }

    public function test_message_lists_the_keys(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public static function fromArray(array $row): self {
            return new self($row['name'], $row['label']);
        }
        PHP);

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('name', $judgment->sins[0]->message);
        $this->assertStringContainsString('label', $judgment->sins[0]->message);
    }

    public function test_suggestion_mentions_spatie_data_and_from(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public static function fromArray(array $row): self {
            return new self($row['name'], $row['label']);
        }
        PHP);

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('Spatie\LaravelData\Data', $judgment->sins[0]->suggestion);
        $this->assertStringContainsString('::from($row)', $judgment->sins[0]->suggestion);
    }

    public function test_respects_min_key_reads_config(): void
    {
        $this->prophet->configure(['min_key_reads' => 4]);

        $judgment = $this->judgeClass(<<<'PHP'
        public static function fromArray(array $row): self {
            return new self($row['name'], $row['label'], $row['type']);
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    // ────────────────────────────────────────────────────────────────
    // Non-flagging — clean patterns
    // ────────────────────────────────────────────────────────────────

    public function test_does_not_flag_single_key_read(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public static function named(array $row): self {
            return new self($row['name'], 'default');
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_creator_without_array_reads(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public static function fromDto(object $dto): self {
            return new self(
                name: $dto->name,
                label: $dto->label,
            );
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_key_reads_without_instantiation(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public static function summarise(array $row): string {
            return $row['name'] . ' ' . $row['label'] . ' ' . $row['type'];
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_dynamic_key_reads(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public static function pick(array $row, string $a, string $b): self {
            return new self($row[$a], $row[$b]);
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_data_class_with_plain_constructor(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        final class FieldSpec extends Data {
            public function __construct(
                public readonly string $name,
                public readonly string|null $label = null,
            ) {}
        }
        PHP;

        $this->assertTrue($this->prophet->judge('/x.php', $content)->isRighteous());
    }

    public function test_does_not_flag_new_exception_with_one_key(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public static function guard(array $row): void {
            if (! isset($row['name'])) {
                throw new \InvalidArgumentException('missing name');
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_instantiation_with_unrelated_method_reads(): void
    {
        // Reads exist in the method, but the instantiated foreign class
        // receives none of them — e.g. building a log context.
        $judgment = $this->judgeClass(<<<'PHP'
        public static function report(array $row): object {
            $context = [$row['name'], $row['label']];

            return new LogEntry('warning');
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_empty_method(): void
    {
        $judgment = $this->judgeClass('public static function noop(array $row): void {}');

        $this->assertTrue($judgment->isRighteous());
    }

    // ────────────────────────────────────────────────────────────────
    // Robustness
    // ────────────────────────────────────────────────────────────────

    public function test_flags_in_trait(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        trait HydratesSelf {
            public static function fromArray(array $row): self {
                return new self($row['name'], $row['label']);
            }
        }
        PHP;

        $this->assertFallen($this->prophet->judge('/x.php', $content), 1);
    }

    public function test_flags_in_abstract_class(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        abstract class BaseSpec {
            public static function fromArray(array $row): static {
                return new static($row['name'], $row['label']);
            }
        }
        PHP;

        $this->assertFallen($this->prophet->judge('/x.php', $content), 1);
    }

    public function test_handles_empty_file(): void
    {
        $this->assertTrue($this->prophet->judge('/x.php', '<?php')->isRighteous());
    }

    public function test_handles_invalid_php_gracefully(): void
    {
        $this->assertTrue($this->prophet->judge('/x.php', '<?php this is not <<< valid')->isRighteous());
    }

    public function test_reports_method_line_number(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        final readonly class Spec {
            public function __construct(public string $name, public ?string $label) {}

            public static function fromArray(array $row): self {
                return new self($row['name'], $row['label']);
            }
        }
        PHP;

        $judgment = $this->prophet->judge('/x.php', $content);
        $this->assertFallen($judgment, 1);
        $this->assertSame(6, $judgment->sins[0]->line);
    }

    // ────────────────────────────────────────────────────────────────
    // Real-world fixture — the InputBagFieldSpec circumvention
    // ────────────────────────────────────────────────────────────────

    public function test_flags_real_world_field_spec_pattern(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Support\Arr;
        final readonly class InputBagFieldSpec {
            public function __construct(
                public string $name,
                public string|null $type,
                public string|null $label,
                public bool $enableInput,
                public mixed $default = null,
            ) {}

            /**
             * @param array{name?: mixed, type?: mixed, label?: mixed, enableInput?: mixed, default?: mixed} $row
             */
            public static function fromArray(array $row): self|null {
                $name = Arr::get($row, 'name');

                if (! is_string($name)) {
                    return null;
                }

                $type = Arr::get($row, 'type');
                $label = Arr::get($row, 'label');
                $enableInput = Arr::get($row, 'enableInput', true);
                $default = Arr::get($row, 'default');

                return new self(
                    name: $name,
                    type: is_string($type) ? $type : null,
                    label: is_string($label) ? $label : null,
                    enableInput: $enableInput === true,
                    default: $default,
                );
            }
        }
        PHP;

        $judgment = $this->prophet->judge('/x.php', $content);
        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('InputBagFieldSpec::fromArray()', $judgment->sins[0]->message);
        $this->assertStringContainsString('5 array keys', $judgment->sins[0]->message);
    }

    // ────────────────────────────────────────────────────────────────
    // Description sanity
    // ────────────────────────────────────────────────────────────────

    public function test_provides_helpful_descriptions(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertStringContainsString('Spatie', $this->prophet->description());
        $this->assertStringContainsString('::from()', $this->prophet->detailedDescription());
        $this->assertStringContainsString('MapInputName', $this->prophet->detailedDescription());
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
        final readonly class Spec {
            private const NAME_KEY = 'name';
            private const LABEL_KEY = 'label';

            public function __construct(
                public string \$name,
                public string|null \$label = null,
                public string|null \$type = null,
            ) {}

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
