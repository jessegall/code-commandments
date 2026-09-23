<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\MessageStringRaiseDetector;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MessageStringRaiseDetectorTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function generic(): iterable
    {
        yield 'RuntimeError with a literal' => ["raise RuntimeError('no active request')\n"];
        yield 'Exception with an f-string' => ["raise Exception(f'order {n} is locked')\n"];
        yield 'BaseException, implicitly concatenated' => ["raise BaseException('stop ' 'now')\n"];
    }

    #[DataProvider('generic')]
    public function test_flags_a_builtin_that_names_nothing_raised_with_prose(string $source): void
    {
        $this->assertCount(1, $this->findIn($source));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'a specific builtin category' => ["raise ValueError(f'quantity must be positive, got {q}')\n"];
        yield 'a type check' => ["raise TypeError('expected a str')\n"];
        yield 'a named failure' => ["raise OrderLocked.for_(n)\n"];
        yield 'a named failure with a message' => ["raise OrderLocked('locked')\n"];
        yield 'a generic without a message' => ["raise RuntimeError\n"];
        yield 'a generic wrapping a value' => ["raise RuntimeError(reason)\n"];
        yield 'a bare re-raise' => ["try:\n    x()\nexcept Exception:\n    raise\n"];
    }

    #[DataProvider('notThisSin')]
    public function test_leaves_named_and_specific_failures(string $source): void
    {
        $this->assertSame([], $this->findIn($source));
    }

    /**
     * @return list<NodeMatch>
     */
    private function findIn(string $source): array
    {
        return new MessageStringRaiseDetector()->find(Codebase::fromString($source));
    }
}
