<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\NullableCallbackDetector;
use PHPUnit\Framework\TestCase;

final class NullableCallbackDetectorTest extends TestCase
{
    public function test_flags_a_null_guarded_callback_that_gets_invoked(): void
    {
        $code = <<<'PHP'
        <?php
        class S {
            public function notify(string $msg, ?callable $onSent = null): void {
                if ($onSent !== null) {
                    $onSent($msg);
                }
            }
            public function union(string $msg, callable | null $hook = null): void {
                ($hook ?? static fn () => null)($msg);
            }
            public function stored(callable | null $cb = null): void {
                if ($cb === null) {
                    return;
                }
                $this->handlers[] = $cb;
            }
            public function required(string $msg, callable $always): void {
                $always($msg);
            }
        }
        PHP;

        $hits = (new NullableCallbackDetector)->find(Codebase::fromString($code));
        $scopes = array_map(static fn ($m): string => $m->scope(), $hits);
        sort($scopes);

        $this->assertSame(['S::notify', 'S::union'], $scopes);
    }
}
