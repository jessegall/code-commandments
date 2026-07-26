<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Scribes\Backend;

use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Backend\InlineDocblockDetector;
use JesseGall\CodeCommandments\Scribes\Backend\InlineDocblockScribe;
use JesseGall\CodeCommandments\Scribes\RepentScribe;

final class InlineDocblockScribeTest extends ScribeTestCase
{
    protected function detector(): Detector
    {
        return new InlineDocblockDetector();
    }

    protected function scribe(): RepentScribe
    {
        return new InlineDocblockScribe();
    }

    public function test_expands_a_one_line_docblock_at_its_own_indentation(): void
    {
        $php = <<<'PHP'
        <?php

        class Wire
        {
            /** The quick-add menu a released wire reveals — where it opens comes from the release. */
            public function menu(): void
            {
            }
        }
        PHP;

        $expected = <<<'PHP'
        <?php

        class Wire
        {
            /**
             * The quick-add menu a released wire reveals — where it opens comes from the release.
             */
            public function menu(): void
            {
            }
        }
        PHP;

        $this->assertSame($expected, $this->fixStable($php));
    }

    public function test_expands_an_annotation_and_a_class_level_block(): void
    {
        $php = <<<'PHP'
        <?php

        /** Pins an element to a corner of the canvas. */
        final class Corner
        {
            /** @var list<string> */
            private array $names = [];
        }
        PHP;

        $expected = <<<'PHP'
        <?php

        /**
         * Pins an element to a corner of the canvas.
         */
        final class Corner
        {
            /**
             * @var list<string>
             */
            private array $names = [];
        }
        PHP;

        $this->assertSame($expected, $this->fixStable($php));
    }

    public function test_keeps_every_content_line_and_the_blank_between_paragraphs(): void
    {
        $php = <<<'PHP'
        <?php

        class Wire
        {
            /** Opens here
             * and runs on.
             *
             * @param int $slot The slot.
             */
            public function menu(int $slot): void
            {
            }
        }
        PHP;

        $expected = <<<'PHP'
        <?php

        class Wire
        {
            /**
             * Opens here
             * and runs on.
             *
             * @param int $slot The slot.
             */
            public function menu(int $slot): void
            {
            }
        }
        PHP;

        $this->assertSame($expected, $this->fixStable($php));
    }

    public function test_leaves_a_docblock_that_is_already_a_block(): void
    {
        $php = <<<'PHP'
        <?php

        class Wire
        {
            /**
             * Already shaped like a block.
             */
            public function menu(): void
            {
            }
        }
        PHP;

        $this->assertFalse($this->rewrote($php));
        $this->assertSame($php, $this->fix($php));
    }

    public function test_is_idempotent(): void
    {
        $php = <<<'PHP'
        <?php

        class Wire
        {
            /** One line. */
            public function menu(): void
            {
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        $this->assertSame([], $this->findings($fixed), 'the sin no longer fires');
        $this->assertSame($fixed, $this->fix($fixed), 'a second pass changes nothing');
    }
}
