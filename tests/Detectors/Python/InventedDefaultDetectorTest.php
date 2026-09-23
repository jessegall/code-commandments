<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\InventedDefaultDetector;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\ExprMatch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InventedDefaultDetectorTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function invented(): iterable
    {
        yield 'an empty string, positional' => ["send(order.email or '')\n"];
        yield 'a zero, keyword' => ["charge(amount=order.total or 0)\n"];
        yield 'False' => ["flag(order.paid or False)\n"];
        yield 'inside a conversion' => ["label = str(row.get('name') or '')\n"];
    }

    #[DataProvider('invented')]
    public function test_flags_an_empty_scalar_invented_into_an_argument(string $source): void
    {
        $this->assertCount(1, $this->findIn($source));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'a real default' => ["send(order.currency or 'EUR')\n"];
        yield 'an empty collection' => ["render(order.lines or [])\n"];
        yield 'None passed on' => ["send(order.email or None)\n"];
        yield 'not an argument' => ["email = order.email or ''\n"];
        yield 'the old conditional' => ["mode(reading and 'r' or '')\n"];
        yield 'the callee itself' => ["(handler or '')()\n"];
    }

    #[DataProvider('notThisSin')]
    public function test_leaves_real_defaults_and_other_shapes(string $source): void
    {
        $this->assertSame([], $this->findIn($source));
    }

    /**
     * @return list<ExprMatch>
     */
    private function findIn(string $source): array
    {
        return new InventedDefaultDetector()->find(Codebase::fromString($source));
    }
}
