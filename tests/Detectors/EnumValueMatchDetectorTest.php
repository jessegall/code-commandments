<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\EnumValueMatchDetector;
use PHPUnit\Framework\TestCase;

final class EnumValueMatchDetectorTest extends TestCase
{
    public function test_flags_a_match_on_an_enum_value_at_a_call_site_only(): void
    {
        $code = <<<'PHP'
        <?php
        class Presenter
        {
            public function badge($order): string
            {
                return match ($order->status->value) {
                    'pending' => 'grey',
                    'paid' => 'green',
                };
            }

            public function label($order): string
            {
                return $order->status->label();
            }
        }

        enum Status: string
        {
            case Pending = 'pending';
            case Paid = 'paid';

            public function colour(): string
            {
                return match ($this) {
                    self::Pending => 'grey',
                    self::Paid => 'green',
                };
            }
        }
        PHP;

        $hits = (new EnumValueMatchDetector)->find(Codebase::fromString($code));

        $this->assertSame(['Presenter::badge'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
