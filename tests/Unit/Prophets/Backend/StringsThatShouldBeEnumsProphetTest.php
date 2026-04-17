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
