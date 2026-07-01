<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\NegativeSpaceCommentDetector;
use PHPUnit\Framework\TestCase;

final class NegativeSpaceCommentDetectorTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function scopes(string $code): array
    {
        $hits = (new NegativeSpaceCommentDetector)->find(Codebase::fromString($code));

        return array_map(static fn ($m): string => $m->scope(), $hits);
    }

    public function test_flags_a_comment_that_defends_against_a_strawman(): void
    {
        $code = <<<'PHP'
        <?php
        class Seeder
        {
            public function pick(): int
            {
                // not random — seeded so replays are deterministic
                return $this->seed % 7;
            }
        }
        PHP;

        $this->assertSame(['Seeder::pick'], $this->scopes($code));
    }

    public function test_flags_no_magic_and_not_a_coincidence_and_not_dead_code(): void
    {
        $code = <<<'PHP'
        <?php
        class Wiring
        {
            // no magic here; registration order is load-bearing
            public array $order = [];

            public function ids(): array
            {
                // not a coincidence these line up with the enum
                return [0, 1, 2];
            }

            public function reached(): void
            {
                // this isn't dead code; a reflection call invokes it
            }
        }
        PHP;

        $this->assertSame(
            ['Wiring', 'Wiring::ids', 'Wiring::reached'],
            $this->scopes($code),
        );
    }

    public function test_does_not_flag_legitimate_contrastive_documentation(): void
    {
        $code = <<<'PHP'
        <?php
        class View
        {
            // render links as Markdown rather than plain text
            public function render(): string
            {
                // a masked hint for display — never the secret itself
                // the file may no longer exist on disk; the id previously bound scopes the lookup
                return '';
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }
}
