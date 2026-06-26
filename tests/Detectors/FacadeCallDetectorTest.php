<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\FacadeCallDetector;
use PHPUnit\Framework\TestCase;

final class FacadeCallDetectorTest extends TestCase
{
    public function test_flags_a_facade_static_call_only(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App;

        use Illuminate\Support\Facades\Cache;
        use App\Support\Money;

        class Service
        {
            public function cached(): string
            {
                return Cache::get('k', 'd');
            }

            public function priced(): Money
            {
                return Money::zero();
            }
        }
        PHP;

        $hits = (new FacadeCallDetector)->find(Codebase::fromString($code));

        $this->assertSame(['App\\Service::cached'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
