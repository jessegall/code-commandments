<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoRedundantOrElseWrapProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoRedundantOrElseWrapProphetTest extends TestCase
{
    private NoRedundantOrElseWrapProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoRedundantOrElseWrapProphet;
    }

    public function test_flags_some_wrap_in_arrow_orelse(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C
        {
            public function go($id): int
            {
                return $this->find($id)
                    ->orElse(fn () => Option::some($this->fallback($id)))
                    ->getOrThrow();
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('hand-wraps', $judgment->warnings[0]->message);
        $this->assertTrue($judgment->warnings[0]->autoFixable);
    }

    public function test_flags_make_wrap(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C
        {
            public function go($id): int
            {
                return $this->find($id)->orElse(fn () => Option::make($this->fallback($id)))->getOrThrow();
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_flags_single_return_closure(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C
        {
            public function go($id): int
            {
                return $this->find($id)->orElse(function () use ($id) {
                    return Option::some($this->fallback($id));
                })->getOrThrow();
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_matches_fully_qualified_option(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C
        {
            public function go($id): int
            {
                return $this->find($id)->orElse(fn () => \App\Support\Option::some($id))->getOrThrow();
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_leaves_none_alone(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C
        {
            public function go($id): int
            {
                return $this->find($id)->orElse(fn () => Option::none())->getOrThrow();
            }
        }
        PHP);

        $this->assertCount(0, $judgment->warnings);
    }

    public function test_leaves_conditional_option_alone(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C
        {
            public function go($id): int
            {
                return $this->find($id)->orElse(fn () => Option::when($id > 0, fn () => $id))->getOrThrow();
            }
        }
        PHP);

        $this->assertCount(0, $judgment->warnings);
    }

    public function test_leaves_bare_value_alternative_alone(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C
        {
            public function go($id): int
            {
                return $this->find($id)->orElse(fn () => $this->fallback($id))->getOrThrow();
            }
        }
        PHP);

        $this->assertCount(0, $judgment->warnings);
    }

    public function test_leaves_unrelated_method_alone(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C
        {
            public function go($id): int
            {
                return $this->find($id)->transform(fn () => Option::some($id))->getOrThrow();
            }
        }
        PHP);

        $this->assertCount(0, $judgment->warnings);
    }

    public function test_repent_unwraps_some_in_arrow(): void
    {
        $src = "<?php\nclass C {\n public function m(\$o, \$id) {\n  return \$o->orElse(fn () => Option::some(\$this->fallback(\$id)))->getOrThrow();\n }\n}\n";

        $result = $this->prophet->repent('/x.php', $src);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('->orElse(fn () => $this->fallback($id))->getOrThrow()', $result->newContent);
        $this->assertStringNotContainsString('Option::some', $result->newContent);
    }

    public function test_repent_unwraps_make_in_closure(): void
    {
        $src = "<?php\nclass C {\n public function m(\$o, \$id) {\n  return \$o->orElse(function () use (\$id) { return Option::make(\$id); })->getOrThrow();\n }\n}\n";

        $result = $this->prophet->repent('/x.php', $src);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('return $id;', $result->newContent);
        $this->assertStringNotContainsString('Option::make', $result->newContent);
    }

    public function test_repent_strips_stale_closure_return_type_hint(): void
    {
        // #101: unwrapping must also drop the closure's `: Option` hint, else the
        // closure declares a type its bare body no longer returns (a type lie).
        $src = "<?php\nclass C {\n public function m(\$o, \$id) {\n  return \$o->orElse(fn (): Option => Option::some(\$this->respondError(\$id)))->getOrThrow();\n }\n}\n";

        $result = $this->prophet->repent('/x.php', $src);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('->orElse(fn () => $this->respondError($id))->getOrThrow()', $result->newContent);
        $this->assertStringNotContainsString(': Option', $result->newContent);
        $this->assertStringNotContainsString('Option::some', $result->newContent);
    }

    public function test_repent_strips_nullable_return_type_hint(): void
    {
        $src = "<?php\nclass C {\n public function m(\$o, \$id) {\n  return \$o->orElse(fn (): ?Option => Option::make(\$id))->getOrThrow();\n }\n}\n";

        $result = $this->prophet->repent('/x.php', $src);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('->orElse(fn () => $id)->getOrThrow()', $result->newContent);
        $this->assertStringNotContainsString('Option', $result->newContent);
    }

    public function test_repent_strips_return_type_on_closure(): void
    {
        $src = "<?php\nclass C {\n public function m(\$o, \$id) {\n  return \$o->orElse(function () use (\$id): Option { return Option::some(\$id); })->getOrThrow();\n }\n}\n";

        $result = $this->prophet->repent('/x.php', $src);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('function () use ($id) {', $result->newContent);
        $this->assertStringContainsString('return $id;', $result->newContent);
        $this->assertStringNotContainsString(': Option', $result->newContent);
    }

    public function test_repent_keeps_untyped_closure_intact(): void
    {
        // No return-type hint to begin with — only the wrap is removed.
        $src = "<?php\nclass C {\n public function m(\$o, \$id) {\n  return \$o->orElse(fn () => Option::some(\$id))->getOrThrow();\n }\n}\n";

        $result = $this->prophet->repent('/x.php', $src);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('->orElse(fn () => $id)->getOrThrow()', $result->newContent);
    }

    public function test_repent_leaves_none_untouched(): void
    {
        $src = "<?php\nclass C {\n public function m(\$o) {\n  return \$o->orElse(fn () => Option::none())->getOrThrow();\n }\n}\n";

        $this->assertFalse($this->prophet->repent('/x.php', $src)->absolved);
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n" . $body);
    }
}
