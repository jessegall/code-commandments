<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Resolvers\Ast;

use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * The `use` import map and namespace of a parsed file — the alias => FQCN table
 * that {@see \JesseGall\CodeCommandments\Support\CallGraph\NameResolver} needs to
 * turn a short type name into a FQCN.
 */
final class FileImports
{
    /**
     * The alias => FQCN map for every `use` in the file (the alias is the short
     * name, or the explicit `as` alias).
     *
     * @param  array<Node>  $ast
     * @return array<string, string>
     */
    public static function of(array $ast): array
    {
        $uses = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Use_::class) as $use) {
            foreach ($use->uses as $u) {
                $uses[$u->getAlias()->toString()] = $u->name->toString();
            }
        }

        return $uses;
    }

    /**
     * The file's namespace, or null for the global namespace.
     *
     * @param  array<Node>  $ast
     */
    public static function namespace(array $ast): ?string
    {
        foreach ($ast as $node) {
            if ($node instanceof Node\Stmt\Namespace_ && $node->name !== null) {
                return $node->name->toString();
            }
        }

        return null;
    }
}
