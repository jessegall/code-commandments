<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use PhpParser\Node;

/**
 * Extract use statements and namespace from an AST.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class ExtractUseStatements implements Pipe
{
    public function handle(mixed $input): mixed
    {
        $uses = [];
        $namespace = null;

        foreach ($input->ast ?? [] as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                $namespace = $node->name?->toString();

                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof Node\Stmt\Use_) {
                        $this->extractFromUseStatement($stmt, $uses);
                    }
                }
            } elseif ($node instanceof Node\Stmt\Use_) {
                $this->extractFromUseStatement($node, $uses);
            }
        }

        return $input->with(useStatements: $uses, namespace: $namespace);
    }

    /**
     * @param  array<string, string>  $uses
     */
    private function extractFromUseStatement(Node\Stmt\Use_ $use, array &$uses): void
    {
        foreach ($use->uses as $useUse) {
            $fqcn = $useUse->name->toString();
            $alias = $useUse->alias?->toString() ?? $useUse->name->getLast();
            $uses[$alias] = $fqcn;
        }
    }
}
