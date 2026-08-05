<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Vue\Codebase as FrontendCodebase;
use JesseGall\CodeCommandments\Vue\ElementMatch;
use PHPUnit\Framework\TestCase;

/**
 * The words a USER reads are values, not identifiers, so no identifier sweep sees them (#441). Each
 * engine selects its own kind of copy — a PHP string literal, a static text node — and the arsenal
 * around it answers which text position the words reached.
 */
final class CopySelectorTest extends TestCase
{
    public function test_the_backend_selects_a_literal_and_says_what_call_it_reached(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App;
        class TracePage {
            public function render(): array {
                return [
                    SectionHeading::of('Taken'),
                    Text::of('a note'),
                ];
            }
        }
        PHP;

        $captions = array_map(
            static fn (NodeMatch $m): string => $m->node->value,
            Codebase::fromString($code)
                ->whereString()
                ->where(static fn (NodeMatch $m): bool => $m->argumentOfCall() === 'of')
                ->where(static fn (NodeMatch $m): bool => $m->parent()->parent()->staticCallClass() === 'App\SectionHeading')
                ->get(),
        );

        $this->assertSame(['Taken'], $captions);
    }

    public function test_the_frontend_selects_the_text_a_user_reads(): void
    {
        $vue = <<<'VUE'
        <template>
            <section>
                <h2>Taken</h2>
                <p>{{ payload.label }}</p>
            </section>
        </template>
        VUE;

        $text = array_map(
            static fn (ElementMatch $m): string => trim($m->text),
            FrontendCodebase::fromString($vue)->whereText()->get(),
        );

        $this->assertSame(['Taken'], $text, 'interpolated text renders elsewhere — there is nothing to read here');
    }
}
