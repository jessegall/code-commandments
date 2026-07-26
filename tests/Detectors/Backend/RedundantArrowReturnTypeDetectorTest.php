<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\RedundantArrowReturnTypeDetector;
use PHPUnit\Framework\TestCase;

/**
 * An arrow function's return type earns its place by saying something the expression does not. When
 * the expression PROVES the type, the annotation is a line of reading for nothing — and when it
 * cannot be proven, the rule says nothing, because that is exactly the type worth writing down.
 */
final class RedundantArrowReturnTypeDetectorTest extends TestCase
{
    /**
     * @return list<int>
     */
    private function lines(string $body): array
    {
        $code = <<<PHP_SOURCE
        <?php
        namespace App;

        final class Money
        {
            public static function zero(): self { return new self(); }
        }

        final class Panel
        {
            private string \$name = 'x';

            private ?string \$maybe = null;

            public function __construct(private readonly Money \$price) {}

            public function label(): string { return \$this->name; }

            public function untyped() { return \$this->name; }

            public function all(): array
            {
                return [
        {$body}
                ];
            }
        }
        PHP_SOURCE;

        return array_map(static fn ($m): int => $m->line(), (new RedundantArrowReturnTypeDetector)->find(Codebase::fromString($code)));
    }

    private function assertFlagged(string $arrow): void
    {
        $this->assertNotSame([], $this->lines($arrow), "should be flagged: {$arrow}");
    }

    private function assertLeftAlone(string $arrow): void
    {
        $this->assertSame([], $this->lines($arrow), "should be left alone: {$arrow}");
    }

    public function test_flags_a_type_restating_a_property(): void
    {
        $this->assertFlagged('fn (): string => $this->name,');
    }

    public function test_flags_a_type_restating_a_promoted_property(): void
    {
        $this->assertFlagged('fn (): Money => $this->price,');
    }

    public function test_flags_a_type_restating_a_method_it_can_read(): void
    {
        $this->assertFlagged('fn (): string => $this->label(),');
    }

    public function test_flags_a_type_restating_a_construction(): void
    {
        $this->assertFlagged('fn (): Money => new Money(),');
        $this->assertFlagged('fn (): Money => Money::zero(),');
    }

    public function test_flags_a_type_restating_a_literal(): void
    {
        $this->assertFlagged('fn (): int => 42,');
        $this->assertFlagged("fn (): string => 'ready',");
        $this->assertFlagged('fn (): bool => true,');
    }

    public function test_flags_a_nullable_type_restating_a_nullable_property(): void
    {
        $this->assertFlagged('fn (): ?string => $this->maybe,');
    }

    public function test_leaves_a_type_that_narrows_what_the_expression_yields(): void
    {
        // The property is `string`; declaring `?string` is a widening the reader should see.
        $this->assertLeftAlone('fn (): ?string => $this->name,');
    }

    public function test_leaves_an_expression_it_cannot_prove(): void
    {
        $this->assertLeftAlone('fn (): string => $this->maybe ?? "d",');
        $this->assertLeftAlone('fn (): string => $this->name === "" ? "a" : "b",');
        $this->assertLeftAlone('fn (): string => $this->price->code(),');
    }

    public function test_leaves_a_type_the_expression_cannot_speak_for(): void
    {
        // The method declares nothing, so the arrow's type is the only one there is.
        $this->assertLeftAlone('fn (): string => $this->untyped(),');
    }

    public function test_leaves_an_arrow_with_no_return_type_alone(): void
    {
        $this->assertLeftAlone('fn () => $this->name,');
    }

    public function test_leaves_self_and_static_alone(): void
    {
        // What they mean depends on where the closure ends up, not on the expression.
        $this->assertLeftAlone('fn (): self => new Panel($this->price),');
    }
}
