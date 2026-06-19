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
