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

    /**
     * Ensure $content imports $fqcn — add `use $fqcn;` so a rewrite that emits the
     * short name (`T_Array::coalesce(...)`) resolves. Anchors after the `namespace`
     * declaration when present; otherwise (global namespace) after the last
     * `declare(...);`, else right after the opening `<?php` tag. No-op when the
     * import already exists. Without this an auto-fix that emits a short class name
     * in a namespaced file resolves to the file's own namespace and fatals.
     */
    public static function ensure(string $content, string $fqcn): string
    {
        if (preg_match('/^\s*use\s+' . preg_quote($fqcn, '/') . '\s*;/m', $content) === 1) {
            return $content;
        }

        // Prefer anchoring after the namespace declaration.
        if (preg_match('/^namespace\s+[^;]+;/m', $content, $m, PREG_OFFSET_CAPTURE) === 1) {
            $insertAt = $m[0][1] + strlen($m[0][0]);

            return substr($content, 0, $insertAt) . "\n\nuse {$fqcn};" . substr($content, $insertAt);
        }

        // Global namespace: anchor after the last declare(...); if any.
        if (preg_match_all('/^declare\s*\([^;]*\)\s*;/m', $content, $m, PREG_OFFSET_CAPTURE) >= 1) {
            $last = end($m[0]);
            $insertAt = $last[1] + strlen($last[0]);

            return substr($content, 0, $insertAt) . "\n\nuse {$fqcn};" . substr($content, $insertAt);
        }

        // Else right after the opening PHP tag.
        if (preg_match('/^<\?php/m', $content, $m, PREG_OFFSET_CAPTURE) === 1) {
            $insertAt = $m[0][1] + strlen($m[0][0]);

            return substr($content, 0, $insertAt) . "\n\nuse {$fqcn};" . substr($content, $insertAt);
        }

        return $content;
    }
}
