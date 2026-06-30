<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\MessageAtThrowDetector;
use PHPUnit\Framework\TestCase;

final class MessageAtThrowDetectorTest extends TestCase
{
    public function test_flags_a_message_string_at_the_throw_site_only(): void
    {
        $code = <<<'PHP'
        <?php
        class S {
            public function a(int $id) { throw new \RuntimeException("order {$id} is gone"); }
            public function b() { throw new DomainError("plain message"); }
            public function c(int $id) { throw OrderMissing::forId($id); }
            public function d(string $code) { throw new DomainError($code); }
        }
        PHP;

        $hits = (new MessageAtThrowDetector)->find(Codebase::fromString($code));
        $scopes = array_map(static fn ($m): string => $m->scope(), $hits);
        sort($scopes);

        // a (interpolated) + b (literal). c is a named factory; d passes a value.
        $this->assertSame(['S::a', 'S::b'], $scopes);
    }
}
