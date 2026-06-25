<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoRedundantDefaultArgumentProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoRedundantDefaultArgumentProphetTest extends TestCase
{
    private NoRedundantDefaultArgumentProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoRedundantDefaultArgumentProphet;
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\nnamespace App;\nuse JesseGall\\PhpTypes\\T_Array;\n" . $body);
    }

    public function test_flags_from_key_that_resolves_to_the_param_default(): void
    {
        // T_Array::empty() (value) and T_Array::EMPTY (default) resolve to the
        // SAME underlying value ([]) via reflection — so it is redundant.
        $judgment = $this->judge(<<<'PHP'
        final class R {
            public function __construct(
                public readonly array $errors,
                public readonly array $warnings = T_Array::EMPTY,
            ) {}
            public static function clean(): self {
                return self::from(['errors' => T_Array::empty(), 'warnings' => T_Array::empty()]);
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('warnings', $judgment->warnings[0]->message);
    }

    public function test_does_not_flag_a_required_param_without_a_default(): void
    {
        $judgment = $this->judge(<<<'PHP'
        final class R {
            public function __construct(public readonly array $errors) {}
            public static function clean(): self {
                return self::from(['errors' => T_Array::empty()]);
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_value_that_differs_from_the_default(): void
    {
        $judgment = $this->judge(<<<'PHP'
        final class R {
            public function __construct(public readonly array $tags = T_Array::EMPTY) {}
            public static function make(): self {
                return self::from(['tags' => ['a']]);
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_flags_a_named_constructor_argument_equal_to_the_default(): void
    {
        $judgment = $this->judge(<<<'PHP'
        final class R {
            public function __construct(public readonly int $limit = 10) {}
            public static function make(): self {
                return new self(limit: 10);
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_is_auto_fixable_and_repent_drops_the_redundant_key(): void
    {
        $code = "<?php\nnamespace App;\nuse JesseGall\\PhpTypes\\T_Array;\n"
            . "final class R {\n"
            . "    public function __construct(public readonly array \$errors, public readonly array \$warnings = T_Array::EMPTY) {}\n"
            . "    public static function clean(): self {\n"
            . "        return self::from([\n            'errors' => T_Array::empty(),\n            'warnings' => T_Array::empty(),\n        ]);\n"
            . "    }\n}\n";

        $judgment = $this->prophet->judge('/x.php', $code);
        $this->assertTrue($judgment->warnings[0]->autoFixable);

        $result = $this->prophet->repent('/x.php', $code);
        $this->assertTrue($result->absolved);
        $this->assertStringNotContainsString("'warnings' =>", (string) $result->newContent);
        $this->assertStringContainsString("'errors' =>", (string) $result->newContent);
    }
}
