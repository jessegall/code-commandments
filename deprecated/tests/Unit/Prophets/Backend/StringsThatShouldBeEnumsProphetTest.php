<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\StringsThatShouldBeEnumsProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class StringsThatShouldBeEnumsProphetTest extends TestCase
{
    private StringsThatShouldBeEnumsProphet $prophet;

    /** @var class-string enum fixtures live under this namespace */
    private const FIXTURE_NS = 'JesseGall\\CodeCommandments\\Tests\\Fixtures\\Backend\\Sinful\\ShouldBeEnums';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new StringsThatShouldBeEnumsProphet();
    }

    // ────────────────────────────────────────────────────────────────
    // Pattern 1: named-arg matches
    // ────────────────────────────────────────────────────────────────

    public function test_flags_named_arg_matching_imported_enum(): void
    {
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('PortDirection')};
        class Port {
            public function __construct(public PortDirection \$direction) {}
        }
        class Wiring {
            public function build(): Port {
                return new Port(direction: 'input');
            }
        }
        PHP;

        $judgment = $this->judge($content);
        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString("'input'", $judgment->sins[0]->message);
        $this->assertStringContainsString('PortDirection', $judgment->sins[0]->message);
        $this->assertStringContainsString('PortDirection::Input', $judgment->sins[0]->message);
    }

    public function test_flags_multiple_distinct_named_args_on_same_enum(): void
    {
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('PortDirection')};
        class P {
            public function __construct(public PortDirection \$direction) {}
        }
        class W {
            public function build(): array {
                return [new P(direction: 'input'), new P(direction: 'output')];
            }
        }
        PHP;

        $judgment = $this->judge($content);
        $this->assertFallen($judgment, 2);
    }

    public function test_dedupes_repeated_same_value_named_args(): void
    {
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('PortDirection')};
        class P {}
        class W {
            public function a(): P { return new P(direction: 'input'); }
            public function b(): P { return new P(direction: 'input'); }
        }
        PHP;

        $judgment = $this->judge($content);
        $this->assertFallen($judgment, 1);
    }

    public function test_does_not_flag_when_enum_not_imported(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class W {
            public function build() {
                return new Port(direction: 'input');
            }
        }
        class Port {
            public function __construct(public string $direction) {}
        }
        PHP;

        $this->assertTrue($this->judge($content)->isRighteous());
    }

    public function test_does_not_flag_when_value_is_not_a_case(): void
    {
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('PortDirection')};
        class P {}
        class W {
            public function build(): P {
                return new P(direction: 'sideways');
            }
        }
        PHP;

        $this->assertTrue($this->judge($content)->isRighteous());
    }

    public function test_does_not_flag_when_arg_name_does_not_match_enum(): void
    {
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('PortDirection')};
        class P {}
        class W {
            public function build(): P {
                // "label" doesn't suffix-match PortDirection
                return new P(label: 'input');
            }
        }
        PHP;

        $this->assertTrue($this->judge($content)->isRighteous());
    }

    public function test_matches_long_enum_name_with_short_arg_name(): void
    {
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('WorkflowRunStatus')};
        class Run {}
        class W {
            public function update(): Run {
                return new Run(status: 'running');
            }
        }
        PHP;

        $judgment = $this->judge($content);
        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('WorkflowRunStatus::Running', $judgment->sins[0]->message);
    }

    public function test_matches_unit_enum_by_case_name(): void
    {
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('Color')};
        class Thing {}
        class W {
            public function build(): Thing {
                return new Thing(color: 'Red');
            }
        }
        PHP;

        $judgment = $this->judge($content);
        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('Color::Red', $judgment->sins[0]->message);
    }

    public function test_case_sensitive_value_match(): void
    {
        // "red" (lowercase) is NOT a case of the unit enum Color — only "Red" is
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('Color')};
        class Thing {}
        class W {
            public function build(): Thing {
                return new Thing(color: 'red');
            }
        }
        PHP;

        $this->assertTrue($this->judge($content)->isRighteous());
    }

    public function test_bidirectional_suffix_match_arg_name_ending_with_enum_short(): void
    {
        // `Color` is a short enum name; `$themeColor` should still resolve.
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('Color')};
        class Theme {}
        class W {
            public function build(): Theme {
                return new Theme(themeColor: 'Red');
            }
        }
        PHP;

        $judgment = $this->judge($content);
        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('Color::Red', $judgment->sins[0]->message);
    }

    public function test_aliased_enum_import_still_detected(): void
    {
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('PortDirection')} as Dir;
        class Port {}
        class W {
            public function build(): Port {
                return new Port(dir: 'input');
            }
        }
        PHP;

        $judgment = $this->judge($content);
        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('Dir::Input', $judgment->sins[0]->message);
    }

    // ────────────────────────────────────────────────────────────────
    // Pattern 2: string-typed param defaults
    // ────────────────────────────────────────────────────────────────

    public function test_flags_typed_string_param_default_matching_case(): void
    {
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('WorkflowRunStatus')};
        class Run {
            public function __construct(public readonly string \$status = 'running') {}
        }
        PHP;

        $judgment = $this->judge($content);
        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('WorkflowRunStatus::Running', $judgment->sins[0]->message);
    }

    public function test_does_not_flag_non_string_typed_param_default(): void
    {
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('WorkflowRunStatus')};
        class Run {
            public function __construct(public readonly WorkflowRunStatus \$status = WorkflowRunStatus::Running) {}
        }
        PHP;

        $this->assertTrue($this->judge($content)->isRighteous());
    }

    public function test_does_not_flag_untyped_param_default(): void
    {
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('WorkflowRunStatus')};
        class Run {
            public function __construct(\$status = 'running') {}
        }
        PHP;

        $this->assertTrue($this->judge($content)->isRighteous());
    }

    public function test_nullable_string_typed_param_default_still_flagged(): void
    {
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('WorkflowRunStatus')};
        class Run {
            public function __construct(public readonly ?string \$status = 'running') {}
        }
        PHP;

        $judgment = $this->judge($content);
        $this->assertFallen($judgment, 1);
    }

    // ────────────────────────────────────────────────────────────────
    // Exception scopes
    // ────────────────────────────────────────────────────────────────

    public function test_does_not_flag_inside_toArray_method(): void
    {
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('PortDirection')};
        class P {}
        class R {
            public function toArray(): array {
                return [new P(direction: 'input')];
            }
        }
        PHP;

        $this->assertTrue($this->judge($content)->isRighteous());
    }

    public function test_does_not_flag_inside_json_serialize_method(): void
    {
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('PortDirection')};
        class P {}
        class R {
            public function jsonSerialize(): mixed {
                return new P(direction: 'input');
            }
        }
        PHP;

        $this->assertTrue($this->judge($content)->isRighteous());
    }

    public function test_does_not_flag_inside_json_resource_class(): void
    {
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('PortDirection')};
        use Illuminate\\Http\\Resources\\Json\\JsonResource;
        class PortResource extends JsonResource {
            public function resolve(\$request = null): array {
                return ['direction' => 'input'];
            }
            public function build(): mixed {
                return new \\App\\P(direction: 'input');
            }
        }
        class P {}
        PHP;

        $this->assertTrue($this->judge($content)->isRighteous());
    }

    // ────────────────────────────────────────────────────────────────
    // Vendor-target exception (Pattern 1)
    // ────────────────────────────────────────────────────────────────

    public function test_does_not_flag_named_arg_when_target_constructor_is_in_vendor(): void
    {
        // BufferedOutput resolves to vendor/symfony/console/... via composer autoload.
        // The `direction` arg name still suffix-matches PortDirection and the
        // value matches a case — but the consumer can't change Symfony's signature.
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('PortDirection')};
        use Symfony\\Component\\Console\\Output\\BufferedOutput;
        class W {
            public function build(): BufferedOutput {
                return new BufferedOutput(direction: 'input');
            }
        }
        PHP;

        $this->assertTrue($this->judge($content)->isRighteous());
    }

    public function test_does_not_flag_named_arg_when_target_is_fully_qualified_vendor_class(): void
    {
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('PortDirection')};
        class W {
            public function build() {
                return new \\Symfony\\Component\\Console\\Output\\BufferedOutput(direction: 'input');
            }
        }
        PHP;

        $this->assertTrue($this->judge($content)->isRighteous());
    }

    public function test_does_not_flag_named_arg_on_vendor_static_call(): void
    {
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('PortDirection')};
        use Symfony\\Component\\Console\\Output\\BufferedOutput;
        class W {
            public function build() {
                return BufferedOutput::factory(direction: 'input');
            }
        }
        PHP;

        $this->assertTrue($this->judge($content)->isRighteous());
    }

    public function test_does_not_flag_named_arg_on_vendor_attribute(): void
    {
        // The named arg lives inside `#[VendorClass(...)]`, an attribute
        // rather than a `new`/static call. Without Attribute support in
        // the vendor filter, the literal 'Red' would be flagged against
        // Color::Red even though the attribute class is vendored. Uses
        // Symfony's autoloadable BufferedOutput purely as a vendor target
        // — the parser only cares the FQCN resolves under /vendor/.
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('Color')};
        use Symfony\\Component\\Console\\Output\\BufferedOutput;
        class D {
            #[BufferedOutput(color: 'Red')]
            public string \$color;
        }
        PHP;

        $this->assertTrue($this->judge($content)->isRighteous());
    }

    public function test_still_flags_named_arg_when_target_constructor_is_project_owned(): void
    {
        // Regression: vendor filter must not over-match. A project-defined
        // class (no autoload path under /vendor/) still gets flagged.
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('PortDirection')};
        class Port {
            public function __construct(public PortDirection \$direction) {}
        }
        class W {
            public function build(): Port {
                return new Port(direction: 'input');
            }
        }
        PHP;

        $this->assertFallen($this->judge($content), 1);
    }

    // ────────────────────────────────────────────────────────────────
    // Sanity
    // ────────────────────────────────────────────────────────────────

    public function test_empty_file_is_righteous(): void
    {
        $this->assertTrue($this->prophet->judge('/x.php', '<?php')->isRighteous());
    }

    public function test_invalid_syntax_is_righteous(): void
    {
        $this->assertTrue($this->prophet->judge('/x.php', '<?php this is garbage <<<')->isRighteous());
    }

    public function test_file_with_no_enum_imports_is_righteous(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class X {
            public function y() {
                return ['foo' => 'input', 'bar' => 'output'];
            }
        }
        PHP;

        $this->assertTrue($this->judge($content)->isRighteous());
    }

    public function test_descriptions_are_non_empty_and_mention_enum(): void
    {
        $this->assertStringContainsString('enum', $this->prophet->description());
        $this->assertStringContainsString('enum', $this->prophet->detailedDescription());
    }

    public function test_reports_correct_line_number(): void
    {
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('PortDirection')};
        class P {}
        class W {
            public function build(): P {
                \$x = 1;
                return new P(direction: 'input');
            }
        }
        PHP;

        $judgment = $this->judge($content);
        $this->assertFallen($judgment, 1);
        $this->assertSame(8, $judgment->sins[0]->line);
    }

    // ────────────────────────────────────────────────────────────────
    // Pattern 4: array of string literals (closed-set membership test)
    // ────────────────────────────────────────────────────────────────

    public function test_flags_in_array_literal_set_matching_enum(): void
    {
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('PortDirection')};
        class W {
            public function check(\$direction): bool {
                return in_array(\$direction, ['input', 'output'], true);
            }
        }
        PHP;

        $judgment = $this->judge($content);
        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('PortDirection', $judgment->sins[0]->message);
        $this->assertStringContainsString("'input'", $judgment->sins[0]->message);
    }

    public function test_does_not_flag_in_array_literals_with_no_matching_enum(): void
    {
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('PortDirection')};
        class W {
            public function check(\$direction): bool {
                return in_array(\$direction, ['north', 'south'], true);
            }
        }
        PHP;

        $this->assertTrue($this->judge($content)->isRighteous());
    }

    public function test_does_not_flag_in_array_with_non_string_elements(): void
    {
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('PortDirection')};
        class W {
            public function check(\$direction, \$x): bool {
                return in_array(\$direction, ['input', \$x], true);
            }
        }
        PHP;

        $this->assertTrue($this->judge($content)->isRighteous());
    }

    // ────────────────────────────────────────────────────────────────
    // Pattern 5/6/7: match / switch / if-else branching on a closed set
    // ────────────────────────────────────────────────────────────────

    public function test_flags_match_on_enum_case_strings(): void
    {
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('PortDirection')};
        class W {
            public function f(\$direction): string {
                return match (\$direction) {
                    'input' => 'a',
                    'output' => 'b',
                    default => 'c',
                };
            }
        }
        PHP;

        $judgment = $this->judge($content);
        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('PortDirection', $judgment->sins[0]->message);
    }

    public function test_flags_switch_on_enum_case_strings(): void
    {
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('PortDirection')};
        class W {
            public function f(\$direction): string {
                switch (\$direction) {
                    case 'input': return 'a';
                    case 'output': return 'b';
                }
                return 'c';
            }
        }
        PHP;

        $judgment = $this->judge($content);
        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('PortDirection', $judgment->sins[0]->message);
    }

    public function test_flags_if_elseif_chain_on_enum_case_strings(): void
    {
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('PortDirection')};
        class W {
            public function f(\$direction): string {
                if (\$direction === 'input') {
                    return 'a';
                } elseif (\$direction === 'output') {
                    return 'b';
                }
                return 'c';
            }
        }
        PHP;

        $judgment = $this->judge($content);
        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('PortDirection', $judgment->sins[0]->message);
    }

    public function test_does_not_flag_match_with_no_matching_enum(): void
    {
        $content = <<<PHP
        <?php
        namespace App;
        use {$this->ns('PortDirection')};
        class W {
            public function f(\$direction): string {
                return match (\$direction) {
                    'north' => 'a',
                    'south' => 'b',
                    default => 'c',
                };
            }
        }
        PHP;

        $this->assertTrue($this->judge($content)->isRighteous());
    }

    // ────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────

    private function judge(string $content): \JesseGall\CodeCommandments\Results\Judgment
    {
        return $this->prophet->judge('/x.php', $content);
    }

    private function ns(string $shortName): string
    {
        return self::FIXTURE_NS . '\\' . $shortName;
    }

    private function assertFallen(
        \JesseGall\CodeCommandments\Results\Judgment $judgment,
        ?int $expected = null,
    ): void {
        $this->assertTrue(
            $judgment->isFallen(),
            'Expected fallen. Sins: ' . json_encode(array_map(fn ($s) => $s->message, $judgment->sins))
        );

        if ($expected !== null) {
            $this->assertCount(
                $expected,
                $judgment->sins,
                'Sins: ' . json_encode(array_map(fn ($s) => $s->message, $judgment->sins))
            );
        }
    }
}
