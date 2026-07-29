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

    public function test_declines_a_stack_whose_tags_would_contradict_each_other(): void
    {
        // #417: PHP shows only the LAST block, so the shadowed `@return` was inert — merging PROMOTES it
        // beside a return type it contradicts, turning dead documentation into live lies.
        $php = <<<'PHP'
        <?php

        class WizardState
        {
            /**
             * @return Option<string>
             */
            /**
             * @return array<class-string, array<string, mixed>>
             */
            public function drivers(): array
            {
                return [];
            }
        }
        PHP;

        $this->assertFalse($this->rewrote($php), 'a human decides which @return survives');
        $this->assertSame($php, $this->fix($php));
    }

    public function test_declines_a_block_standing_apart_from_the_stack(): void
    {
        // #415: an insertion orphaned the block belonging to a method further down — folding it in
        // attributed that method's prose to the one it happens to sit above.
        $php = <<<'PHP'
        <?php

        class WorkerSupervisor
        {
            /**
             * Stale workers are dropped so the next request rebuilds them.
             */

            /**
             * Changed source means the compiled maps describe code that no longer exists.
             */
            private function clearCompiledCaches(): void
            {
            }
        }
        PHP;

        $this->assertFalse($this->rewrote($php), 'whose words those are is not a machine question');
        $this->assertSame($php, $this->fix($php));
    }

    public function test_still_folds_tags_that_speak_about_different_things(): void
    {
        $php = <<<'PHP'
        <?php

        class Roster
        {
            /**
             * @param  string  $name
             */
            /**
             * @param  int  $age
             */
            public function join(string $name, int $age): void
            {
            }
        }
        PHP;

        $expected = <<<'PHP'
        <?php

        class Roster
        {
            /**
             * @param  string  $name
             *
             * @param  int  $age
             */
            public function join(string $name, int $age): void
            {
            }
        }
        PHP;

        $this->assertSame($expected, $this->fixStable($php), 'two @param of DIFFERENT names do not clash');
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
