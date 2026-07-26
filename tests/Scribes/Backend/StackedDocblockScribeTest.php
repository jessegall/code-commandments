<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Scribes\Backend;

use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Backend\StackedDocblockDetector;
use JesseGall\CodeCommandments\Scribes\Backend\StackedDocblockScribe;
use JesseGall\CodeCommandments\Scribes\RepentScribe;

final class StackedDocblockScribeTest extends ScribeTestCase
{
    protected function detector(): Detector
    {
        return new StackedDocblockDetector();
    }

    protected function scribe(): RepentScribe
    {
        return new StackedDocblockScribe();
    }

    public function test_merges_two_one_liners_into_one_block(): void
    {
        $php = <<<'PHP'
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

        $expected = <<<'PHP'
        <?php

        class Corner
        {
            /**
             * Pins an element to a corner of the canvas; same-corner elements stack.
             *
             * Pins a control into a corner. Nothing to pin pins nothing.
             */
            public function pin(): void
            {
            }
        }
        PHP;

        $this->assertSame($expected, $this->fixStable($php));
    }

    public function test_merges_a_description_with_an_annotation_block(): void
    {
        $php = <<<'PHP'
        <?php

        class Roster
        {
            /** The names, in arrival order. */
            /** @var list<string> */
            private array $names = [];
        }
        PHP;

        $expected = <<<'PHP'
        <?php

        class Roster
        {
            /**
             * The names, in arrival order.
             *
             * @var list<string>
             */
            private array $names = [];
        }
        PHP;

        $this->assertSame($expected, $this->fixStable($php));
    }

    public function test_leaves_a_single_docblock_untouched(): void
    {
        $php = <<<'PHP'
        <?php

        class Corner
        {
            /**
             * Pins an element to a corner.
             */
            public function pin(): void
            {
            }
        }
        PHP;

        $this->assertFalse($this->rewrote($php));
        $this->assertSame($php, $this->fix($php));
    }

    public function test_is_idempotent_and_keeps_every_word(): void
    {
        $php = <<<'PHP'
        <?php

        /** The first description. */
        /** The second one, which is the only one PHP hands to a reader. */
        final class Canvas
        {
        }
        PHP;

        $fixed = $this->fixStable($php);

        $this->assertStringContainsString('The first description.', $fixed);
        $this->assertStringContainsString('The second one, which is the only one PHP hands to a reader.', $fixed);
        $this->assertSame([], $this->findings($fixed), 'the sin no longer fires');
        $this->assertSame($fixed, $this->fix($fixed), 'a second pass changes nothing');
    }
}
