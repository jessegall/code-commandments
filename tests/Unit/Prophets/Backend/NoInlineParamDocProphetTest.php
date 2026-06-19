<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoInlineParamDocProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoInlineParamDocProphetTest extends TestCase
{
    private NoInlineParamDocProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoInlineParamDocProphet;
    }

    public function test_flags_inline_var_on_a_promoted_constructor_param(): void
    {
        $j = $this->judge('class Spec {
            public function __construct(
                public string $name,
                /** @var list<string>|null */
                public array | null $options = null,
            ) {}
        }');

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString('@param list<string>|null $options', $j->warnings[0]->message);
        $this->assertStringContainsString("constructor's docblock", $j->warnings[0]->message);
    }

    public function test_flags_inline_var_on_a_plain_function_param(): void
    {
        $j = $this->judge('function f(
            /** @var array<string, int> */
            array $counts
        ): void {}');

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString('@param array<string, int> $counts', $j->warnings[0]->message);
    }

    public function test_flags_each_inline_param_separately(): void
    {
        $j = $this->judge('class Spec {
            public function __construct(
                /** @var list<int> */
                public array $ids,
                /** @var array<string,string> */
                public array $meta,
            ) {}
        }');

        $this->assertCount(2, $j->warnings);
    }

    public function test_ignores_params_without_a_docblock(): void
    {
        $this->assertTrue($this->judge('class Spec {
            public function __construct(
                public string $name,
                public array | null $options = null,
            ) {}
        }')->isRighteous());
    }

    public function test_ignores_a_plain_non_var_comment(): void
    {
        // A plain explanatory note (no @var type) is not flagged.
        $this->assertTrue($this->judge('class Spec {
            public function __construct(
                /** the display name */
                public string $name,
            ) {}
        }')->isRighteous());
    }

    public function test_marks_autofixable(): void
    {
        $j = $this->judge('class Spec {
            public function __construct(
                /** @var list<string>|null */
                public array | null $options = null,
            ) {}
        }');

        $this->assertTrue($j->warnings[0]->autoFixable);
    }

    public function test_repent_creates_a_docblock(): void
    {
        $src = "<?php\nclass Spec {\n    public function __construct(\n        public string \$name,\n        /** @var list<string>|null */\n        public array | null \$options = null,\n    ) {}\n}\n";

        $result = $this->prophet->repent('/x.php', $src);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('@param list<string>|null $options', $result->newContent);
        $this->assertStringNotContainsString('@var', $result->newContent);
        // The created docblock sits above the constructor.
        $this->assertMatchesRegularExpression('/\/\*\*.*@param list<string>\|null \$options.*\*\/\s*public function __construct/s', $result->newContent);
        $this->assertNotNull($this->reparse($result->newContent), 'repented code must still parse');
    }

    public function test_repent_extends_an_existing_docblock(): void
    {
        $src = "<?php\nclass Spec {\n    /**\n     * Build a spec.\n     */\n    public function __construct(\n        /** @var list<string> */\n        public array \$ids,\n    ) {}\n}\n";

        $result = $this->prophet->repent('/x.php', $src);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('Build a spec.', $result->newContent);
        $this->assertStringContainsString('@param list<string> $ids', $result->newContent);
        $this->assertStringNotContainsString('@var', $result->newContent);
        $this->assertNotNull($this->reparse($result->newContent), 'repented code must still parse');
    }

    private function reparse(string $code): ?array
    {
        return (new \PhpParser\ParserFactory)->createForNewestSupportedVersion()->parse($code);
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n" . $body);
    }
}
