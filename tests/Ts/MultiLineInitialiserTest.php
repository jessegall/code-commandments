<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ts;

use JesseGall\CodeCommandments\Ts\Node\VariableDecl;
use JesseGall\CodeCommandments\Ts\Parser;
use PHPUnit\Framework\TestCase;

/**
 * A newline ends a statement only where the expression could BE finished (#470). After an operator
 * the language is still mid-expression, so `const f = (x) =>` with its body on the next line is ONE
 * declaration — it used to be cut at the arrow, and the body leaked out as separate statements. Any
 * rule reading a declaration's own body saw nothing for every arrow-bodied declaration there is.
 */
final class MultiLineInitialiserTest extends TestCase
{
    public function test_an_arrow_body_on_the_next_line_belongs_to_its_declaration(): void
    {
        $module = Parser::module(<<<'TS'
            export const hasAttribute: Check = (payload) =>
                element(payload.node).hasAttribute(payload.name)
            TS);

        $declaration = $this->onlyDeclaration($module->body);

        $this->assertSame('Check', $declaration->typeAnnotation?->render());
        $this->assertStringContainsString('hasAttribute(payload.name)', (string) $declaration->initRaw);
    }

    public function test_an_expression_broken_after_any_operator_stays_whole(): void
    {
        $module = Parser::module(<<<'TS'
            const label =
                first +
                second
            TS);

        $declaration = $this->onlyDeclaration($module->body);

        $this->assertStringContainsString('second', (string) $declaration->initRaw);
    }

    public function test_a_finished_expression_still_ends_at_the_newline(): void
    {
        // ASI is not switched off: a complete expression followed by a new statement is two
        // statements, with no semicolon needed to say so.
        $module = Parser::module(<<<'TS'
            const total = count
            const other = 1
            TS);

        $declarations = array_values(array_filter($module->body, static fn (object $s): bool => $s instanceof VariableDecl));

        $this->assertCount(2, $declarations);
        $this->assertSame('count', $declarations[0]->initRaw);
    }

    /**
     * @param  list<object>  $body
     */
    private function onlyDeclaration(array $body): VariableDecl
    {
        $declarations = array_values(array_filter($body, static fn (object $s): bool => $s instanceof VariableDecl));

        $this->assertCount(1, $declarations, 'the whole initialiser is ONE declaration');
        $this->assertCount(1, $body, 'nothing leaked out of it as a statement of its own');

        return $declarations[0];
    }
}
