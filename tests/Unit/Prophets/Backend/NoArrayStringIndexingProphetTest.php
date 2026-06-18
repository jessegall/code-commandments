<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoArrayStringIndexingProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoArrayStringIndexingProphetTest extends TestCase
{
    private NoArrayStringIndexingProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoArrayStringIndexingProphet();
    }

    // ────────────────────────────────────────────────────────────────
    // Core flagging behavior
    // ────────────────────────────────────────────────────────────────

    public function test_flags_literal_string_read(): void
    {
        $judgment = $this->judge('return $row[\'nodeId\'];');

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString("\$row['nodeId']", $judgment->sins[0]->message);
    }

    public function test_flags_literal_string_write(): void
    {
        $judgment = $this->judge("\$row['status'] = 'pending';");

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString("\$row['status']", $judgment->sins[0]->message);
    }

    public function test_flags_literal_string_read_on_this_property(): void
    {
        $content = $this->classWithProperty('private array $config;', 'return $this->config[\'label\'];');
        $judgment = $this->prophet->judge('/x.php', $content);

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString("\$this->config['label']", $judgment->sins[0]->message);
    }

    public function test_flags_method_call_chain_access(): void
    {
        $judgment = $this->judge('return $this->repo->find(1)[\'name\'];');

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString("['name']", $judgment->sins[0]->message);
    }

    public function test_flags_func_call_result_access(): void
    {
        $judgment = $this->judge('return fetchRow()[\'name\'];');

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_static_call_result_access(): void
    {
        $judgment = $this->judge('return Repo::find(1)[\'name\'];');

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_access_inside_closure(): void
    {
        $judgment = $this->judge(<<<'PHP'
        $fn = function (array $row) { return $row['name']; };
        return $fn([]);
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_access_inside_arrow_function(): void
    {
        $judgment = $this->judge(<<<'PHP'
        $fn = fn(array $row) => $row['name'];
        return $fn([]);
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_access_inside_array_map_callback(): void
    {
        $judgment = $this->judge('return array_map(fn($r) => $r[\'name\'], [$row]);');

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_access_in_match_arm(): void
    {
        $judgment = $this->judge(<<<'PHP'
        return match (true) {
            isset($row['name']) => $row['name'],
            default => 'unknown',
        };
        PHP);

        $this->assertFallen($judgment);
    }

    public function test_flags_access_in_ternary(): void
    {
        $judgment = $this->judge("return \$row['x'] ?: 'fallback';");

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_access_in_null_coalescing(): void
    {
        $judgment = $this->judge("return \$row['x'] ?? 'fallback';");

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_null_coalescing_assignment(): void
    {
        $judgment = $this->judge("\$row['x'] ??= 'fallback'; return \$row;");

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_access_in_isset(): void
    {
        $judgment = $this->judge("return isset(\$row['x']) ? 'y' : 'n';");

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_access_in_unset(): void
    {
        $judgment = $this->judge("unset(\$row['x']); return 'ok';");

        $this->assertFallen($judgment, 1);
    }

    public function test_flags_compound_assignment_on_string_key(): void
    {
        $judgment = $this->judge("\$row['total'] += 5; return (string) \$row['total'];");

        $this->assertFallen($judgment, 1);
        $this->assertCount(1, $judgment->sins);
    }

    // ────────────────────────────────────────────────────────────────
    // Key classification
    // ────────────────────────────────────────────────────────────────

    public function test_flags_numeric_string_key(): void
    {
        $judgment = $this->judge("return \$row['0'];");

        $this->assertFallen($judgment, 1);
    }

    public function test_does_not_flag_integer_key(): void
    {
        $judgment = $this->judge('return $row[0];');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_negative_integer_key(): void
    {
        $judgment = $this->judge('return $row[-1];');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_float_key(): void
    {
        $judgment = $this->judge('return $row[1.5];');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_variable_key(): void
    {
        $judgment = $this->judge('foreach ($items as $k => $v) { $items[$k] = $v; } return $items;');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_expression_key(): void
    {
        $judgment = $this->judge('return $items[$i + 1];');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_method_call_key(): void
    {
        $judgment = $this->judge('return $items[$this->keyFor()];');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_string_concat_key(): void
    {
        $judgment = $this->judge("return \$items['prefix' . \$suffix];");

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_flags_self_class_constant_key(): void
    {
        $judgment = $this->judge('return $row[self::FIELD];');

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('self::FIELD', $judgment->sins[0]->message);
    }

    public function test_flags_static_class_constant_key(): void
    {
        $judgment = $this->judge('return $row[static::FIELD];');

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('static::FIELD', $judgment->sins[0]->message);
    }

    public function test_does_not_flag_unconfirmable_external_class_constant_key(): void
    {
        // KEY is not declared in this file — we can't confirm it's a string
        // (it could be an int index), so it must not be flagged.
        $this->assertTrue($this->judge('return $row[\App\Foo::KEY];')->isRighteous());
    }

    public function test_does_not_flag_numeric_constant_key(): void
    {
        // T_Int::ZERO is an integer index, not a record field.
        $this->assertTrue($this->judge('return $row[\JesseGall\PhpTypes\T_Int::ZERO];')->isRighteous());
    }

    public function test_does_not_flag_class_string_key(): void
    {
        $this->assertTrue($this->judge('return $row[\App\Foo::class];')->isRighteous());
        $this->assertTrue($this->judge('$obj = new \App\Foo; return $row[$obj::class];')->isRighteous());
    }

    public function test_does_not_flag_class_string_keyed_dictionary_property(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        final class Registry {
            /** @var array<class-string<Detector>, Detector> */
            private array $detectors = [];
            public function add(Detector $d): void { $this->detectors[$d::class] = $d; }
            public function get(string $c): ?Detector { return $this->detectors[$c] ?? null; }
        }
        PHP;

        $this->assertTrue($this->prophet->judge('/x.php', $content)->isRighteous());
    }

    public function test_flags_enum_case_value_key(): void
    {
        $judgment = $this->judge('return $row[Field::Total->value];');

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('Field::Total->value', $judgment->sins[0]->message);
    }

    public function test_flags_aliased_enum_value_key(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use App\Enums\OrderField as F;
        class S {
            public function read(array $row): mixed {
                return $row[F::Total->value];
            }
        }
        PHP;

        $judgment = $this->prophet->judge('/x.php', $content);
        $this->assertFallen($judgment, 1);
    }

    // ────────────────────────────────────────────────────────────────
    // Exception cases — wrapper helpers / superglobals
    // ────────────────────────────────────────────────────────────────

    public function test_does_not_flag_config_helper(): void
    {
        $judgment = $this->judge("return config('app.name');");

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_env_helper(): void
    {
        $judgment = $this->judge("return env('APP_ENV');");

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_trans_helper(): void
    {
        $judgment = $this->judge("return trans('messages.welcome');");

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_data_get_helper(): void
    {
        $judgment = $this->judge("return data_get(\$row, 'nested.key');");

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_data_set_helper(): void
    {
        $judgment = $this->judge("data_set(\$row, 'nested.key', 1); return \$row;");

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_data_forget_helper(): void
    {
        $judgment = $this->judge("data_forget(\$row, 'nested.key'); return \$row;");

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_access_inside_arr_get(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Support\Arr;
        class S {
            public function read(array $row, string $d): mixed {
                return Arr::get($row['orders'], $d);
            }
        }
        PHP;

        $judgment = $this->prophet->judge('/x.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_access_inside_arr_set(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Support\Arr;
        class S {
            public function write(array $row, string $key): array {
                Arr::set($row['nested'], $key, 1);
                return $row;
            }
        }
        PHP;

        $judgment = $this->prophet->judge('/x.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_access_inside_arr_has(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Support\Arr;
        class S {
            public function has(array $row, string $key): bool {
                return Arr::has($row['nested'], $key);
            }
        }
        PHP;

        $judgment = $this->prophet->judge('/x.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_access_with_fully_qualified_arr(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class S {
            public function read(array $row, string $key): mixed {
                return \Illuminate\Support\Arr::get($row['nested'], $key);
            }
        }
        PHP;

        $judgment = $this->prophet->judge('/x.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_access_with_aliased_arr_import(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Support\Arr as ArrHelper;
        class S {
            public function read(array $row, string $key): mixed {
                return ArrHelper::get($row['nested'], $key);
            }
        }
        PHP;

        $judgment = $this->prophet->judge('/x.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_access_inside_nested_wrapper_calls(): void
    {
        $judgment = $this->judge("return data_get(config('app'), \$row['key']);");

        $this->assertTrue($judgment->isRighteous());
    }

    // ────────────────────────────────────────────────────────────────
    // Wrapper circumvention — Arr::get etc. with a literal key
    // ────────────────────────────────────────────────────────────────

    public function test_flags_arr_get_with_literal_key(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Support\Arr;
        class S {
            public function nodes(array $graph): mixed {
                return Arr::get($graph, 'nodes');
            }
        }
        PHP;

        $judgment = $this->prophet->judge('/x.php', $content);
        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('Arr::get', $judgment->sins[0]->message);
        $this->assertStringContainsString("'nodes'", $judgment->sins[0]->message);
        $this->assertStringContainsString('do not absolve', $judgment->sins[0]->message);
    }

    public function test_flags_data_get_with_literal_single_segment_key(): void
    {
        $judgment = $this->judge("return data_get(\$row, 'name');");

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('data_get', $judgment->sins[0]->message);
    }

    public function test_flags_other_arr_methods_with_literal_key(): void
    {
        $methods = [
            "Arr::has(\$row, 'name')",
            "Arr::set(\$row, 'name', 1)",
            "Arr::forget(\$row, 'name')",
            "Arr::pull(\$row, 'name')",
            "Arr::add(\$row, 'name', 1)",
            "Arr::exists(\$row, 'name')",
        ];

        foreach ($methods as $call) {
            $content = <<<PHP
            <?php
            namespace App;
            use Illuminate\Support\Arr;
            class S {
                public function touch(array \$row): mixed {
                    return {$call};
                }
            }
            PHP;

            $this->assertFallen(
                $this->prophet->judge('/x.php', $content),
                1,
            );
        }
    }

    public function test_flags_aliased_arr_with_literal_key(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Support\Arr as ArrHelper;
        class S {
            public function read(array $row): mixed {
                return ArrHelper::get($row, 'name');
            }
        }
        PHP;

        $this->assertFallen($this->prophet->judge('/x.php', $content), 1);
    }

    public function test_flags_arr_get_with_enum_value_key(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Support\Arr;
        class S {
            public function total(array $row): mixed {
                return Arr::get($row, Field::Total->value);
            }
        }
        PHP;

        $this->assertFallen($this->prophet->judge('/x.php', $content), 1);
    }

    public function test_does_not_flag_arr_get_with_dynamic_key(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Support\Arr;
        class S {
            public function read(array $row, string $key): mixed {
                return Arr::get($row, $key);
            }
        }
        PHP;

        $this->assertTrue($this->prophet->judge('/x.php', $content)->isRighteous());
    }

    public function test_does_not_flag_arr_get_with_dotted_deep_path(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Support\Arr;
        class S {
            public function read(array $row): mixed {
                return Arr::get($row, 'meta.created.at');
            }
        }
        PHP;

        $this->assertTrue($this->prophet->judge('/x.php', $content)->isRighteous());
    }

    public function test_does_not_flag_arr_get_on_dict_annotated_target(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Support\Arr;
        class S {
            /** @param array<string, int> $scores */
            public function read(array $scores): mixed {
                return Arr::get($scores, 'alice');
            }
        }
        PHP;

        $this->assertTrue($this->prophet->judge('/x.php', $content)->isRighteous());
    }

    public function test_flags_arr_get_on_mixed_dict_annotated_target(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Support\Arr;
        class S {
            /** @param array<string, mixed> $graph */
            public function nodes(array $graph): mixed {
                return Arr::get($graph, 'nodes');
            }
        }
        PHP;

        $this->assertFallen($this->prophet->judge('/x.php', $content), 1);
    }

    public function test_does_not_flag_arr_get_on_shape_annotated_target(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Support\Arr;
        class S {
            /** @param array{nodes: list<mixed>} $graph */
            public function nodes(array $graph): mixed {
                return Arr::get($graph, 'nodes');
            }
        }
        PHP;

        $this->assertTrue($this->prophet->judge('/x.php', $content)->isRighteous());
    }

    public function test_does_not_flag_arr_except_with_key_list(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Support\Arr;
        class S {
            public function strip(array $node): array {
                return Arr::except($node, ['position']);
            }
        }
        PHP;

        $this->assertTrue($this->prophet->judge('/x.php', $content)->isRighteous());
    }

    public function test_dedupes_subscript_and_wrapper_access_to_same_key(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Support\Arr;
        class S {
            public function read(array $row): mixed {
                $a = $row['name'];
                return [$a, Arr::get($row, 'name')];
            }
        }
        PHP;

        $this->assertFallen($this->prophet->judge('/x.php', $content), 1);
    }

    public function test_wrapper_sin_suggestion_mentions_dto(): void
    {
        $judgment = $this->judge("return data_get(\$row, 'name');");

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('DTO', $judgment->sins[0]->suggestion);
        $this->assertStringContainsString('parameter', $judgment->sins[0]->suggestion);
    }

    public function test_does_not_flag_any_superglobal(): void
    {
        $supers = ['_GET', '_POST', '_REQUEST', '_COOKIE', '_SESSION', '_SERVER', '_ENV', '_FILES', 'GLOBALS'];

        foreach ($supers as $super) {
            $judgment = $this->judge("return \${$super}['x'];");
            $this->assertTrue(
                $judgment->isRighteous(),
                "Expected \${$super}['x'] to be ignored"
            );
        }
    }

    // ────────────────────────────────────────────────────────────────
    // PHPDoc dict tags
    // ────────────────────────────────────────────────────────────────

    public function test_respects_param_phpdoc_dict_tag(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class L {
            /** @param array<string, int> $scores */
            public function first(array $scores): int { return $scores['alice']; }
        }
        PHP;
        $this->assertTrue($this->prophet->judge('/x.php', $content)->isRighteous());
    }

    public function test_respects_inline_var_phpdoc_dict_tag(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class L {
            public function first(): int {
                /** @var array<string, int> $scores */
                $scores = $this->load();
                return $scores['alice'];
            }
            public function load(): array { return []; }
        }
        PHP;
        $this->assertTrue($this->prophet->judge('/x.php', $content)->isRighteous());
    }

    public function test_respects_property_phpdoc_dict_tag(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class L {
            /** @var array<string, int> */
            private array $scores = [];
            public function first(): int { return $this->scores['alice']; }
        }
        PHP;
        $this->assertTrue($this->prophet->judge('/x.php', $content)->isRighteous());
    }

    public function test_still_flags_property_access_without_dict_tag(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class L {
            private array $config = [];
            public function label(): string { return $this->config['label']; }
        }
        PHP;
        $this->assertFallen($this->prophet->judge('/x.php', $content), 1);
    }

    public function test_param_dict_tag_does_not_leak_to_other_methods(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class L {
            /** @param array<string, int> $items */
            public function first(array $items): int { return $items['a']; }
            public function second(array $items): int { return $items['a']; }
        }
        PHP;
        $judgment = $this->prophet->judge('/x.php', $content);
        $this->assertFallen($judgment, 1);
        $this->assertSame(6, $judgment->sins[0]->line);
    }

    public function test_respects_array_key_type_alias(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class L {
            /** @param array<array-key, int> $items */
            public function first(array $items): int { return $items['a']; }
        }
        PHP;
        $this->assertTrue($this->prophet->judge('/x.php', $content)->isRighteous());
    }

    public function test_respects_nested_dict_value_type(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class L {
            /** @param array<string, array<string, int>> $matrix */
            public function first(array $matrix): int { return $matrix['row']['col']; }
        }
        PHP;
        $this->assertTrue($this->prophet->judge('/x.php', $content)->isRighteous());
    }

    public function test_flags_despite_param_dict_tag_with_mixed_value_type(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class L {
            /** @param array<string, mixed> $graph */
            public function nodes(array $graph): mixed { return $graph['nodes']; }
        }
        PHP;
        $this->assertFallen($this->prophet->judge('/x.php', $content), 1);
    }

    public function test_flags_despite_param_dict_tag_with_bare_array_value_type(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class L {
            /** @param array<string, array> $graph */
            public function nodes(array $graph): mixed { return $graph['nodes']; }
        }
        PHP;
        $this->assertFallen($this->prophet->judge('/x.php', $content), 1);
    }

    public function test_flags_despite_inline_var_dict_tag_with_mixed_value_type(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class L {
            public function nodes(): mixed {
                /** @var array<string, mixed> $graph */
                $graph = $this->load();
                return $graph['nodes'];
            }
            public function load(): array { return []; }
        }
        PHP;
        $this->assertFallen($this->prophet->judge('/x.php', $content), 1);
    }

    public function test_flags_despite_property_dict_tag_with_mixed_value_type(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class L {
            /** @var array<string, mixed> */
            private array $graph = [];
            public function nodes(): mixed { return $this->graph['nodes']; }
        }
        PHP;
        $this->assertFallen($this->prophet->judge('/x.php', $content), 1);
    }

    // ────────────────────────────────────────────────────────────────
    // PHPDoc array shapes — exact shapes count as typed
    // ────────────────────────────────────────────────────────────────

    public function test_respects_param_array_shape(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class L {
            /** @param array{nodes: list<mixed>, edges: list<mixed>} $graph */
            public function nodes(array $graph): mixed { return $graph['nodes']; }
        }
        PHP;
        $this->assertTrue($this->prophet->judge('/x.php', $content)->isRighteous());
    }

    public function test_respects_param_array_shape_with_nested_shape(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class L {
            /** @param array{meta: array{version: int}, payload: string} $envelope */
            public function version(array $envelope): mixed { return $envelope['meta']['version']; }
        }
        PHP;
        $this->assertTrue($this->prophet->judge('/x.php', $content)->isRighteous());
    }

    public function test_respects_inline_var_array_shape(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class L {
            public function pair(): string {
                /** @var array{0: string, 1: int} $pair */
                $pair = $this->load();
                return $pair['0'];
            }
            public function load(): array { return []; }
        }
        PHP;
        $this->assertTrue($this->prophet->judge('/x.php', $content)->isRighteous());
    }

    public function test_flags_inline_var_shape_on_built_array_literal(): void
    {
        // The dodge: annotate a record you BUILD here with an exact shape
        // instead of introducing a DTO. The shape must not bless the literal.
        $content = <<<'PHP'
        <?php
        namespace App;
        class L {
            public function build(): string {
                /** @var array{financialStatus: string, lineItems: list<mixed>} $payload */
                $payload = ['financialStatus' => 'PAID', 'lineItems' => []];
                return $payload['financialStatus'];
            }
        }
        PHP;
        $this->assertFallen($this->prophet->judge('/x.php', $content), 1);
    }

    public function test_respects_inline_var_dict_on_built_literal(): void
    {
        // A genuine homogeneous dictionary you build is a real map, not a
        // record in disguise — a concrete dict type still opts out.
        $content = <<<'PHP'
        <?php
        namespace App;
        class L {
            public function rates(): int {
                /** @var array<string, int> $prices */
                $prices = ['usd' => 1, 'eur' => 2];
                return $prices['usd'];
            }
        }
        PHP;
        $this->assertTrue($this->prophet->judge('/x.php', $content)->isRighteous());
    }

    public function test_respects_property_array_shape(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class L {
            /** @var array{label: string, weight: int} */
            private array $config = [];
            public function label(): string { return $this->config['label']; }
        }
        PHP;
        $this->assertTrue($this->prophet->judge('/x.php', $content)->isRighteous());
    }

    public function test_flags_despite_all_mixed_shape_annotation(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class L {
            /** @param array{name?: mixed, type?: mixed} $row */
            public function name(array $row): mixed { return $row['name']; }
        }
        PHP;
        $this->assertFallen($this->prophet->judge('/x.php', $content), 1);
    }

    public function test_flags_arr_get_despite_all_mixed_shape_annotation(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Support\Arr;
        class L {
            /** @param array{name?: mixed, type?: mixed} $row */
            public function name(array $row): mixed { return Arr::get($row, 'name'); }
        }
        PHP;

        $judgment = $this->prophet->judge('/x.php', $content);
        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('Arr::get', $judgment->sins[0]->message);
    }

    public function test_respects_shape_with_one_concrete_type_among_mixed(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class L {
            /** @param array{name: string, default?: mixed} $row */
            public function name(array $row): mixed { return $row['default']; }
        }
        PHP;
        $this->assertTrue($this->prophet->judge('/x.php', $content)->isRighteous());
    }

    public function test_flags_despite_inline_var_all_mixed_shape(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class L {
            public function name(): mixed {
                /** @var array{name?: mixed} $row */
                $row = $this->load();
                return $row['name'];
            }
            public function load(): array { return []; }
        }
        PHP;
        $this->assertFallen($this->prophet->judge('/x.php', $content), 1);
    }

    public function test_flags_despite_property_all_mixed_shape(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class L {
            /** @var array{label?: mixed, weight?: mixed} */
            private array $config = [];
            public function label(): mixed { return $this->config['label']; }
        }
        PHP;
        $this->assertFallen($this->prophet->judge('/x.php', $content), 1);
    }

    public function test_array_shape_does_not_leak_to_other_methods(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class L {
            /** @param array{a: int} $items */
            public function first(array $items): int { return $items['a']; }
            public function second(array $items): int { return $items['a']; }
        }
        PHP;
        $judgment = $this->prophet->judge('/x.php', $content);
        $this->assertFallen($judgment, 1);
        $this->assertSame(6, $judgment->sins[0]->line);
    }

    public function test_ignores_plain_list_phpdoc(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class L {
            /** @param array<int, string> $items */
            public function first(array $items): string { return $items['nope']; }
        }
        PHP;
        $this->assertFallen($this->prophet->judge('/x.php', $content), 1);
    }

    public function test_ignores_typed_list_phpdoc(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class L {
            /** @param list<string> $items */
            public function first(array $items): string { return $items['nope']; }
        }
        PHP;
        $this->assertFallen($this->prophet->judge('/x.php', $content), 1);
    }

    // ────────────────────────────────────────────────────────────────
    // Destructuring / write patterns
    // ────────────────────────────────────────────────────────────────

    public function test_does_not_flag_short_destructuring(): void
    {
        $judgment = $this->judge("['name' => \$name] = \$row; return \$name;");

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_list_destructuring(): void
    {
        $judgment = $this->judge("list('name' => \$name) = \$row; return \$name;");

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_foreach_list_destructuring(): void
    {
        $judgment = $this->judge(<<<'PHP'
        $out = '';
        foreach ($rows as ['name' => $name]) { $out .= $name; }
        return $out;
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_array_literal_values(): void
    {
        $judgment = $this->judge("return ['name' => 'foo', 'type' => 'bar'];");

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_array_column_string_arg(): void
    {
        $judgment = $this->judge('return array_column($rows, \'name\');');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_eloquent_update_with_array_literal(): void
    {
        $judgment = $this->judge(<<<'PHP'
        $run->update([
            'status' => 'delayed',
            'duration_ms' => 42,
            'resume_node_id' => 'n-1',
        ]);
        return 'ok';
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_eloquent_create_with_array_literal(): void
    {
        $judgment = $this->judge("\$model = Run::create(['status' => 'ready', 'name' => 'n']); return \$model;");

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_where_with_array_literal(): void
    {
        $judgment = $this->judge("\$q = Run::query()->where(['status' => 'ready']); return \$q;");

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_nested_array_literal_values(): void
    {
        $judgment = $this->judge(<<<'PHP'
        return [
            'meta' => ['v' => 1, 'source' => 'api'],
            'payload' => ['id' => 'x', 'label' => 'y'],
        ];
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_still_flags_array_subscripts_inside_update_call(): void
    {
        $judgment = $this->judge(<<<'PHP'
        $run->update([
            'status' => $data['status'],
        ]);
        return 'ok';
        PHP);

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString("\$data['status']", $judgment->sins[0]->message);
    }

    public function test_does_not_flag_array_keys(): void
    {
        $judgment = $this->judge('return array_keys($row);');

        $this->assertTrue($judgment->isRighteous());
    }

    // ────────────────────────────────────────────────────────────────
    // Dedupe / multiple sins
    // ────────────────────────────────────────────────────────────────

    public function test_dedupes_repeated_access_to_same_key(): void
    {
        $judgment = $this->judge(<<<'PHP'
        if ($row['nodeId']) { return $row['nodeId']; }
        return $row['nodeId'] ?? '';
        PHP);

        $this->assertFallen($judgment, 1);
    }

    public function test_reports_distinct_keys_on_same_variable(): void
    {
        $judgment = $this->judge("return [\$row['a'], \$row['b'], \$row['c']];");

        $this->assertFallen($judgment, 3);
    }

    public function test_reports_distinct_variables_with_same_key(): void
    {
        $judgment = $this->judge("return \$a['name'] . \$b['name'];");

        $this->assertFallen($judgment, 2);
    }

    public function test_reports_access_on_property_and_param_separately(): void
    {
        $content = $this->classWithProperty(
            'private array $cfg = [];',
            "return \$this->cfg['label'] . \$row['label'];"
        );
        $judgment = $this->prophet->judge('/x.php', $content);

        $this->assertFallen($judgment, 2);
    }

    public function test_triple_nested_access_creates_three_sins(): void
    {
        $judgment = $this->judge("return \$a['b']['c']['d'];");

        $this->assertFallen($judgment, 3);
    }

    // ────────────────────────────────────────────────────────────────
    // Source hint (suggestion)
    // ────────────────────────────────────────────────────────────────

    public function test_suggestion_for_param_mentions_replacing_parameter(): void
    {
        $judgment = $this->judge("return \$row['name'];");
        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('parameter', $judgment->sins[0]->suggestion);
    }

    public function test_suggestion_for_property_mentions_property(): void
    {
        $content = $this->classWithProperty('private array $cfg = [];', "return \$this->cfg['x'];");
        $judgment = $this->prophet->judge('/x.php', $content);

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('$this->cfg', $judgment->sins[0]->suggestion);
    }

    public function test_suggestion_for_method_call_mentions_return_type(): void
    {
        $judgment = $this->judge('return $this->repo->find(1)[\'name\'];');

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('return a DTO', $judgment->sins[0]->suggestion);
        $this->assertStringContainsString('find()', $judgment->sins[0]->suggestion);
    }

    public function test_suggestion_for_static_call_mentions_return_type(): void
    {
        $judgment = $this->judge('return Repo::find(1)[\'name\'];');

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('find()', $judgment->sins[0]->suggestion);
    }

    public function test_suggestion_for_func_call_mentions_return_type(): void
    {
        $judgment = $this->judge('return fetchRow()[\'name\'];');

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('fetchRow()', $judgment->sins[0]->suggestion);
    }

    public function test_suggestion_for_nested_access_mentions_tree(): void
    {
        $judgment = $this->judge("return \$data['a']['b'];");

        $this->assertFallen($judgment, 2);
        $innerSuggestion = collect($judgment->sins)
            ->first(fn ($s) => str_contains($s->message, "\$data['a'][")) ?->suggestion;
        $this->assertNotNull($innerSuggestion);
        $this->assertStringContainsString('each level', $innerSuggestion);
    }

    public function test_suggestion_always_mentions_dto(): void
    {
        $judgment = $this->judge("return \$row['name'];");
        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('DTO', $judgment->sins[0]->suggestion);
    }

    public function test_suggestion_mentions_spatie_data(): void
    {
        $judgment = $this->judge("return \$row['name'];");
        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('Spatie', $judgment->sins[0]->suggestion);
    }

    // ────────────────────────────────────────────────────────────────
    // Line number / snippet accuracy
    // ────────────────────────────────────────────────────────────────

    public function test_reports_correct_line_number(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class S {
            public function process(array $row): string {
                $x = 1;
                return $row['nodeId'];
            }
        }
        PHP;
        $judgment = $this->prophet->judge('/x.php', $content);
        $this->assertFallen($judgment, 1);
        $this->assertSame(6, $judgment->sins[0]->line);
    }

    public function test_reports_distinct_lines_for_distinct_keys(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class S {
            public function process(array $row): array {
                $a = $row['a'];
                $b = $row['b'];
                return [$a, $b];
            }
        }
        PHP;
        $judgment = $this->prophet->judge('/x.php', $content);
        $this->assertFallen($judgment, 2);
        $this->assertSame(5, $judgment->sins[0]->line);
        $this->assertSame(6, $judgment->sins[1]->line);
    }

    // ────────────────────────────────────────────────────────────────
    // Robustness
    // ────────────────────────────────────────────────────────────────

    public function test_handles_file_with_no_classes(): void
    {
        $content = "<?php echo \$_GET['q'];";

        $judgment = $this->prophet->judge('/x.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_handles_empty_file(): void
    {
        $judgment = $this->prophet->judge('/x.php', '<?php');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_handles_syntactically_invalid_file_gracefully(): void
    {
        $judgment = $this->prophet->judge('/x.php', '<?php this is not valid php <<<');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_flags_in_trait(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        trait T {
            public function read(array $row): string { return $row['name']; }
        }
        PHP;
        $this->assertFallen($this->prophet->judge('/x.php', $content), 1);
    }

    public function test_flags_in_abstract_class(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        abstract class A {
            public function read(array $row): string { return $row['name']; }
        }
        PHP;
        $this->assertFallen($this->prophet->judge('/x.php', $content), 1);
    }

    public function test_flags_in_enum_method(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        enum E: string {
            case A = 'a';
            public function label(array $row): string { return $row['label']; }
        }
        PHP;
        $this->assertFallen($this->prophet->judge('/x.php', $content), 1);
    }

    public function test_flags_in_interface_default_method(): void
    {
        // Interfaces can't have method bodies but we still want to parse safely
        $content = <<<'PHP'
        <?php
        namespace App;
        interface I {
            public function read(array $row): string;
        }
        PHP;
        $this->assertTrue($this->prophet->judge('/x.php', $content)->isRighteous());
    }

    public function test_flags_at_top_level_script(): void
    {
        $content = "<?php \$row = [];\nreturn \$row['x'];";
        $this->assertFallen($this->prophet->judge('/x.php', $content), 1);
    }

    // ────────────────────────────────────────────────────────────────
    // Fixture file tests
    // ────────────────────────────────────────────────────────────────

    public function test_fixture_array_string_indexing_on_param(): void
    {
        $judgment = $this->judgeFixture('Sinful/ArrayStringIndexingOnParam.php');
        $this->assertFallen($judgment, 3);

        $suggestions = array_map(fn ($s) => $s->suggestion, $judgment->sins);
        foreach ($suggestions as $suggestion) {
            $this->assertStringContainsString('$row', $suggestion);
            $this->assertStringContainsString('parameter', $suggestion);
        }
    }

    public function test_fixture_array_string_indexing_on_property(): void
    {
        $judgment = $this->judgeFixture('Sinful/ArrayStringIndexingOnProperty.php');
        $this->assertFallen($judgment, 3);

        foreach ($judgment->sins as $sin) {
            $this->assertStringContainsString('$this->config', $sin->suggestion);
        }
    }

    public function test_fixture_array_string_indexing_from_method_call(): void
    {
        $judgment = $this->judgeFixture('Sinful/ArrayStringIndexingFromMethodCall.php');
        $this->assertFallen($judgment, 2);

        $suggestions = array_map(fn ($s) => $s->suggestion, $judgment->sins);
        foreach ($suggestions as $suggestion) {
            $this->assertStringContainsString('find()', $suggestion);
        }
    }

    public function test_fixture_deeply_nested_array_string_indexing(): void
    {
        $judgment = $this->judgeFixture('Sinful/DeeplyNestedArrayStringIndexing.php');
        $this->assertTrue($judgment->isFallen());
        $this->assertGreaterThanOrEqual(9, count($judgment->sins));
    }

    public function test_fixture_enum_keyed_array_access(): void
    {
        $judgment = $this->judgeFixture('Sinful/EnumKeyedArrayAccess.php');
        $this->assertFallen($judgment, 3);

        $messages = array_map(fn ($s) => $s->message, $judgment->sins);
        $this->assertContains(
            true,
            array_map(fn ($m) => str_contains($m, 'Total->value'), $messages)
        );
    }

    public function test_fixture_nested_call_chain_array_access(): void
    {
        $judgment = $this->judgeFixture('Sinful/NestedCallChainArrayAccess.php');
        $this->assertTrue($judgment->isFallen());

        $suggestions = array_map(fn ($s) => $s->suggestion, $judgment->sins);
        $kindsFound = [
            'param' => count(array_filter($suggestions, fn ($s) => str_contains($s, 'parameter'))),
            'call' => count(array_filter($suggestions, fn ($s) => str_contains($s, 'return a DTO'))),
        ];

        $this->assertGreaterThan(0, $kindsFound['param']);
        $this->assertGreaterThan(0, $kindsFound['call']);
    }

    public function test_fixture_righteous_typed_dto_usage(): void
    {
        $judgment = $this->judgeFixture('Righteous/ProperTypedDtoUsage.php');
        $this->assertTrue($judgment->isRighteous());
    }

    // ────────────────────────────────────────────────────────────────
    // Description sanity
    // ────────────────────────────────────────────────────────────────

    public function test_provides_helpful_descriptions(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertStringContainsString('DTO', $this->prophet->description());
        $this->assertStringContainsString('array<string,', $this->prophet->detailedDescription());
    }

    // ────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────

    private function judge(string $methodBody): \JesseGall\CodeCommandments\Results\Judgment
    {
        $content = <<<PHP
        <?php
        namespace App\\Services;
        class OrderService {
            public function process(array \$row, array \$items = [], array \$rows = [], array \$a = [], array \$b = [], array \$data = []): mixed {
                {$methodBody}
            }
            public function load(): array { return []; }
            public function keyFor(): string { return ''; }
            private const FIELD = 'field';
            private \$repo;
        }
        PHP;

        return $this->prophet->judge('/x.php', $content);
    }

    private function classWithProperty(string $propertyDeclaration, string $methodBody): string
    {
        return <<<PHP
        <?php
        namespace App\\Services;
        class OrderService {
            {$propertyDeclaration}
            public function process(array \$row = []): mixed {
                {$methodBody}
            }
        }
        PHP;
    }

    private function judgeFixture(string $relativePath): \JesseGall\CodeCommandments\Results\Judgment
    {
        $raw = __DIR__ . '/../../../Fixtures/Backend/' . $relativePath;
        $path = realpath($raw);
        $this->assertNotFalse($path, "Fixture not found at {$raw}");
        return $this->prophet->judge($path, file_get_contents($path));
    }

    private function assertFallen(
        \JesseGall\CodeCommandments\Results\Judgment $judgment,
        ?int $expectedSins = null,
    ): void {
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
