<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\MassUpdateAtCallSiteDetector;
use PHPUnit\Framework\TestCase;

final class MassUpdateAtCallSiteDetectorTest extends TestCase
{
    public function test_flags_a_call_site_mass_update_on_a_model_only(): void
    {
        $code = <<<'PHP'
        <?php
        namespace Illuminate\Database\Eloquent { class Model {} }
        namespace App {
            use Illuminate\Database\Eloquent\Model;
            class Order extends Model {
                public function markPaid(): void {
                    $this->update(['status' => 'paid']);
                }
            }
            class Plain {}
            class S {
                public function bad(Order $order): void {
                    $order->update(['status' => 'paid', 'paid_at' => 'now']);
                }
                public function nonModel(Plain $p): void {
                    $p->update(['x' => 1]);
                }
            }
        }
        PHP;

        $hits = (new MassUpdateAtCallSiteDetector)->find(Codebase::fromString($code));

        // bad: call-site update on a Model. markPaid: $this (the intention method).
        // nonModel: not a Model.
        $this->assertSame(['App\\S::bad'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
