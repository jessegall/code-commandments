<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\InlineDocblockDetector;
use PHPUnit\Framework\TestCase;

/**
 * A docblock is a BLOCK: the opening delimiter on its own line, the closing one on its own.
 */
final class InlineDocblockDetectorTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function scopes(string $code): array
    {
        $hits = (new InlineDocblockDetector)->find(Codebase::fromString($code));

        return array_map(static fn ($m): string => $m->scope(), $hits);
    }

    public function test_flags_a_docblock_written_on_one_line(): void
    {
        $code = <<<'PHP'
        <?php
        class Wire
        {
            /** The quick-add menu a released wire reveals. */
            public function menu(): void
            {
            }
        }
        PHP;

        $this->assertSame(['Wire::menu'], $this->scopes($code));
    }

    public function test_flags_a_one_line_annotation_too(): void
    {
        $code = <<<'PHP'
        <?php
        class Roster
        {
            /** @var list<string> */
            private array $names = [];
        }
        PHP;

        $this->assertSame(['Roster'], $this->scopes($code));
    }

    public function test_flags_a_block_that_opens_beside_its_text(): void
    {
        $code = <<<'PHP'
        <?php
        class Wire
        {
            /** Opens here
             * and closes properly.
             */
            public function menu(): void
            {
            }
        }
        PHP;

        $this->assertSame(['Wire::menu'], $this->scopes($code));
    }

    public function test_flags_a_block_that_closes_beside_its_text(): void
    {
        $code = <<<'PHP'
        <?php
        class Wire
        {
            /**
             * Opens properly
             * but closes here. */
            public function menu(): void
            {
            }
        }
        PHP;

        $this->assertSame(['Wire::menu'], $this->scopes($code));
    }

    public function test_leaves_a_proper_block_alone(): void
    {
        $code = <<<'PHP'
        <?php
        class Wire
        {
            /**
             * The quick-add menu a released wire reveals — where it opens and what it offers come
             * from the release.
             *
             * @param int $slot The slot it opens over.
             */
            public function menu(int $slot): void
            {
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_leaves_a_line_comment_alone(): void
    {
        // `//` prose is not a docblock and has no delimiters to place.
        $code = <<<'PHP'
        <?php
        class Wire
        {
            // the release decides where this opens
            public function menu(): void
            {
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_leaves_an_empty_docblock_alone(): void
    {
        // Nothing to put on a line of its own — and an empty docblock is another rule's business.
        $code = <<<'PHP'
        <?php
        class Wire
        {
            /** */
            public function menu(): void
            {
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }
}
