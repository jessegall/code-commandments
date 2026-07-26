<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\StackedDocblockDetector;
use PHPUnit\Framework\TestCase;

/**
 * One declaration, one docblock — PHP reads only the last of a stack.
 */
final class StackedDocblockDetectorTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function scopes(string $code): array
    {
        $hits = (new StackedDocblockDetector)->find(Codebase::fromString($code));

        return array_map(static fn ($m): string => $m->scope(), $hits);
    }

    public function test_flags_two_one_line_docblocks_on_one_method(): void
    {
        $code = <<<'PHP'
        <?php
        class Corner
        {
            /** Pins an element to a corner of the canvas; same-corner elements stack. */
            /** Pins a control into a corner. Nothing to pin pins nothing. */
            public function pin(): void
            {
            }
        }
        PHP;

        $this->assertSame(['Corner::pin'], $this->scopes($code));
    }

    public function test_flags_a_stack_of_full_blocks_on_a_class(): void
    {
        $code = <<<'PHP'
        <?php
        /**
         * The first description.
         */
        /**
         * The second one, which is the only one PHP hands to a reader.
         */
        final class Canvas
        {
        }
        PHP;

        $this->assertSame(['Canvas'], $this->scopes($code));
    }

    public function test_flags_a_stack_on_a_property(): void
    {
        $code = <<<'PHP'
        <?php
        class Roster
        {
            /** The names, in arrival order. */
            /** @var list<string> */
            private array $names = [];
        }
        PHP;

        $this->assertSame(['Roster'], $this->scopes($code));
    }

    public function test_leaves_a_single_docblock_alone(): void
    {
        $code = <<<'PHP'
        <?php
        class Corner
        {
            /**
             * Pins an element to a corner of the canvas.
             *
             * @var list<string>
             */
            private array $pinned = [];
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_leaves_a_docblock_with_a_line_comment_above_it(): void
    {
        // A `//` note is not a second docblock — nothing is hidden from tooling.
        $code = <<<'PHP'
        <?php
        class Corner
        {
            // the canvas decides the corner
            /**
             * Pins an element to a corner.
             */
            public function pin(): void
            {
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }
}
