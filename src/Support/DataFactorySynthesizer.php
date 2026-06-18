<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * Auto-fixes object-typed `XData::from($obj)` magic dispatch: it rewrites the
 * call to `XData::from{Type}($obj)` and synthesises the matching factory
 *
 *     public static function from{Type}(\Fqcn\Type $x): static
 *     {
 *         return static::from($x->toArray());
 *     }
 *
 * on `XData`. The factory lands wherever `XData` is defined — the SAME file
 * (folded into the call-site edits) or ANY OTHER file, located through the
 * codebase index and emitted as a `createdFiles` overwrite. So repenting a
 * flagged call site fixes the whole migration even when the Data class lives
 * outside the scoped set.
 */
final class DataFactorySynthesizer
{
    /**
     * @param  array<Node>  $ast  the current file's AST, already name-resolved
     * @param  list<string>  $dataSuffixes
     * @return array{edits: list<array{start: int, end: int, text: string}>, createdFiles: array<string, string>, penance: list<string>}
     */
    public function synthesize(string $filePath, string $content, array $ast, ?CodebaseIndex $index, array $dataSuffixes): array
    {
        $finder = new NodeFinder;
        $edits = [];
        $penance = [];

        // dataFqcn => ['short' => string, 'types' => array<typeShort, typeFqcn>,
        //              'calls' => list<array{call: StaticCall, typeShort: string}>]
        $needs = [];

        foreach ($finder->findInstanceOf($ast, Node\Expr\StaticCall::class) as $call) {
            $resolved = $this->resolveObjectFrom($call, $ast, $dataSuffixes);

            if ($resolved === null) {
                continue;
            }

            $needs[$resolved['dataFqcn']]['short'] = $resolved['dataShort'];
            $needs[$resolved['dataFqcn']]['types'][$resolved['typeShort']] = $resolved['typeFqcn'];
            $needs[$resolved['dataFqcn']]['calls'][] = ['call' => $call, 'typeShort' => $resolved['typeShort']];
        }

        $createdFiles = [];

        foreach ($needs as $dataFqcn => $need) {
            // Only rewrite the call sites if we can actually place the factory on
            // the Data class — otherwise we'd leave a call to a method that does
            // not exist. An unreachable class (e.g. a vendor Data) is left alone.
            if (! $this->placeFactories($dataFqcn, $need, $filePath, $content, $index, $edits, $createdFiles, $penance)) {
                continue;
            }

            foreach ($need['calls'] as $site) {
                $edits[] = [
                    'start' => (int) $site['call']->name->getStartFilePos(),
                    'end' => (int) $site['call']->name->getEndFilePos(),
                    'text' => 'from' . $site['typeShort'],
                ];
                $penance[] = sprintf('Rewrote %s::from(object) to ::from%s()', $need['short'], $site['typeShort']);
            }
        }

        return ['edits' => $edits, 'createdFiles' => $createdFiles, 'penance' => $penance];
    }

    /**
     * @param  array<Node>  $ast
     * @param  list<string>  $dataSuffixes
     * @return array{dataFqcn: string, dataShort: string, typeShort: string, typeFqcn: string}|null
     */
    private function resolveObjectFrom(Node\Expr\StaticCall $call, array $ast, array $dataSuffixes): ?array
    {
        if (! $call->name instanceof Node\Identifier || $call->name->toString() !== 'from'
            || ! $call->class instanceof Node\Name || count($call->args) !== 1
            || ! $call->args[0] instanceof Node\Arg
        ) {
            return null;
        }

        $dataShort = $call->class->getLast();
        $enclosingClass = $this->enclosingClass($call, $ast);

        if (in_array($dataShort, ['self', 'static'], true)) {
            if ($enclosingClass?->name === null) {
                return null;
            }

            $dataShort = $enclosingClass->name->toString();
            $dataFqcn = $this->classFqcn($enclosingClass);
        } else {
            $dataFqcn = $this->nameFqcn($call->class);
        }

        if (! $this->matchesSuffix($dataShort, $dataSuffixes)) {
            return null;
        }

        $type = $this->resolveArgType($call->args[0]->value, $call, $ast, $enclosingClass);

        if ($type === null) {
            return null;
        }

        return [
            'dataFqcn' => $dataFqcn,
            'dataShort' => $dataShort,
            'typeShort' => $type['short'],
            'typeFqcn' => $type['fqcn'],
        ];
    }

    /**
     * @param  array<Node>  $ast
     * @return array{short: string, fqcn: string}|null
     */
    private function resolveArgType(Node $arg, Node\Expr\StaticCall $call, array $ast, ?Node\Stmt\Class_ $enclosingClass): ?array
    {
        if ($arg instanceof Node\Expr\Variable && $arg->name === 'this') {
            return $enclosingClass?->name !== null
                ? ['short' => $enclosingClass->name->toString(), 'fqcn' => $this->classFqcn($enclosingClass)]
                : null;
        }

        if ($arg instanceof Node\Expr\Variable && is_string($arg->name)) {
            return $this->objectInfo($this->paramTypeInScope($arg->name, $call, $ast));
        }

        if (($arg instanceof Node\Expr\PropertyFetch || $arg instanceof Node\Expr\NullsafePropertyFetch)
            && $arg->var instanceof Node\Expr\Variable && $arg->var->name === 'this'
            && $arg->name instanceof Node\Identifier && $enclosingClass !== null
        ) {
            return $this->objectInfo($this->propertyType($enclosingClass, $arg->name->toString()));
        }

        if ($arg instanceof Node\Expr\New_ && $arg->class instanceof Node\Name) {
            return ['short' => $arg->class->getLast(), 'fqcn' => $this->nameFqcn($arg->class)];
        }

        return null;
    }

    /**
     * A type node → object info, or null when it is not a class type (builtin
     * scalar/array/etc. are never the magic object dispatch).
     *
     * @return array{short: string, fqcn: string}|null
     */
    private function objectInfo(?Node $type): ?array
    {
        if ($type instanceof Node\NullableType) {
            return $this->objectInfo($type->type);
        }

        if ($type instanceof Node\Name) {
            return ['short' => $type->getLast(), 'fqcn' => $this->nameFqcn($type)];
        }

        return null;
    }

    /**
     * The type node of parameter $name from the innermost enclosing
     * function-like (method / closure / arrow fn) that contains $call.
     *
     * @param  array<Node>  $ast
     */
    private function paramTypeInScope(string $name, Node\Expr\StaticCall $call, array $ast): ?Node
    {
        $finder = new NodeFinder;
        $pos = (int) $call->getStartFilePos();
        $best = null;
        $bestStart = -1;

        $functionLikes = array_merge(
            $finder->findInstanceOf($ast, Node\Stmt\ClassMethod::class),
            $finder->findInstanceOf($ast, Node\Stmt\Function_::class),
            $finder->findInstanceOf($ast, Node\Expr\Closure::class),
            $finder->findInstanceOf($ast, Node\Expr\ArrowFunction::class),
        );

        foreach ($functionLikes as $fn) {
            $start = (int) $fn->getStartFilePos();
            $end = (int) $fn->getEndFilePos();

            if ($start > $pos || $end < $pos || $start <= $bestStart) {
                continue;
            }

            foreach ($fn->params as $param) {
                if ($param->var instanceof Node\Expr\Variable && $param->var->name === $name) {
                    $best = $param->type;
                    $bestStart = $start;
                }
            }
        }

        return $best;
    }

    private function propertyType(Node\Stmt\Class_ $class, string $property): ?Node
    {
        foreach ($class->getProperties() as $prop) {
            foreach ($prop->props as $declared) {
                if ($declared->name->toString() === $property) {
                    return $prop->type;
                }
            }
        }

        // Promoted constructor properties.
        $ctor = $class->getMethod('__construct');

        if ($ctor !== null) {
            foreach ($ctor->params as $param) {
                if ($param->flags !== 0 && $param->var instanceof Node\Expr\Variable && $param->var->name === $property) {
                    return $param->type;
                }
            }
        }

        return null;
    }

    /**
     * Insert the needed factories onto $dataFqcn's class, routing the edit to
     * the current file (byte edits) or to its own file (a createdFiles
     * overwrite) located through the index.
     *
     * @param  array{short: string, types: array<string, string>, calls: list<array{call: Node\Expr\StaticCall, typeShort: string}>}  $need
     * @param  list<array{start: int, end: int, text: string}>  $edits
     * @param  array<string, string>  $createdFiles
     * @param  list<string>  $penance
     * @return bool  whether the Data class was reachable (so its call sites may be rewritten)
     */
    private function placeFactories(string $dataFqcn, array $need, string $filePath, string $content, ?CodebaseIndex $index, array &$edits, array &$createdFiles, array &$penance): bool
    {
        $targetFile = $this->locateClassFile($dataFqcn, $need['short'], $filePath, $content, $index);

        if ($targetFile === null) {
            return false; // the Data class is not reachable — leave the call for a human.
        }

        $sameFile = $targetFile === $filePath;
        $fileContent = $sameFile ? $content : ($createdFiles[$targetFile] ?? @file_get_contents($targetFile));

        if (! is_string($fileContent)) {
            return false;
        }

        $class = $this->findClassNode($fileContent, $need['short']);

        if ($class === null) {
            return false;
        }

        $existing = $this->methodNames($class);
        $body = '';

        foreach ($need['types'] as $typeShort => $typeFqcn) {
            $factoryName = 'from' . $typeShort;

            if (in_array(strtolower($factoryName), $existing, true)) {
                continue;
            }

            $existing[] = strtolower($factoryName);
            $body .= $this->factorySource($typeShort, $typeFqcn);
            $penance[] = sprintf('Generated %s::%s() factory', $need['short'], $factoryName);
        }

        // Reachable but every factory already exists — the call sites may still
        // be rewritten to use them.
        if ($body === '') {
            return true;
        }

        $insertPos = (int) $class->getEndFilePos(); // the closing brace

        if ($sameFile) {
            $edits[] = ['start' => $insertPos, 'end' => $insertPos - 1, 'text' => $body];

            return true;
        }

        $createdFiles[$targetFile] = substr($fileContent, 0, $insertPos) . $body . substr($fileContent, $insertPos);

        return true;
    }

    private function factorySource(string $typeShort, string $typeFqcn): string
    {
        $param = lcfirst($typeShort);

        return sprintf(
            "\n    public static function from%s(%s \$%s): static\n    {\n        return static::from(\$%s->toArray());\n    }\n",
            $typeShort,
            $typeFqcn,
            $param,
            $param,
        );
    }

    private function locateClassFile(string $dataFqcn, string $dataShort, string $filePath, string $content, ?CodebaseIndex $index): ?string
    {
        // Defined in the current file?
        if ($this->findClassNode($content, $dataShort) !== null) {
            return $filePath;
        }

        $summary = $index?->classByFqcn(ltrim($dataFqcn, '\\'));

        return $summary?->filePath;
    }

    private function findClassNode(string $content, string $shortName): ?Node\Stmt\Class_
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return null;
        }

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if ($class->name?->toString() === $shortName) {
                return $class;
            }
        }

        return null;
    }

    /**
     * @return list<string>  lowercased method names
     */
    private function methodNames(Node\Stmt\Class_ $class): array
    {
        $names = [];

        foreach ($class->getMethods() as $method) {
            $names[] = strtolower($method->name->toString());
        }

        return $names;
    }

    /**
     * @param  array<Node>  $ast
     */
    private function enclosingClass(Node $node, array $ast): ?Node\Stmt\Class_
    {
        $pos = (int) $node->getStartFilePos();
        $best = null;
        $bestStart = -1;

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            $start = (int) $class->getStartFilePos();

            if ($start <= $pos && (int) $class->getEndFilePos() >= $pos && $start > $bestStart) {
                $best = $class;
                $bestStart = $start;
            }
        }

        return $best;
    }

    private function classFqcn(Node\Stmt\Class_ $class): string
    {
        $resolved = $class->namespacedName;

        return '\\' . ($resolved !== null ? $resolved->toString() : ($class->name?->toString() ?? ''));
    }

    private function nameFqcn(Node\Name $name): string
    {
        $resolved = $name->getAttribute('resolvedName');

        if ($resolved instanceof Node\Name) {
            return '\\' . $resolved->toString();
        }

        return $name->isFullyQualified() ? '\\' . $name->toString() : $name->toString();
    }

    private function matchesSuffix(string $short, array $suffixes): bool
    {
        foreach ($suffixes as $suffix) {
            if (str_ends_with($short, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<Node>|null
     */
    private function parse(string $content): ?array
    {
        return (new ParserFactory)->createForNewestSupportedVersion()->parse($content);
    }
}
