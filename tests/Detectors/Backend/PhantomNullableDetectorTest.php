<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\PhantomNullableDetector;
use PHPUnit\Framework\TestCase;

/**
 * {@see PhantomNullableDetector} names a nullable promoted field and reads the ValueFlow verdict —
 * flagging only when the value is assumed present everywhere and guarded nowhere.
 */
final class PhantomNullableDetectorTest extends TestCase
{
    /** @return list<string> the flagged field names */
    private function fields(string $php): array
    {
        $matches = new PhantomNullableDetector()->find(Codebase::fromString($php));

        return array_values(array_map(static fn ($m): string => $m->node->var->name, $matches));
    }

    public function test_flags_a_field_assumed_present_everywhere(): void
    {
        $php = <<<'PHP'
        <?php
        namespace App;
        class Inner { public function v(): int { return 1; } }
        class Holder { public function __construct(public readonly ?Inner $inner = null) {} }
        class Reader { public function go(Holder $h): int { return $h->inner->v(); } }
        PHP;

        $this->assertSame(['inner'], $this->fields($php));
    }

    public function test_does_not_flag_when_guarded_downstream(): void
    {
        $php = <<<'PHP'
        <?php
        namespace App;
        class Inner { public function v(): int { return 1; } }
        class Holder { public function __construct(public readonly ?Inner $inner = null) {} }
        class Wrapper {
            public function __construct(public readonly ?Inner $inner = null) {}
            public function safe(): bool { return $this->inner !== null; }
        }
        class Reader { public function go(Holder $h): Wrapper { return new Wrapper(inner: $h->inner); } }
        PHP;

        $this->assertSame([], $this->fields($php));
    }

    public function test_does_not_flag_a_non_nullable_field(): void
    {
        $php = <<<'PHP'
        <?php
        namespace App;
        class Inner { public function v(): int { return 1; } }
        class Holder { public function __construct(public readonly Inner $inner = new Inner()) {} }
        class Reader { public function go(Holder $h): int { return $h->inner->v(); } }
        PHP;

        $this->assertSame([], $this->fields($php));
    }

    public function test_flags_an_inherited_field_read_through_a_subclass(): void
    {
        // The field is declared on the base but read through a subclass receiver — the read must
        // still reach the base field's verdict, not fall into an orphan bucket.
        $php = <<<'PHP'
        <?php
        namespace App;
        class Money { public function cents(): int { return 1; } }
        class Base { public function __construct(public readonly ?Money $total = null) {} }
        class Sub extends Base {}
        class Reader { public function go(Sub $s): int { return $s->total->cents(); } }
        PHP;

        $this->assertSame(['total'], $this->fields($php));
    }

    public function test_does_not_flag_a_field_with_no_reads(): void
    {
        $php = <<<'PHP'
        <?php
        namespace App;
        class Inner {}
        class Holder { public function __construct(public readonly ?Inner $inner = null) {} }
        PHP;

        $this->assertSame([], $this->fields($php));
    }
}
