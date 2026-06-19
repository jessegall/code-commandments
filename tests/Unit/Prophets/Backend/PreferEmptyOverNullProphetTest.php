<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferEmptyOverNullProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferEmptyOverNullProphetTest extends TestCase
{
    private PreferEmptyOverNullProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferEmptyOverNullProphet;
    }

    public function test_flags_nullable_array_return(): void
    {
        $judgment = $this->judge('class A { public function rows(): array | null { return null; } }');

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('empty identity', $judgment->warnings[0]->message);
    }

    public function test_flags_nullable_collection_shorthand(): void
    {
        $judgment = $this->judge('class A { public function rows(): ?Collection { return null; } }');

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_flags_nullable_collection_property(): void
    {
        $judgment = $this->judge('class A { public Collection | null $items = null; }');

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_suppresses_a_return_whose_caller_distinguishes_null(): void
    {
        // #89: a caller that branches on the null (=== null / !== null / assign-
        // then-null-test) genuinely distinguishes ABSENT from EMPTY — suppress.
        // A caller that only iterates / `?? []` still fires (the true win).
        $dir = sys_get_temp_dir() . '/cc-pen89-' . uniqid();
        @mkdir($dir, 0755, true);

        file_put_contents("$dir/C.php", "<?php\nnamespace App;\nclass C { public function viteEntries(): array | null { return null; } public function rows(): array | null { return null; } }\n");
        file_put_contents("$dir/Caller.php", "<?php\nnamespace App;\nclass Caller { public function a(C \$c) { return \$c->viteEntries() !== null; } public function b(C \$c) { foreach (\$c->rows() ?? [] as \$r) {} } }\n");

        $index = \JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex::build(glob("$dir/*.php") ?: []);
        $prophet = new PreferEmptyOverNullProphet;
        $prophet->setCodebaseIndex($index);

        $symbols = array_map(static fn ($w): string => (string) $w->symbol, $prophet->judge("$dir/C.php", file_get_contents("$dir/C.php"))->warnings);

        $this->assertContains('prefer-empty:return:rows', $symbols, 'rows() is only iterated → still fires.');
        $this->assertNotContains('prefer-empty:return:viteEntries', $symbols, 'viteEntries() is null-tested by a caller → suppressed.');

        foreach (glob("$dir/*.php") ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }

    public function test_does_not_flag_a_private_lazy_init_memo_property(): void
    {
        // #86: a private nullable collection property is an internal lazy-init
        // memo where null ("not loaded") and [] ("loaded, empty") differ — leave
        // it. Public/protected collection properties are still the API contract.
        $this->assertTrue($this->judge('class R { private array | null $resources = null; }')->isRighteous());
        $this->assertCount(1, $this->judge('class R { public array | null $items = null; }')->warnings);
    }

    public function test_does_not_flag_scalar_or_single_object_nullable(): void
    {
        // Scalar / single-object absence is PreferOptionOverNull's Option case.
        $this->assertTrue($this->judge('class A { public function a(): ?string { return null; } }')->isRighteous());
        $this->assertTrue($this->judge('class A { public function b(): ?int { return null; } }')->isRighteous());
        $this->assertTrue($this->judge('class A { public function c(): ?User { return null; } }')->isRighteous());
    }

    public function test_does_not_flag_a_three_member_union(): void
    {
        // 3+ members defer to WideUnionType.
        $this->assertTrue($this->judge('class A { public function a(): Collection | array | null { return null; } }')->isRighteous());
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n" . $body);
    }
}
