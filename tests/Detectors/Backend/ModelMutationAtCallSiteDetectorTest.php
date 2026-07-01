<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Laravel\ModelMutationAtCallSiteDetector;
use PHPUnit\Framework\TestCase;

final class ModelMutationAtCallSiteDetectorTest extends TestCase
{
    public function test_flags_set_then_save_only(): void
    {
        $code = <<<'PHP'
        <?php
        namespace Illuminate\Database\Eloquent { class Model {} }
        namespace App {
            use Illuminate\Database\Eloquent\Model;
            class Order extends Model {}
            class Draft {}
            class S {
                public function bad(Order $order) {
                    $order->status = 'paid';
                    $order->save();
                }
                public function good(Order $order) {
                    $order->markPaid();
                    $order->save();
                }
                public function nonModel(Draft $draft) {
                    $draft->name = 'x';
                    $draft->save();
                }
            }
        }
        PHP;

        $hits = (new ModelMutationAtCallSiteDetector)->find(Codebase::fromString($code));

        // bad: model set-then-save. good: no property write. nonModel: not a Model.
        $this->assertSame(['App\\S::bad'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
