<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\PhantomNullableDetector;
use PhpParser\Node\Param;
use PHPUnit\Framework\TestCase;

/**
 * {@see PhantomNullableDetector} names a nullable field — a promoted param OR a declared property —
 * and reads the ValueFlow verdict, flagging only when the value is assumed present everywhere and
 * guarded nowhere.
 */
final class PhantomNullableDetectorTest extends TestCase
{
    /** @return list<string> the flagged field names */
    private function fields(string $php): array
    {
        $matches = new PhantomNullableDetector()->find(Codebase::fromString($php));

        return array_values(array_map(
            static fn ($m): string => $m->node instanceof Param ? $m->node->var->name : (string) $m->node->name,
            $matches,
        ));
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

    public function test_flags_a_declared_property_not_just_a_promoted_param(): void
    {
        // A plain declared `public ?T $x` (not constructor-promoted) — a mutable pipeline-context
        // property set later and then assumed present — is a phantom too.
        $php = <<<'PHP'
        <?php
        namespace App;
        class Bytes { public function size(): int { return 1; } }
        class Context {
            public ?Bytes $payload = null;
            public function __construct(public readonly string $disk) {}
        }
        class WritePipe { public function run(Context $ctx): int { return $ctx->payload->size(); } }
        PHP;

        $this->assertSame(['payload'], $this->fields($php));
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

    public function test_does_not_flag_a_field_guarded_through_a_docblocked_collection(): void
    {
        // Issue #449: the field IS assumed present in one place (`Labels::of`), which is what puts it in
        // the frame — but it is guarded in another class, behind `foreach ($order->payments as $payment)`.
        // PHP types the container and stops there, so the loop variable went unresolved and that guard
        // was never counted; the field read as never-guarded and was flagged.
        $php = <<<'PHP'
        <?php
        namespace App;
        class Money { public function cents(): int { return 1; } }
        class Payment {
            public function __construct(
                public readonly ?Money $total = null,
            ) {}
        }
        class Order { /** @var list<Payment> */ public array $payments = []; }
        class Labels {
            public function of(Payment $payment): int { return $payment->total->cents(); }
        }
        class Refunds {
            public function pick(Order $order): ?Payment {
                foreach ($order->payments as $payment) {
                    if ($payment->total === null) { continue; }

                    return $payment;
                }

                return null;
            }
        }
        PHP;

        $this->assertSame([], $this->fields($php), 'a guard behind a docblocked collection still counts');
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

    public function test_does_not_flag_a_promoted_param_declaring_null_on_its_wire_type(): void
    {
        // Issue #313: the same shape as `test_flags_a_field_assumed_present_everywhere` — `inner` is
        // read unguarded — but the field declares null as part of its serialized contract, so the null
        // is intentional, not a phantom. Without the attribute this field IS flagged (that test); with
        // it, it must not be.
        $php = <<<'PHP'
        <?php
        namespace App;
        class Inner { public function v(): int { return 1; } }
        class Holder {
            public function __construct(
                #[LiteralTypeScriptType('InnerData | null')]
                public readonly ?Inner $inner = null,
            ) {}
        }
        class Reader { public function go(Holder $h): int { return $h->inner->v(); } }
        PHP;

        $this->assertSame([], $this->fields($php), 'a field that declares null on its wire contract is not a phantom');
    }

    public function test_does_not_flag_a_declared_property_declaring_null_on_its_wire_type(): void
    {
        $php = <<<'PHP'
        <?php
        namespace App;
        class Inner { public function v(): int { return 1; } }
        class Holder {
            #[LiteralTypeScriptType('InnerData | null')]
            public readonly ?Inner $inner;
            public function __construct() { $this->inner = null; }
        }
        class Reader { public function go(Holder $h): int { return $h->inner->v(); } }
        PHP;

        $this->assertSame([], $this->fields($php), 'the attribute is read off the declared property too');
    }
}
