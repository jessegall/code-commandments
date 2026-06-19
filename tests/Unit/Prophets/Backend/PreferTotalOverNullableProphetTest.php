<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferTotalOverNullableProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferTotalOverNullableProphetTest extends TestCase
{
    private PreferTotalOverNullableProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferTotalOverNullableProphet;
    }

    public function test_flags_native_nullable_when_every_caller_de_nulls(): void
    {
        $judgment = $this->judge('class C {
            private function root(): ?Node { return $this->r; }
            public function a() { return $this->root() ?? throw new \RuntimeException("x"); }
            public function b() { return $this->root()->id; }
        }');

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('root()', $judgment->warnings[0]->message);
    }

    public function test_flags_option_when_every_caller_unwraps(): void
    {
        $judgment = $this->judge('class C {
            private function find(): Option { return $this->o; }
            public function a() { return $this->find()->getOrThrow(); }
            public function b() { return $this->find()->unwrap()->id; }
        }');

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_leaves_option_when_a_caller_supplies_a_default(): void
    {
        // `->getOr($default)` HANDLES the absence — the Option is earned, not a de-null.
        $this->assertFalse($this->judge('class C {
            private function find(): Option { return $this->o; }
            public function a() { return $this->find()->getOr(null); }
            public function b() { return $this->find()->getOrThrow(); }
        }')->hasWarnings());
    }

    public function test_leaves_option_when_a_caller_maps(): void
    {
        $this->assertFalse($this->judge('class C {
            private function find(): Option { return $this->o; }
            public function a() { return $this->find()->map(fn ($x) => $x->id); }
        }')->hasWarnings());
    }

    public function test_leaves_nullable_when_a_caller_supplies_a_real_default(): void
    {
        $this->assertFalse($this->judge('class C {
            private function root(): ?Node { return $this->r; }
            public function a() { return $this->root() ?? throw new \Ex; }
            public function b() { return $this->root() ?? new NullNode; }
        }')->hasWarnings());
    }

    public function test_leaves_nullable_when_a_caller_branches_on_null(): void
    {
        $this->assertFalse($this->judge('class C {
            private function root(): ?Node { return $this->r; }
            public function a() { return $this->root()->id; }
            public function b() { $x = $this->root(); if ($x === null) { return 0; } return $x->id; }
        }')->hasWarnings());
    }

    public function test_leaves_nullable_when_a_caller_uses_nullsafe(): void
    {
        $this->assertFalse($this->judge('class C {
            private function root(): ?Node { return $this->r; }
            public function a() { return $this->root()?->id; }
        }')->hasWarnings());
    }

    public function test_leaves_a_public_method(): void
    {
        // Callers may live in another file — "every caller de-nulls" is not provable.
        $this->assertFalse($this->judge('class C {
            public function root(): ?Node { return $this->r; }
            public function a() { return $this->root()->id; }
        }')->hasWarnings());
    }

    public function test_leaves_a_non_nullable_method(): void
    {
        $this->assertFalse($this->judge('class C {
            private function root(): Node { return $this->r; }
            public function a() { return $this->root()->id; }
        }')->hasWarnings());
    }

    public function test_leaves_when_there_are_no_callers(): void
    {
        $this->assertFalse($this->judge('class C {
            private function root(): ?Node { return $this->r; }
        }')->hasWarnings());
    }

    public function test_scalar_empty_identity_suggests_the_zero_value(): void
    {
        // ?string with an empty identity ('') — fires even though the caller
        // TOLERATES the absence (?? 'x'), because the empty value removes the hedge.
        $judgment = $this->judge('class C {
            private function label(): ?string { return $this->l; }
            public function a() { return $this->label() ?? "x"; }
        }');

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString("''", $judgment->warnings[0]->message);
        $this->assertStringContainsString('empty identity', $judgment->warnings[0]->message);
    }

    public function test_array_empty_identity(): void
    {
        $judgment = $this->judge('class C {
            private function rows(): ?array { return $this->r; }
            public function a() { foreach ($this->rows() ?? [] as $x) {} }
        }');

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('[]', $judgment->warnings[0]->message);
    }

    public function test_fluent_subclass_empty_identity_in_file(): void
    {
        $judgment = $this->judge('class ValueBag extends \Illuminate\Support\Fluent {}
        class C {
            private function decode(): ?ValueBag { return $this->v; }
            public function a() { return $this->decode()?->get("x"); }
        }');

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('new ValueBag', $judgment->warnings[0]->message);
    }

    public function test_class_with_static_empty_identity(): void
    {
        $judgment = $this->judge('class Money { public static function empty(): self { return new self; } }
        class C {
            private function total(): ?Money { return $this->m; }
            public function a() { return $this->total() ?? throw new \RuntimeException("x"); }
        }');

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('Money::empty()', $judgment->warnings[0]->message);
    }

    public function test_option_with_empty_identity_inner_via_docblock(): void
    {
        $judgment = $this->judge('class ValueBag extends \Illuminate\Support\Fluent {}
        class C {
            /** @return Option<ValueBag> */
            private function decode(): Option { return $this->v; }
            public function a() { return $this->decode()->getOr(new ValueBag); }
        }');

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('new ValueBag', $judgment->warnings[0]->message);
        $this->assertStringContainsString('Option<T>', $judgment->warnings[0]->message);
    }

    public function test_no_empty_identity_keeps_strict_trigger(): void
    {
        // A class with a required-arg constructor, not Fluent, no empty()/make():
        // no empty identity → the strict "every caller de-nulls" trigger applies,
        // so a tolerant caller means NO finding.
        $judgment = $this->judge('class Node { public function __construct(public int $id) {} }
        class C {
            private function root(): ?Node { return $this->r; }
            public function a() { return $this->root() ?? $this->fallback(); }
        }');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_describes_itself(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertNotNull($this->prophet->advisory());
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\nnamespace App;\n" . $body);
    }
}
