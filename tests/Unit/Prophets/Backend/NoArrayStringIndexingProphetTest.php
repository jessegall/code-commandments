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

    public function test_flags_fully_qualified_class_constant_key(): void
    {
        $judgment = $this->judge('return $row[\App\Foo::KEY];');

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('KEY', $judgment->sins[0]->message);
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
                return Arr::get($row['orders'], 'first', $d);
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
            public function write(array $row): array {
                Arr::set($row['nested'], 'key', 1);
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
            public function has(array $row): bool {
                return Arr::has($row['nested'], 'key');
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
            public function read(array $row): mixed {
                return \Illuminate\Support\Arr::get($row['nested'], 'key');
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
            public function read(array $row): mixed {
                return ArrHelper::get($row['nested'], 'key');
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
