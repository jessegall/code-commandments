<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\OptionDisciplineProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use PHPUnit\Framework\TestCase;

/**
 * One prophet owns the null↔Option decision. Its return-type branch makes the
 * verdicts disjoint (a method gets at most one), so it can never contradict itself
 * — the failure that made the old PreferOptionOverNull ⇄ NoOptionOveruse pair
 * ping-pong. Unwrapping/branching on an Option is explicitly NOT a smell.
 */
class OptionDisciplineProphetTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc-optdisc-' . uniqid();
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

    private function write(string $name, string $php): void
    {
        file_put_contents($this->dir . '/' . $name, $php);
    }

    public function test_does_not_fire_on_a_nullable_list_return_issue_221(): void
    {
        // inCategory() returns a LIST (or null) — its natural absence is `[]`, not
        // Option::none(). That is PreferEmptyOverNull's domain; OptionDiscipline must
        // not fire (the @return docblock signals the array result).
        $this->write('CatalogService.php', "<?php\nnamespace App\\Catalog;\nclass CatalogService {\n  /** @return array<int, mixed>|null */\n  public function inCategory(\$key) {\n    if (\$key === null) { return null; }\n    \$out = [];\n    foreach ([1,2] as \$p) { \$out[] = \$p; }\n    return \$out;\n  }\n}\n");

        $j = $this->judge('CatalogService.php');

        $this->assertCount(0, $j->sins);
        $this->assertCount(0, $j->warnings, 'A nullable list/array return belongs to PreferEmptyOverNull, not OptionDiscipline.');
    }

    public function test_does_not_fire_on_a_declared_array_nullable_return(): void
    {
        $this->write('Repo.php', "<?php\nnamespace App\\X;\nclass Repo {\n  public function find(\$k): ?array {\n    if (\$k === null) { return null; }\n    return ['a'];\n  }\n}\n");

        $j = $this->judge('Repo.php');
        $this->assertCount(0, $j->warnings);
        $this->assertCount(0, $j->sins);
    }

    /**
     * Judge one named file against the whole written dir (so cross-file callers
     * resolve through the index).
     */
    private function judge(string $name): Judgment
    {
        $files = glob($this->dir . '/*.php') ?: [];
        $prophet = new OptionDisciplineProphet();
        $prophet->setCodebaseIndex(CodebaseIndex::build($files));

        $path = $this->dir . '/' . $name;

        return $prophet->judge($path, (string) file_get_contents($path));
    }

    // ── Case A: decides-null ⇒ adopt ────────────────────────────────────

    public function test_case_a_flags_a_decides_null_method_with_branching_callers(): void
    {
        $this->write('Repo.php', <<<'PHP'
        <?php
        namespace App;
        class Repo {
            public function lookup(string $id): string|null {
                foreach (['a'] as $x) { if ($x === $id) { return $x; } }
                return null;
            }
        }
        PHP);
        $this->write('A.php', '<?php namespace App; class A { public function __construct(private Repo $r){} public function go(string $id): void { $x = $this->r->lookup($id); if ($x === null) { return; } echo $x; } }');
        $this->write('B.php', '<?php namespace App; class B { public function __construct(private Repo $r){} public function go(string $id): void { $x = $this->r->lookup($id); if ($x === null) { return; } echo $x; } }');

        $j = $this->judge('Repo.php');

        $this->assertTrue($j->hasWarnings());
        $this->assertStringContainsString('decides nothingness', $j->warnings[0]->message);
    }

    public function test_case_a_is_suppressed_below_min_callers(): void
    {
        $this->write('Repo.php', <<<'PHP'
        <?php
        namespace App;
        class Repo {
            public function lookup(string $id): string|null {
                foreach (['a'] as $x) { if ($x === $id) { return $x; } }
                return null;
            }
        }
        PHP);
        // A single branching caller — below the default min_callers of 2.
        $this->write('A.php', '<?php namespace App; class A { public function __construct(private Repo $r){} public function go(string $id): void { $x = $this->r->lookup($id); if ($x === null) { return; } echo $x; } }');

        $this->assertFalse($this->judge('Repo.php')->hasWarnings(), 'a lone nearby null check is not worth an Option');
    }

    // ── Case B: always-some ─────────────────────────────────────────────

    public function test_case_b_flags_an_option_that_is_never_none(): void
    {
        $this->write('Lib.php', <<<'PHP'
        <?php
        namespace App;
        use JesseGall\PhpTypes\Option;
        class Lib {
            private int $v = 1;
            public function current(): Option { return Option::some($this->v); }
        }
        PHP);

        $j = $this->judge('Lib.php');

        $this->assertTrue($j->hasWarnings());
        $this->assertStringContainsString('never empty', $j->warnings[0]->message);
    }

    public function test_case_b_silent_when_a_real_none_path_exists(): void
    {
        $this->write('Lib.php', <<<'PHP'
        <?php
        namespace App;
        use JesseGall\PhpTypes\Option;
        class Lib {
            public function find(string $id): Option {
                if ($id === '') { return Option::none(); }
                return Option::some($id);
            }
        }
        PHP);

        $this->assertFalse($this->judge('Lib.php')->hasWarnings());
    }

    public function test_case_b_silent_when_overriding_an_option_returning_contract(): void
    {
        // A handler whose `: Option` return is imposed by an interface (whose other
        // implementors legitimately return none()). This single always-some override
        // cannot be retyped — flagging it is a false positive.
        $this->write('Handler.php', <<<'PHP'
        <?php
        namespace App;
        use JesseGall\PhpTypes\Option;
        interface Handler {
            public function intent(): Option;
        }
        final class ClearHandler implements Handler {
            public function intent(): Option { return Option::some('clear'); }
        }
        PHP);

        $this->assertFalse($this->judge('Handler.php')->hasWarnings(), 'an Option override is contract-locked, not overuse');
    }

    // ── Case D: construct-then-unwrap ───────────────────────────────────

    public function test_case_d_flags_wrap_then_unwrap(): void
    {
        $this->write('Lib.php', <<<'PHP'
        <?php
        namespace App;
        use JesseGall\PhpTypes\Option;
        class Lib {
            public function greet(string $x): string { return Option::some($x)->unwrap(); }
        }
        PHP);

        $j = $this->judge('Lib.php');

        $this->assertTrue($j->hasWarnings());
        $this->assertStringContainsString('immediately unwrapped', $j->warnings[0]->message);
    }

    // ── The no-contradiction guarantee ──────────────────────────────────

    public function test_silent_on_a_justified_option_whose_callers_unwrap(): void
    {
        // The old schemaShape() shape: an Option with a REAL none() path whose
        // callers unwrap / branch on it. The retired smell #3 flagged this; the
        // unified prophet must stay SILENT — that is the whole point.
        $this->write('Shapes.php', <<<'PHP'
        <?php
        namespace App;
        use JesseGall\PhpTypes\Option;
        class Shapes {
            public function shape(string $slug): Option {
                if ($slug === '') { return Option::none(); }
                return Option::some(['type' => $slug]);
            }
        }
        PHP);
        $this->write('Caller.php', <<<'PHP'
        <?php
        namespace App;
        class Caller {
            public function __construct(private Shapes $s) {}
            public function a(string $slug): array { $o = $this->s->shape($slug); if ($o->isNone()) { return []; } return $o->unwrap(); }
            public function b(string $slug): array { return $this->s->shape($slug)->unwrapOr(['type' => 'object']); }
        }
        PHP);

        $this->assertFalse($this->judge('Shapes.php')->hasWarnings(), 'unwrapping/branching a genuine Option is normal — never the smell');
    }

    public function test_a_method_never_earns_two_verdicts(): void
    {
        // A returns-null method (case A territory) can never also be case B (it is
        // not typed `: Option`). Assert exactly one finding kind per method.
        $this->write('Repo.php', <<<'PHP'
        <?php
        namespace App;
        class Repo {
            public function lookup(string $id): string|null {
                foreach (['a'] as $x) { if ($x === $id) { return $x; } }
                return null;
            }
        }
        PHP);
        $this->write('A.php', '<?php namespace App; class A { public function __construct(private Repo $r){} public function go(string $id): void { $x = $this->r->lookup($id); if ($x === null) { return; } echo $x; } }');
        $this->write('B.php', '<?php namespace App; class B { public function __construct(private Repo $r){} public function go(string $id): void { $x = $this->r->lookup($id); if ($x === null) { return; } echo $x; } }');

        $j = $this->judge('Repo.php');

        $this->assertCount(1, $j->warnings, 'one method, one verdict');
    }

    // ── Exemptions ──────────────────────────────────────────────────────

    public function test_exempts_a_request_boundary_parser(): void
    {
        $this->write('Form.php', <<<'PHP'
        <?php
        namespace App;
        class Form {
            public function name(): string|null {
                $v = $this->input('name');
                return is_string($v) ? $v : null;
            }
            private function input(string $k): mixed { return null; }
        }
        PHP);

        $this->assertFalse($this->judge('Form.php')->hasWarnings(), 'request-boundary null is the HTTP idiom, not a domain decision');
    }

    public function test_does_not_flag_the_option_class_itself(): void
    {
        // exemptClasses() points at the configured Option — judging it is skipped
        // by the framework, but a same-named domain class is still judged. Here we
        // only assert the prophet has the exemption wired.
        $this->assertContains('JesseGall\\PhpTypes\\Option', (new OptionDisciplineProphet())->exemptClasses());
    }
}
