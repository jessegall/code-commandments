<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PushGenericToSourceProphet;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Tests\TestCase;

class PushGenericToSourceProphetTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc-pgs-' . uniqid();
        @mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*.php') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    /**
     * @return list<int>  flagged line numbers in Caller.php
     */
    private function judgeCaller(string $callerBody): array
    {
        file_put_contents($this->dir . '/Reg.php', "<?php\nnamespace App;\nclass Option {}\nclass WidgetRegistry {\n /** @return Option */\n public function find(string \$k): Option { return new Option(); }\n /** @return Option<Widget> */\n public function good(string \$k): Option { return new Option(); }\n public function mixedReturn(string \$k): mixed { return null; }\n}\n");
        $caller = $this->dir . '/Caller.php';
        $src = "<?php\nnamespace App;\nclass Caller {\n public function __construct(private WidgetRegistry \$registry) {}\n{$callerBody}\n}\n";
        file_put_contents($caller, $src);

        $index = CodebaseIndex::build(glob($this->dir . '/*.php') ?: []);
        $prophet = new PushGenericToSourceProphet;
        $prophet->setCodebaseIndex($index);

        return array_map(static fn ($w): int => $w->line, $prophet->judge($caller, $src)->warnings);
    }

    public function test_flags_var_over_a_bare_generic_return(): void
    {
        $flagged = $this->judgeCaller(" public function a() {\n  /** @var Option<Widget> \$found */\n  \$found = \$this->registry->find(\"x\");\n  return \$found;\n }");

        $this->assertCount(1, $flagged);
    }

    public function test_flags_var_over_a_mixed_return(): void
    {
        $flagged = $this->judgeCaller(" public function a() {\n  /** @var array<string, Widget> \$w */\n  \$w = \$this->registry->mixedReturn(\"x\");\n  return \$w;\n }");

        $this->assertCount(1, $flagged);
    }

    public function test_leaves_an_already_parameterized_return(): void
    {
        $flagged = $this->judgeCaller(" public function a() {\n  /** @var Option<Widget> \$f */\n  \$f = \$this->registry->good(\"x\");\n  return \$f;\n }");

        $this->assertSame([], $flagged);
    }

    public function test_leaves_a_fallback_widened_call(): void
    {
        // `->getOr($default)` widens the result — the source @return can't fix it.
        $flagged = $this->judgeCaller(" public function a() {\n  /** @var Option<Widget> \$g */\n  \$g = \$this->registry->find(\"x\")->getOr(null);\n  return \$g;\n }");

        $this->assertSame([], $flagged);
    }

    public function test_leaves_a_var_that_names_a_different_variable(): void
    {
        // A `@var` for a foreach loop var that happens to sit above an assignment
        // is not about that assignment's call — the names must match.
        $flagged = $this->judgeCaller(" public function a(array \$widgets) {\n  foreach (\$widgets as \$cfg) {\n   /** @var Widget \$cfg */\n   \$value = \$this->registry->find(\"x\");\n  }\n }");

        $this->assertSame([], $flagged);
    }

    public function test_leaves_a_vendor_call(): void
    {
        // The receiver type isn't in the index → unannotatable → leave it.
        $caller = $this->dir . '/Vendor.php';
        $src = "<?php\nnamespace App;\nclass V {\n public function a(\\Illuminate\\Support\\Collection \$c) {\n  /** @var \\App\\Widget \$w */\n  \$w = \$c->first();\n  return \$w;\n }\n}\n";
        file_put_contents($caller, $src);
        $index = CodebaseIndex::build([$caller]);
        $prophet = new PushGenericToSourceProphet;
        $prophet->setCodebaseIndex($index);

        $this->assertTrue($prophet->judge($caller, $src)->isRighteous());
    }
}
