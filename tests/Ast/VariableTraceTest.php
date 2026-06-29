<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Interaction;
use PhpParser\Node\Expr\Variable;
use PHPUnit\Framework\TestCase;

final class VariableTraceTest extends TestCase
{
    public function test_traces_every_interaction_in_source_order(): void
    {
        $code = <<<'PHP'
        <?php
        class S {
            public function handle(): string {
                $w = $this->find();
                if ($w === null) { return ''; }
                $w->record();
                return label($w) ?? 'x';
            }
        }
        PHP;

        $occurrences = Codebase::fromString($code)
            ->where(static fn (AstNode $n): bool => $n->node instanceof Variable && $n->node->name === 'w')
            ->get();

        $trace = $occurrences[0]->trace();
        $kinds = array_map(static fn (Interaction $i): string => $i->kind->value, $trace);

        $this->assertSame(['assigned', 'null-checked', 'method-call', 'argument'], $kinds);

        $deNulls = array_filter($trace, static fn (Interaction $i): bool => $i->deNulls());
        $this->assertCount(1, $deNulls);
    }
}
