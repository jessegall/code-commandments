<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

use PhpParser\Node;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;

/**
 * Gives a parameter the class its function's doc comment documents for it, read in the file's own namespace and imports.
 */
final class DocumentedParameters extends NodeVisitorAbstract
{
    public function __construct(private readonly NameResolver $names) {}

    public function enterNode(Node $node): null
    {
        if (! $node instanceof Node\FunctionLike || ($doc = $node->getDocComment()) === null) {
            return null;
        }
        preg_match_all('/@param\s+(\??)([\\\\A-Za-z_][\\\\A-Za-z0-9_]*)(\|null)?\s+(?:\.\.\.)?\$([A-Za-z_][A-Za-z0-9_]*)/', $doc->getText(), $documented, PREG_SET_ORDER);
        $classes = [];
        foreach ($documented as [, $nullable, $written, $orNull, $name]) {
            $resolved = $this->names->getNameContext()->getResolvedClassName(new Node\Name($written))->toString();
            $classes[$name] = ['name' => $resolved] + ($nullable !== '' || $orNull !== '' ? ['nullable' => true] : []);
        }
        foreach ($node->getParams() as $parameter) {
            if ($parameter->var instanceof Node\Expr\Variable && is_string($parameter->var->name) && isset($classes[$parameter->var->name])) {
                $parameter->setAttribute('documented', $classes[$parameter->var->name]);
            }
        }

        return null;
    }
}
