<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\ConstantsAndPropertiesFirstProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class ConstantsAndPropertiesFirstProphetTest extends TestCase
{
    private ConstantsAndPropertiesFirstProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new ConstantsAndPropertiesFirstProphet;
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\nnamespace App;\n" . $body);
    }

    public function test_flags_a_constant_declared_after_a_method(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Provider {
            public function register(): void {}
            private const array COMMANDS = ['a'];
            private function boot(): void {}
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('COMMANDS', $judgment->warnings[0]->message);
    }

    public function test_flags_a_property_declared_after_a_method(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Provider {
            public function register(): void {}
            private int $count = 0;
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('count', $judgment->warnings[0]->message);
    }

    public function test_does_not_flag_constants_and_properties_already_at_the_top(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Provider {
            private const array COMMANDS = ['a'];
            private int $count = 0;
            public function register(): void {}
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_constructor_promoted_properties(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Provider {
            public function register(): void {}
            public function __construct(public readonly int $count = 0) {}
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_class_with_no_methods(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Config {
            public const A = 1;
            public const B = 2;
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_repent_hoists_misplaced_members_above_the_first_method(): void
    {
        $code = "<?php\nnamespace App;\n"
            . "class Provider {\n"
            . "    public function register(): void {}\n\n"
            . "    private const array COMMANDS = ['a'];\n\n"
            . "    private function boot(): void {}\n\n"
            . "    private int \$count = 0;\n"
            . "}\n";

        $judgment = $this->prophet->judge('/x.php', $code);
        $this->assertCount(2, $judgment->warnings);
        $this->assertTrue($judgment->warnings[0]->autoFixable);

        $result = $this->prophet->repent('/x.php', $code);
        $this->assertTrue($result->absolved);

        $fixed = (string) $result->newContent;

        // Both declarations now sit before the first method.
        $constPos = strpos($fixed, 'COMMANDS');
        $propPos = strpos($fixed, '$count');
        $methodPos = strpos($fixed, 'function register');

        $this->assertNotFalse($constPos);
        $this->assertNotFalse($propPos);
        $this->assertLessThan($methodPos, $constPos);
        $this->assertLessThan($methodPos, $propPos);

        // Relative order of the hoisted members is preserved.
        $this->assertLessThan($propPos, $constPos);

        // The fixed file is still valid PHP and reports nothing further.
        $this->assertTrue($this->prophet->judge('/x.php', $fixed)->isRighteous());
    }

    public function test_carries_the_docblock_when_hoisting(): void
    {
        $code = "<?php\nnamespace App;\n"
            . "class Provider {\n"
            . "    public function register(): void {}\n\n"
            . "    /** @var list<string> */\n"
            . "    private const array COMMANDS = ['a'];\n"
            . "}\n";

        $result = $this->prophet->repent('/x.php', $code);
        $fixed = (string) $result->newContent;

        $docPos = strpos($fixed, '@var list<string>');
        $methodPos = strpos($fixed, 'function register');

        $this->assertNotFalse($docPos);
        $this->assertLessThan($methodPos, $docPos);
    }
}
