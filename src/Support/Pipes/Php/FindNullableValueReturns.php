<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

/**
 * Find methods that decide between a value and nothing by returning null.
 *
 * The signal is the BODY, not the signature: a method is flagged when it
 * contains an explicit `return null;` (or a ternary with a null branch)
 * alongside at least one value return. Getters returning a nullable
 * property and passthroughs of someone else's nullable never flag —
 * they carry data, they don't decide nothingness.
 *
 * Skipped: methods with #[Override] (the contract isn't theirs to change)
 * and method names matching the configured exclude patterns (fnmatch
 * style, e.g. `try*` for the tryFrom convention).
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class FindNullableValueReturns implements Pipe
{
    /**
     * @var list<string>
     */
    private array $excludedMethods = [];

    /**
     * @param  list<string>  $patterns
     */
    public function withExcludedMethods(array $patterns): self
    {
        $this->excludedMethods = $patterns;

        return $this;
    }

    public function handle(mixed $input): mixed
    {
        if ($input->ast === null) {
            return $input->with(matches: []);
        }

        $nodeFinder = new NodeFinder;
        $matches = [];

        /** @var array<Node\Stmt\ClassLike> $classLikes */
        $classLikes = $nodeFinder->findInstanceOf($input->ast, Node\Stmt\ClassLike::class);

        foreach ($classLikes as $classLike) {
            $ownName = $classLike->name?->toString();

            foreach ($classLike->getMethods() as $method) {
                if ($this->isExcluded($method)) {
                    continue;
                }

                $counts = $this->countOwnReturns($method);

                if ($counts['null'] < 1 || $counts['value'] < 1) {
                    continue;
                }

                $typeInfo = $this->returnTypeInfo($method->returnType, $input->useStatements, $input->namespace);
                $label = ($ownName !== null ? $ownName . '::' : '') . $method->name->toString() . '()';
                $line = $method->getStartLine();
                $classFqcn = $ownName !== null
                    ? (($input->namespace !== null && $input->namespace !== '') ? $input->namespace . '\\' . $ownName : $ownName)
                    : '';

                $matches[] = new MatchResult(
                    name: $method->name->toString(),
                    pattern: '',
                    match: $label,
                    line: $line,
                    offset: null,
                    content: $this->getSnippet($input->content, $line),
                    groups: [
                        'method' => $label,
                        'method_name' => $method->name->toString(),
                        'class_fqcn' => $classFqcn,
                        'type_name' => $typeInfo['name'],
                        'type_fqcn' => $typeInfo['fqcn'],
                        'null_count' => (string) $counts['null'],
                        'value_count' => (string) $counts['value'],
                    ],
                );
            }
        }

        return $input->with(matches: $matches);
    }

    private function isExcluded(Stmt\ClassMethod $method): bool
    {
        if ($method->stmts === null || $method->stmts === []) {
            return true;
        }

        foreach ($method->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if ($attr->name->getLast() === 'Override') {
                    return true;
                }
            }
        }

        $name = $method->name->toString();

        foreach ($this->excludedMethods as $pattern) {
            if (fnmatch($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Count null returns and value returns belonging to the method's own
     * scope — returns inside nested closures, functions, and anonymous
     * classes are theirs, not the method's. A ternary with a null branch
     * counts as both.
     *
     * @return array{null: int, value: int}
     */
    private function countOwnReturns(Stmt\ClassMethod $method): array
    {
        $returns = [];

        $visitor = new class($returns) extends NodeVisitorAbstract {
            /** @var array<Stmt\Return_> */
            public array $returns;

            public function __construct(array &$returns)
            {
                $this->returns = &$returns;
            }

            public function enterNode(Node $node): ?int
            {
                if ($node instanceof Expr\Closure
                    || $node instanceof Expr\ArrowFunction
                    || $node instanceof Stmt\Function_
                    || $node instanceof Stmt\Class_
                ) {
                    return NodeVisitor::DONT_TRAVERSE_CHILDREN;
                }

                if ($node instanceof Stmt\Return_) {
                    $this->returns[] = $node;
                }

                return null;
            }
        };

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($method->stmts ?? []);

        $null = 0;
        $value = 0;

        foreach ($visitor->returns as $return) {
            if ($return->expr === null) {
                continue;
            }

            if ($this->isNull($return->expr)) {
                $null++;

                continue;
            }

            if ($return->expr instanceof Expr\Ternary) {
                $ifIsNull = $return->expr->if !== null && $this->isNull($return->expr->if);
                $elseIsNull = $this->isNull($return->expr->else);

                if ($ifIsNull || $elseIsNull) {
                    $null++;
                    $value++;

                    continue;
                }
            }

            $value++;
        }

        return ['null' => $null, 'value' => $value];
    }

    private function isNull(Expr $expr): bool
    {
        return $expr instanceof Expr\ConstFetch
            && strtolower($expr->name->toString()) === 'null';
    }

    /**
     * Printable non-null return type plus, when it is a single named
     * type, the resolved FQCN for null-object map lookups.
     *
     * @param  array<string, string>  $useStatements
     * @return array{name: string, fqcn: string}
     */
    private function returnTypeInfo(?Node $type, array $useStatements, ?string $namespace): array
    {
        $members = [];

        if ($type instanceof Node\NullableType) {
            $members = [$type->type];
        } elseif ($type instanceof Node\UnionType) {
            foreach ($type->types as $member) {
                if (($member instanceof Node\Identifier || $member instanceof Node\Name)
                    && strtolower($member->toString()) === 'null'
                ) {
                    continue;
                }

                $members[] = $member;
            }
        } elseif ($type !== null) {
            $members = [$type];
        }

        $names = array_map(fn (Node $m) => $m instanceof Node\IntersectionType
            ? implode('&', array_map(fn ($t) => $t->toString(), $m->types))
            : $m->toString(), $members);

        $fqcn = '';

        if (count($members) === 1 && $members[0] instanceof Node\Name) {
            $fqcn = $this->resolveFqcn($members[0], $useStatements, $namespace);
        }

        return [
            'name' => implode(' | ', $names),
            'fqcn' => $fqcn,
        ];
    }

    /**
     * @param  array<string, string>  $useStatements
     */
    private function resolveFqcn(Node\Name $name, array $useStatements, ?string $namespace): string
    {
        if ($name->isFullyQualified()) {
            return $name->toString();
        }

        $first = $name->getFirst();

        if (isset($useStatements[$first])) {
            $rest = array_slice($name->getParts(), 1);

            return $useStatements[$first] . ($rest !== [] ? '\\' . implode('\\', $rest) : '');
        }

        return ($namespace !== null && $namespace !== '' ? $namespace . '\\' : '') . $name->toString();
    }

    private function getSnippet(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return isset($lines[$line - 1]) ? trim($lines[$line - 1]) : '';
    }
}
