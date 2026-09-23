<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\SubjectLadderDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Four rungs or more comparing one subject with a constant are a dispatch written as a ladder — whether
 * the constant is a literal, an enum member or a `const`, and whether the rung says `==` or `is`. A
 * ladder whose rungs test different things, or compare with something computed, is left alone.
 */
final class SubjectLadderDetectorTest extends TestCase
{
    use NeedsTheBridge;

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function ladders(): array
    {
        return [
            'enum members' => ['if (s == Status.Paid) { n = 1; } else if (s == Status.Late) { n = 2; } else if (s == Status.Void) { n = 3; } else if (s == Status.Open) { n = 4; }', 1],
            'literals, the constant on either side' => ['if (code == "a") { n = 1; } else if ("b" == code) { n = 2; } else if (code == "c") { n = 3; } else if (code == "d") { n = 4; }', 1],
            'is patterns and a const' => ['if (s is Status.Paid) { n = 1; } else if (s is Status.Late) { n = 2; } else if (s == Limit) { n = 3; } else if (s is Status.Open) { n = 4; }', 1],
            'a generic method dispatching on its type argument' => ['if (typeof(T) == typeof(int)) { n = 1; } else if (typeof(T) == typeof(string)) { n = 2; } else if (typeof(T) == typeof(Status)) { n = 3; } else if (typeof(T) == typeof(Badge)) { n = 4; }', 1],
            'three rungs' => ['if (s == Status.Paid) { n = 1; } else if (s == Status.Late) { n = 2; } else if (s == Status.Void) { n = 3; }', 0],
            'rungs testing different subjects' => ['if (s == Status.Paid) { n = 1; } else if (code == "b") { n = 2; } else if (s == Status.Void) { n = 3; } else if (s == Status.Open) { n = 4; }', 0],
            'a rung comparing with something computed' => ['if (s == Status.Paid) { n = 1; } else if (s == Next()) { n = 2; } else if (s == Status.Void) { n = 3; } else if (s == Status.Open) { n = 4; }', 0],
        ];
    }

    #[DataProvider('ladders')]
    public function test_flags_a_ladder_over_one_subject(string $ladder, int $flagged): void
    {
        $source = <<<CS
            public enum Status { Paid, Late, Void, Open }

            public class Badge
            {
                private const Status Limit = Status.Void;

                private static Status Next() => Status.Open;

                public int For<T>(Status s, string code)
                {
                    var n = 0;
                    {$ladder}
                    return n;
                }
            }
            CS;

        $this->assertCount($flagged, new SubjectLadderDetector()->find(Codebase::fromString($source, 'Badge.cs')), $source);
    }
}
