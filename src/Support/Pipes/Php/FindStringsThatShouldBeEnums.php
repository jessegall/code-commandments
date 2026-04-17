<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\NodeFinder;
use ReflectionEnum;
use ReflectionException;

/**
 * Flag raw string literals that are really enum cases in disguise.
 *
 * High-signal patterns (v1):
 *
 *   1. Named arg passed to a ctor / call where the arg name matches an
 *      enum imported in the same file (by case-insensitive suffix match)
 *      AND the literal value matches one of that enum's cases.
 *
 *   2. A string-typed class/constructor property with a default value
 *      whose value matches a case on an imported enum whose short name
 *      shares a suffix with the property name.
 *
 * Enum introspection is reflection-based — the enum classes must be
 * autoloadable at judgement time, which is true when `commandments`
 * runs inside the consumer's project.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class FindStringsThatShouldBeEnums implements Pipe
{
    /**
     * Methods whose string returns are wire-format (JSON responses, arrays
     * going over an API boundary) and where literal values are intentional.
     */
    private const WIRE_FORMAT_METHODS = [
        'toArray', 'jsonSerialize', 'render', 'toResponse', 'resolve',
    ];

    /**
     * Classes to treat as wire-format scopes — any string inside a method
     * of one of these is left alone.
     */
    private const WIRE_FORMAT_PARENT_SUFFIXES = [
        'JsonResource', 'Resource', 'Response',
    ];

    /** @var array<string, ReflectionEnum> */
    private static array $reflectionCache = [];

    public function handle(mixed $input): mixed
    {
        if ($input->ast === null) {
            return $input->with(matches: []);
        }

        $importedEnums = $this->resolveImportedEnums($input->useStatements);

        if (empty($importedEnums)) {
            return $input->with(matches: []);
        }

        $nodeFinder = new NodeFinder;
        $parentMap = $this->buildParentMap($input->ast);

        $matches = [];
        $seen = [];

        // Pattern 1: named args whose value is a string literal matching
        // a case on an imported enum whose name matches the arg name.
        foreach ($nodeFinder->find($input->ast, fn ($n) => $n instanceof Node\Arg && $n->name !== null) as $arg) {
            assert($arg instanceof Node\Arg);

            if (! $arg->value instanceof Scalar\String_) {
                continue;
            }

            if ($this->isInsideWireFormatScope($arg, $parentMap)) {
                continue;
            }

            $argName = $arg->name->toString();
            $value = $arg->value->value;

            foreach ($importedEnums as $shortName => $info) {
                if (! $this->nameMatches($argName, $shortName)) {
                    continue;
                }

                $caseName = $this->matchCase($info, $value);

                if ($caseName === null) {
                    continue;
                }

                $dedupe = "arg::{$shortName}::{$argName}::{$value}";

                if (isset($seen[$dedupe])) {
                    continue;
                }

                $seen[$dedupe] = true;

                $matches[] = $this->makeMatch(
                    name: 'named_arg',
                    line: $arg->getStartLine(),
                    content: $this->getSnippet($input->content, $arg->getStartLine()),
                    shortName: $shortName,
                    fqcn: $info['fqcn'],
                    caseName: $caseName,
                    value: $value,
                    subject: "\${$argName}",
                );

                break; // one match per arg is enough
            }
        }

        // Pattern 2: string-typed property/param defaults matching a case
        // on an imported enum whose name matches the property/param name.
        foreach ($nodeFinder->findInstanceOf($input->ast, Node\Param::class) as $param) {
            assert($param instanceof Node\Param);

            if ($param->default === null || ! $param->default instanceof Scalar\String_) {
                continue;
            }

            if (! $this->typeIsString($param->type)) {
                continue;
            }

            if (! $param->var instanceof Expr\Variable || ! is_string($param->var->name)) {
                continue;
            }

            if ($this->isInsideWireFormatScope($param, $parentMap)) {
                continue;
            }

            $paramName = $param->var->name;
            $value = $param->default->value;

            foreach ($importedEnums as $shortName => $info) {
                if (! $this->nameMatches($paramName, $shortName)) {
                    continue;
                }

                $caseName = $this->matchCase($info, $value);

                if ($caseName === null) {
                    continue;
                }

                $dedupe = "param::{$shortName}::{$paramName}::{$value}";

                if (isset($seen[$dedupe])) {
                    continue;
                }

                $seen[$dedupe] = true;

                $matches[] = $this->makeMatch(
                    name: 'param_default',
                    line: $param->getStartLine(),
                    content: $this->getSnippet($input->content, $param->getStartLine()),
                    shortName: $shortName,
                    fqcn: $info['fqcn'],
                    caseName: $caseName,
                    value: $value,
                    subject: "\${$paramName}",
                );

                break;
            }
        }

        return $input->with(matches: $matches);
    }

    /**
     * Reflect every imported class name. Keep only those that are enums.
     *
     * @param  array<string, string>  $useStatements  alias => FQCN
     * @return array<string, array{fqcn: string, short: string, backing: ?string, cases: array<string, string>}>
     */
    private function resolveImportedEnums(array $useStatements): array
    {
        $out = [];

        foreach ($useStatements as $alias => $fqcn) {
            $info = $this->reflectEnum($fqcn);

            if ($info === null) {
                continue;
            }

            $out[$alias] = $info;
        }

        return $out;
    }

    /**
     * @return array{fqcn: string, short: string, backing: ?string, cases: array<string, string>}|null
     */
    private function reflectEnum(string $fqcn): ?array
    {
        if (isset(self::$reflectionCache[$fqcn])) {
            $ref = self::$reflectionCache[$fqcn];
        } else {
            try {
                if (! enum_exists($fqcn)) {
                    return null;
                }

                $ref = new ReflectionEnum($fqcn);
            } catch (ReflectionException) {
                return null;
            }

            self::$reflectionCache[$fqcn] = $ref;
        }

        $cases = [];

        foreach ($ref->getCases() as $case) {
            if ($ref->isBacked()) {
                $backingValue = $case->getBackingValue();
                $cases[(string) $backingValue] = $case->getName();
            } else {
                $cases[$case->getName()] = $case->getName();
            }
        }

        $short = $ref->getShortName();
        $backing = null;

        if ($ref->isBacked()) {
            $bType = $ref->getBackingType();
            $backing = $bType instanceof \ReflectionNamedType ? $bType->getName() : null;
        }

        return [
            'fqcn' => $fqcn,
            'short' => $short,
            'backing' => $backing,
            'cases' => $cases,
        ];
    }

    /**
     * Does the given identifier (arg name, param name) plausibly refer to
     * this enum? Matches on exact name or suffix of the enum short name.
     */
    private function nameMatches(string $identifier, string $enumShort): bool
    {
        $id = strtolower($identifier);
        $short = strtolower($enumShort);

        if ($id === $short) {
            return true;
        }

        // arg "direction" matches enum "PortDirection"
        return strlen($id) >= 3 && str_ends_with($short, $id);
    }

    /**
     * @param  array{backing: ?string, cases: array<string, string>}  $info
     */
    private function matchCase(array $info, string $value): ?string
    {
        // Backed enum: compare against backing values.
        if ($info['backing'] === 'string') {
            return $info['cases'][$value] ?? null;
        }

        if ($info['backing'] === 'int') {
            if (ctype_digit($value) || (str_starts_with($value, '-') && ctype_digit(substr($value, 1)))) {
                return $info['cases'][$value] ?? null;
            }

            return null;
        }

        // Unit enum: compare against case names.
        return $info['cases'][$value] ?? null;
    }

    /**
     * @param  array<int, Node>  $parentMap
     */
    private function isInsideWireFormatScope(Node $node, array $parentMap): bool
    {
        $current = $parentMap[spl_object_id($node)] ?? null;

        while ($current !== null) {
            if ($current instanceof Node\Stmt\ClassMethod) {
                $methodName = $current->name->toString();

                if (in_array($methodName, self::WIRE_FORMAT_METHODS, true)) {
                    return true;
                }
            }

            if ($current instanceof Node\Stmt\Class_ && $current->extends !== null) {
                $parent = $current->extends->toString();

                foreach (self::WIRE_FORMAT_PARENT_SUFFIXES as $suffix) {
                    if ($parent === $suffix || str_ends_with($parent, '\\' . $suffix)) {
                        return true;
                    }
                }
            }

            $current = $parentMap[spl_object_id($current)] ?? null;
        }

        return false;
    }

    private function typeIsString(?Node $type): bool
    {
        if ($type === null) {
            return false;
        }

        if ($type instanceof Node\NullableType) {
            return $this->typeIsString($type->type);
        }

        if ($type instanceof Node\Identifier) {
            return strtolower($type->toString()) === 'string';
        }

        return false;
    }

    /**
     * @param  array<Node>  $ast
     * @return array<int, Node>
     */
    private function buildParentMap(array $ast): array
    {
        $parents = [];
        $stack = [];

        $walker = static function (mixed $node) use (&$walker, &$parents, &$stack): void {
            if (! $node instanceof Node) {
                return;
            }

            if (! empty($stack)) {
                $parents[spl_object_id($node)] = end($stack);
            }

            $stack[] = $node;

            foreach ($node->getSubNodeNames() as $name) {
                $child = $node->{$name};

                if (is_array($child)) {
                    foreach ($child as $c) {
                        $walker($c);
                    }
                } else {
                    $walker($child);
                }
            }

            array_pop($stack);
        };

        foreach ($ast as $node) {
            $walker($node);
        }

        return $parents;
    }

    private function getSnippet(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return isset($lines[$line - 1]) ? trim($lines[$line - 1]) : '';
    }

    private function makeMatch(
        string $name,
        int $line,
        string $content,
        string $shortName,
        string $fqcn,
        string $caseName,
        string $value,
        string $subject,
    ): MatchResult {
        return new MatchResult(
            name: $name,
            pattern: '',
            match: "'{$value}' ≈ {$shortName}::{$caseName}",
            line: $line,
            offset: null,
            content: $content,
            groups: [
                'subject' => $subject,
                'value' => $value,
                'enum_short' => $shortName,
                'enum_fqcn' => $fqcn,
                'case' => $caseName,
            ],
        );
    }
}
