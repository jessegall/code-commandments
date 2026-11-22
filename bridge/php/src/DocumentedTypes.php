<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

use PhpParser\Node;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;

/**
 * Gives a parameter, a class's undeclared properties and the variables an inline @var names the class a doc comment
 * documents for them, read in the file's own namespace and imports.
 */
final class DocumentedTypes extends NodeVisitorAbstract
{
    private const BUILT_IN = ['int', 'integer', 'string', 'bool', 'boolean', 'float', 'double', 'array', 'list', 'mixed', 'callable', 'iterable', 'object', 'null', 'void', 'false', 'true', 'never', 'resource', 'self', 'static', 'parent', 'scalar', 'numeric'];

    public function __construct(private readonly NameResolver $names) {}

    public function enterNode(Node $node): null
    {
        if ($node instanceof Node\Stmt\ClassLike && ($doc = $node->getDocComment()) !== null) {
            $node->setAttribute('documentedProperties', $this->documented('@property(?:-read)?', $doc->getText()));

            return null;
        }
        if ($node instanceof Node\Stmt && ! $node instanceof Node\FunctionLike && ($doc = $node->getDocComment()) !== null) {
            $variables = $this->documented('@var', $doc->getText());
            $assigned = $node instanceof Node\Stmt\Expression && $node->expr instanceof Node\Expr\Assign && $node->expr->var instanceof Node\Expr\Variable && is_string($node->expr->var->name) ? $node->expr->var->name : null;
            if ($assigned !== null && $variables === [] && preg_match('/@var\s+(\??)([\\\\A-Za-z_][\\\\A-Za-z0-9_]*)(\|null)?\s*(?:\*\/|\s)/', $doc->getText(), $only) === 1 && ! in_array(strtolower($only[2]), self::BUILT_IN, true)) {
                $variables[$assigned] = ['name' => $this->names->getNameContext()->getResolvedClassName(new Node\Name($only[2]))->toString()] + ($only[1] !== '' || $only[3] !== '' ? ['nullable' => true] : []);
            }
            if ($variables !== []) {
                $node->setAttribute('documentedVariables', $variables);
            }

            return null;
        }
        if (! $node instanceof Node\FunctionLike || ($doc = $node->getDocComment()) === null) {
            return null;
        }
        $classes = $this->documented('@param', $doc->getText());
        foreach ($node->getParams() as $parameter) {
            if ($parameter->var instanceof Node\Expr\Variable && is_string($parameter->var->name) && isset($classes[$parameter->var->name])) {
                $parameter->setAttribute('documented', $classes[$parameter->var->name]);
            }
        }

        return null;
    }

    /**
     * @return array<string, array{name: string, nullable?: true}>
     */
    private function documented(string $tag, string $doc): array
    {
        preg_match_all('/'.$tag.'\s+(\??)([\\\\A-Za-z_][\\\\A-Za-z0-9_]*)(\|null)?\s+(?:\.\.\.)?\$([A-Za-z_][A-Za-z0-9_]*)/', $doc, $documented, PREG_SET_ORDER);
        $classes = [];
        preg_match_all('/'.$tag.'\s+(?:(?:array|list|iterable|non-empty-list|non-empty-array)<(?:[^,<>]+,\s*)?([\\\\A-Za-z_][\\\\A-Za-z0-9_]*)>|([\\\\A-Za-z_][\\\\A-Za-z0-9_]*)\[\])\s+(?:\.\.\.)?\$([A-Za-z_][A-Za-z0-9_]*)/', $doc, $listed, PREG_SET_ORDER);
        foreach ($listed as [, $inBrackets, $beforeBrackets, $name]) {
            $element = $inBrackets !== '' ? $inBrackets : $beforeBrackets;
            if (! in_array(strtolower($element), self::BUILT_IN, true)) {
                $classes[$name] = ['element' => ['name' => $this->names->getNameContext()->getResolvedClassName(new Node\Name($element))->toString()]];
            }
        }
        foreach ($documented as [, $nullable, $written, $orNull, $name]) {
            if (in_array(strtolower($written), self::BUILT_IN, true)) {
                continue;
            }
            $resolved = $this->names->getNameContext()->getResolvedClassName(new Node\Name($written))->toString();
            $classes[$name] = ['name' => $resolved] + ($nullable !== '' || $orNull !== '' ? ['nullable' => true] : []);
        }

        return $classes;
    }
}
