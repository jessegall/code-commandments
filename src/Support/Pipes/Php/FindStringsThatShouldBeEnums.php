<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use Composer\Autoload\ClassLoader;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\CallGraph\EnumSummary;
use JesseGall\CodeCommandments\Support\VendorPath;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use ReflectionEnum;

/**
 * Flag raw string literals that are really enum cases in disguise.
 *
 *   1. Named arg passed to a ctor / call where the arg name matches an
 *      enum (imported in the file, or — with a CodebaseIndex — any enum
 *      defined in the project) and the value matches one of its cases.
 *
 *   2. A `string`-typed parameter with a default literal whose value
 *      matches a case on an enum whose short name shares a suffix with
 *      the parameter name.
 *
 *   3. A `string`-typed parameter whose call sites across the project
 *      use a small closed set of literals. When a CodebaseIndex is
 *      injected, the pipe walks every call site to the containing
 *      method and flags the param even when no enum exists yet — that
 *      is the highest-value moment to suggest creating one.
 *
 * Enum introspection prefers reflection (when the enum is autoloadable)
 * and falls back to AST parsing for project enums known via the
 * CodebaseIndex.
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

    /** @var array<string, array{fqcn: string, short: string, backing: ?string, cases: array<string, string>}|false> */
    private static array $enumCache = [];

    /** @var array<string, bool> FQCN => whether the class file lives under /vendor/. */
    private static array $vendorCache = [];

    private static ?ClassLoader $composerLoader = null;

    private static bool $composerLoaderResolved = false;

    private ?CodebaseIndex $codebaseIndex = null;

    /**
     * Minimum number of distinct call sites a parameter must have before the
     * literal-frequency heuristic considers it a closed set rather than a
     * one-off.
     */
    private int $minCallSites = 2;

    /**
     * Maximum number of distinct literal values a parameter may receive
     * before the heuristic gives up — past this it's unlikely to be a
     * closed set in disguise.
     */
    private int $maxDistinctLiterals = 5;

    public function withCodebaseIndex(CodebaseIndex $index): self
    {
        $this->codebaseIndex = $index;

        return $this;
    }

    public function handle(mixed $input): mixed
    {
        if ($input->ast === null) {
            return $input->with(matches: []);
        }

        $importedEnums = $this->resolveImportedEnums($input->useStatements);
        $hasIndex = $this->codebaseIndex !== null;

        // Without imported enums AND no codebase index, none of the
        // patterns this pipe knows about can fire.
        if (empty($importedEnums) && ! $hasIndex) {
            return $input->with(matches: []);
        }

        $nodeFinder = new NodeFinder;
        $parentMap = $this->buildParentMap($input->ast);

        $matches = [];
        $seen = [];

        // Pattern 1: named args whose value is a string literal matching
        // a case on any enum (imported or — with a CodebaseIndex — any
        // project enum) whose name matches the arg name.
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

            $candidate = $this->findCandidateEnum($argName, $value, $importedEnums);

            if ($candidate === null) {
                continue;
            }

            // Skip when the called target lives in /vendor/ — the consumer
            // can't change a third-party method's string-typed parameter to
            // an enum, so flagging the literal is noise.
            if ($this->callTargetIsVendor($arg, $parentMap, $input->useStatements, $input->namespace)) {
                continue;
            }

            [$shortName, $info, $importedAlias] = $candidate;

            $dedupe = "arg::{$info['fqcn']}::{$argName}::{$value}";

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
                caseName: $info['cases'][$value] ?? $this->matchCase($info, $value) ?? $value,
                value: $value,
                subject: "\${$argName}",
                requiresImport: $importedAlias === null,
            );
        }

        // Pattern 2 + 3: string-typed parameters.
        //   2. With a default literal that matches a case on a name-matched enum.
        //   3. With OR without a default, when call-site analysis (via the
        //      codebase index) shows the literals form a closed set.
        foreach ($nodeFinder->findInstanceOf($input->ast, Node\Param::class) as $param) {
            assert($param instanceof Node\Param);

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
            $matched = false;

            // Pattern 2 — default literal matching a known enum case.
            if ($param->default instanceof Scalar\String_) {
                $value = $param->default->value;
                $candidate = $this->findCandidateEnum($paramName, $value, $importedEnums);

                if ($candidate !== null) {
                    [$shortName, $info, $importedAlias] = $candidate;
                    // Pattern 2 short-circuits Pattern 3 for this param even
                    // when the dedupe key suppresses a duplicate emission.
                    $matched = true;

                    $dedupe = "param::{$info['fqcn']}::{$paramName}::{$value}";

                    if (! isset($seen[$dedupe])) {
                        $seen[$dedupe] = true;

                        $matches[] = $this->makeMatch(
                            name: 'param_default',
                            line: $param->getStartLine(),
                            content: $this->getSnippet($input->content, $param->getStartLine()),
                            shortName: $shortName,
                            fqcn: $info['fqcn'],
                            caseName: $info['cases'][$value] ?? $this->matchCase($info, $value) ?? $value,
                            value: $value,
                            subject: "\${$paramName}",
                            requiresImport: $importedAlias === null,
                        );
                    }
                }
            }

            // Pattern 3 — literal-frequency heuristic. Walks every call site
            // to the containing method via the codebase index, collects the
            // literals passed at this parameter's position, and flags a
            // closed set even when no enum exists yet.
            if (! $matched && $hasIndex) {
                $closedSet = $this->collectClosedSet($input, $param, $parentMap);

                if ($closedSet !== null) {
                    [$paramIndex, $literals] = $closedSet;
                    $candidate = $this->findCandidateEnumForLiterals($paramName, $literals, $importedEnums);

                    if ($candidate !== null) {
                        [$shortName, $info, $importedAlias] = $candidate;
                        $dedupe = "param_freq::{$info['fqcn']}::{$paramName}";

                        if (! isset($seen[$dedupe])) {
                            $seen[$dedupe] = true;

                            $matches[] = $this->makeMatchForClosedSet(
                                line: $param->getStartLine(),
                                content: $this->getSnippet($input->content, $param->getStartLine()),
                                shortName: $shortName,
                                fqcn: $info['fqcn'],
                                literals: $literals,
                                subject: "\${$paramName}",
                                requiresImport: $importedAlias === null,
                                matchedEnum: true,
                            );
                        }
                    } else {
                        $dedupe = "param_freq::{$paramName}::" . implode(',', $literals);

                        if (! isset($seen[$dedupe])) {
                            $seen[$dedupe] = true;

                            $matches[] = $this->makeMatchForClosedSet(
                                line: $param->getStartLine(),
                                content: $this->getSnippet($input->content, $param->getStartLine()),
                                shortName: $this->suggestEnumName($paramName),
                                fqcn: '',
                                literals: $literals,
                                subject: "\${$paramName}",
                                requiresImport: false,
                                matchedEnum: false,
                            );
                        }
                    }
                }
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
        if (array_key_exists($fqcn, self::$enumCache)) {
            $cached = self::$enumCache[$fqcn];

            return $cached === false ? null : $cached;
        }

        $info = $this->resolveEnumInfo($fqcn);
        self::$enumCache[$fqcn] = $info ?? false;

        return $info;
    }

    /**
     * @return array{fqcn: string, short: string, backing: ?string, cases: array<string, string>}|null
     */
    private function resolveEnumInfo(string $fqcn): ?array
    {
        // Already loaded — reflection is safe.
        if (enum_exists($fqcn, autoload: false)) {
            try {
                return $this->enumInfoFromReflection(new ReflectionEnum($fqcn));
            } catch (\Throwable) {
                return null;
            }
        }

        // If it's a loaded non-enum class, it's definitively not an enum.
        if (class_exists($fqcn, autoload: false) || interface_exists($fqcn, autoload: false) || trait_exists($fqcn, autoload: false)) {
            return null;
        }

        // Not loaded. Parse the source file without autoloading so a broken
        // consumer class can't fatal the whole run.
        return $this->enumInfoFromAst($fqcn);
    }

    /**
     * @return array{fqcn: string, short: string, backing: ?string, cases: array<string, string>}
     */
    private function enumInfoFromReflection(ReflectionEnum $ref): array
    {
        $cases = [];

        foreach ($ref->getCases() as $case) {
            if ($ref->isBacked()) {
                $backingValue = $case->getBackingValue();
                $cases[(string) $backingValue] = $case->getName();
            } else {
                $cases[$case->getName()] = $case->getName();
            }
        }

        $backing = null;

        if ($ref->isBacked()) {
            $bType = $ref->getBackingType();
            $backing = $bType instanceof \ReflectionNamedType ? $bType->getName() : null;
        }

        return [
            'fqcn' => $ref->getName(),
            'short' => $ref->getShortName(),
            'backing' => $backing,
            'cases' => $cases,
        ];
    }

    /**
     * @return array{fqcn: string, short: string, backing: ?string, cases: array<string, string>}|null
     */
    private function enumInfoFromAst(string $fqcn): ?array
    {
        $loader = $this->getComposerLoader();

        if ($loader === null) {
            return null;
        }

        $file = $loader->findFile($fqcn);

        if ($file === false || ! is_file($file)) {
            return null;
        }

        $content = @file_get_contents($file);

        if ($content === false || $content === '') {
            return null;
        }

        try {
            $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($content);
        } catch (\Throwable) {
            return null;
        }

        if ($ast === null) {
            return null;
        }

        $parts = explode('\\', $fqcn);
        $short = array_pop($parts);
        $namespace = implode('\\', $parts);

        $enumNode = $this->findEnumNode($ast, $short, $namespace);

        if ($enumNode === null) {
            return null;
        }

        $cases = [];
        $backing = null;

        if ($enumNode->scalarType instanceof Node\Identifier) {
            $backing = $enumNode->scalarType->toString();
        }

        foreach ($enumNode->stmts as $stmt) {
            if (! $stmt instanceof Node\Stmt\EnumCase) {
                continue;
            }

            $caseName = $stmt->name->toString();

            if ($backing !== null && $stmt->expr !== null) {
                $value = $this->extractScalarValue($stmt->expr);

                if ($value === null) {
                    continue;
                }

                $cases[(string) $value] = $caseName;
            } else {
                $cases[$caseName] = $caseName;
            }
        }

        return [
            'fqcn' => $fqcn,
            'short' => $short,
            'backing' => $backing,
            'cases' => $cases,
        ];
    }

    /**
     * @param  array<Node>  $ast
     */
    private function findEnumNode(array $ast, string $short, string $namespace): ?Node\Stmt\Enum_
    {
        foreach ($ast as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                $ns = $node->name?->toString() ?? '';

                if ($ns !== $namespace) {
                    continue;
                }

                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof Node\Stmt\Enum_ && $stmt->name?->toString() === $short) {
                        return $stmt;
                    }
                }
            } elseif ($node instanceof Node\Stmt\Enum_ && $namespace === '' && $node->name?->toString() === $short) {
                return $node;
            }
        }

        return null;
    }

    private function extractScalarValue(Node\Expr $expr): string|int|null
    {
        if ($expr instanceof Scalar\String_) {
            return $expr->value;
        }

        if ($expr instanceof Scalar\Int_) {
            return $expr->value;
        }

        if ($expr instanceof Expr\UnaryMinus && $expr->expr instanceof Scalar\Int_) {
            return -$expr->expr->value;
        }

        return null;
    }

    private function getComposerLoader(): ?ClassLoader
    {
        if (self::$composerLoaderResolved) {
            return self::$composerLoader;
        }

        self::$composerLoaderResolved = true;

        foreach (spl_autoload_functions() ?: [] as $autoload) {
            if (is_array($autoload) && isset($autoload[0]) && $autoload[0] instanceof ClassLoader) {
                self::$composerLoader = $autoload[0];

                return self::$composerLoader;
            }
        }

        return null;
    }

    /**
     * Does the given identifier (arg name, param name) plausibly refer to
     * this enum? Matches both directions:
     *   - identifier is a suffix of the enum short name (`$direction` ≈ `PortDirection`)
     *   - enum short name is a suffix of the identifier (`$mirroringVerb` ≈ `Verb`)
     */
    private function nameMatches(string $identifier, string $enumShort): bool
    {
        $id = strtolower($identifier);
        $short = strtolower($enumShort);

        if ($id === $short) {
            return true;
        }

        // arg "direction" matches enum "PortDirection" — id is suffix of short.
        if (strlen($id) >= 3 && str_ends_with($short, $id)) {
            return true;
        }

        // arg "mirroringVerb" matches enum "Verb" — short is suffix of id.
        return strlen($short) >= 3 && str_ends_with($id, $short);
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
     * Walk up from a named argument to its enclosing call/new/attribute
     * and decide whether that target class lives under /vendor/. Only
     * `Expr\New_`, `Expr\StaticCall`, and `Node\Attribute` are resolved —
     * instance method calls (`$x->m(...)`) would require type inference
     * and are left as a non-vendor default.
     *
     * @param  array<int, Node>  $parentMap
     * @param  array<string, string>  $useStatements  alias => FQCN
     */
    private function callTargetIsVendor(Node\Arg $arg, array $parentMap, array $useStatements, ?string $namespace): bool
    {
        $parent = $parentMap[spl_object_id($arg)] ?? null;

        // Args are typically wrapped in their call node directly, but walk
        // a couple of hops in case the parser adds an intermediate.
        while ($parent !== null) {
            if ($parent instanceof Expr\New_
                || $parent instanceof Expr\StaticCall
                || $parent instanceof Node\Attribute
            ) {
                break;
            }

            if ($parent instanceof Expr\MethodCall
                || $parent instanceof Expr\NullsafeMethodCall
                || $parent instanceof Expr\FuncCall
            ) {
                return false;
            }

            $parent = $parentMap[spl_object_id($parent)] ?? null;
        }

        if ($parent === null) {
            return false;
        }

        // `New_`/`StaticCall` carry the target on `->class`; `Attribute` on `->name`.
        $classNode = $parent instanceof Node\Attribute ? $parent->name : $parent->class;

        if (! $classNode instanceof Node\Name) {
            // Anonymous-class new or dynamic class — can't resolve, don't filter.
            return false;
        }

        $fqcn = $this->resolveFqcn($classNode, $useStatements, $namespace);

        return $this->fqcnIsVendor($fqcn);
    }

    /**
     * @param  array<string, string>  $useStatements
     */
    private function resolveFqcn(Node\Name $name, array $useStatements, ?string $namespace): string
    {
        if ($name->isFullyQualified()) {
            return ltrim($name->toString(), '\\');
        }

        $parts = explode('\\', $name->toString());
        $first = $parts[0];

        if (isset($useStatements[$first])) {
            $parts[0] = $useStatements[$first];

            return implode('\\', $parts);
        }

        if ($namespace !== null && $namespace !== '') {
            return $namespace . '\\' . $name->toString();
        }

        return $name->toString();
    }

    private function fqcnIsVendor(string $fqcn): bool
    {
        if (array_key_exists($fqcn, self::$vendorCache)) {
            return self::$vendorCache[$fqcn];
        }

        $loader = $this->getComposerLoader();

        if ($loader === null) {
            return self::$vendorCache[$fqcn] = false;
        }

        $file = $loader->findFile($fqcn);

        if ($file === false) {
            return self::$vendorCache[$fqcn] = false;
        }

        return self::$vendorCache[$fqcn] = VendorPath::isVendor($file);
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
        bool $requiresImport = false,
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
                'requires_import' => $requiresImport ? '1' : '',
            ],
        );
    }

    /**
     * Build a match for the literal-frequency heuristic.
     *
     * @param  list<string>  $literals
     */
    private function makeMatchForClosedSet(
        int $line,
        string $content,
        string $shortName,
        string $fqcn,
        array $literals,
        string $subject,
        bool $requiresImport,
        bool $matchedEnum,
    ): MatchResult {
        sort($literals);

        return new MatchResult(
            name: $matchedEnum ? 'param_closed_set_matched_enum' : 'param_closed_set',
            pattern: '',
            match: $matchedEnum
                ? "{$subject} ≈ {$shortName} (" . implode(', ', $literals) . ')'
                : "{$subject} closed set: " . implode(', ', $literals),
            line: $line,
            offset: null,
            content: $content,
            groups: [
                'subject' => $subject,
                'value' => implode(', ', $literals),
                'enum_short' => $shortName,
                'enum_fqcn' => $fqcn,
                'literals' => implode(', ', array_map(fn ($v) => "'{$v}'", $literals)),
                'requires_import' => $requiresImport ? '1' : '',
                'matched_enum' => $matchedEnum ? '1' : '',
            ],
        );
    }

    /**
     * Pick a candidate enum for a (name, value) pair: prefer imported enums,
     * then fall back to any project enum known via the codebase index.
     *
     * @param  array<string, array{fqcn: string, short: string, backing: ?string, cases: array<string, string>}>  $importedEnums
     * @return array{0: string, 1: array{fqcn: string, short: string, backing: ?string, cases: array<string, string>}, 2: ?string}|null  [displayShort, info, importedAlias]
     */
    private function findCandidateEnum(string $identifier, string $value, array $importedEnums): ?array
    {
        foreach ($importedEnums as $alias => $info) {
            if (! $this->nameMatches($identifier, $alias) && ! $this->nameMatches($identifier, $info['short'])) {
                continue;
            }

            if ($this->matchCase($info, $value) !== null) {
                return [$alias, $info, $alias];
            }
        }

        if ($this->codebaseIndex === null) {
            return null;
        }

        foreach ($this->codebaseIndex->allEnums() as $summary) {
            if (! $this->nameMatches($identifier, $summary->short)) {
                continue;
            }

            $info = $this->infoFromEnumSummary($summary);

            if ($this->matchCase($info, $value) !== null) {
                return [$summary->short, $info, null];
            }
        }

        return null;
    }

    /**
     * Find a name-matched enum whose cases cover every observed literal.
     *
     * @param  list<string>  $literals
     * @param  array<string, array{fqcn: string, short: string, backing: ?string, cases: array<string, string>}>  $importedEnums
     * @return array{0: string, 1: array{fqcn: string, short: string, backing: ?string, cases: array<string, string>}, 2: ?string}|null
     */
    private function findCandidateEnumForLiterals(string $identifier, array $literals, array $importedEnums): ?array
    {
        foreach ($importedEnums as $alias => $info) {
            if (! $this->nameMatches($identifier, $alias) && ! $this->nameMatches($identifier, $info['short'])) {
                continue;
            }

            if ($this->casesCoverAll($info, $literals)) {
                return [$alias, $info, $alias];
            }
        }

        if ($this->codebaseIndex === null) {
            return null;
        }

        foreach ($this->codebaseIndex->allEnums() as $summary) {
            if (! $this->nameMatches($identifier, $summary->short)) {
                continue;
            }

            $info = $this->infoFromEnumSummary($summary);

            if ($this->casesCoverAll($info, $literals)) {
                return [$summary->short, $info, null];
            }
        }

        return null;
    }

    /**
     * @param  array{backing: ?string, cases: array<string, string>}  $info
     * @param  list<string>  $literals
     */
    private function casesCoverAll(array $info, array $literals): bool
    {
        foreach ($literals as $literal) {
            if ($this->matchCase($info, $literal) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{fqcn: string, short: string, backing: ?string, cases: array<string, string>}
     */
    private function infoFromEnumSummary(EnumSummary $summary): array
    {
        return [
            'fqcn' => $summary->fqcn,
            'short' => $summary->short,
            'backing' => $summary->backing,
            'cases' => $summary->cases,
        ];
    }

    /**
     * Walk every call site to the method that contains $param and collect
     * the literals passed at $param's position. Returns null when the
     * heuristic cannot fire (not enough call sites, not a closed set, or
     * the values don't look like case names).
     *
     * @param  array<int, Node>  $parentMap
     * @return array{0: int, 1: list<string>}|null  [paramIndex, sortedDistinctLiterals]
     */
    private function collectClosedSet(mixed $input, Node\Param $param, array $parentMap): ?array
    {
        if ($this->codebaseIndex === null) {
            return null;
        }

        [$classFqcn, $methodName, $paramIndex] = $this->resolveContainingMethod($param, $parentMap, $input->namespace ?? null) ?? [null, null, null];

        if ($classFqcn === null || $methodName === null || $paramIndex === null) {
            return null;
        }

        $paramName = $param->var instanceof Expr\Variable && is_string($param->var->name)
            ? $param->var->name
            : null;

        $callers = $this->codebaseIndex->callersOf($classFqcn, $methodName);

        if (empty($callers)) {
            return null;
        }

        $literals = [];
        $sites = 0;

        foreach ($callers as $site) {
            $literal = $this->literalFromCallSite($site->argExprs, $paramIndex, $paramName);

            if ($literal === null) {
                // Mixed call: at least one site passes something non-literal.
                // That breaks the closed-set guarantee.
                return null;
            }

            $sites++;

            if (! in_array($literal, $literals, true)) {
                $literals[] = $literal;
            }

            if (count($literals) > $this->maxDistinctLiterals) {
                return null;
            }
        }

        if ($sites < $this->minCallSites) {
            return null;
        }

        if (count($literals) < 2) {
            // A single literal is hardly a "set" — leave it alone.
            return null;
        }

        foreach ($literals as $literal) {
            if (! $this->looksLikeCaseName($literal)) {
                return null;
            }
        }

        return [$paramIndex, $literals];
    }

    /**
     * Extract the literal value the call site passed for the parameter at
     * $paramIndex. Honours named-argument syntax: a call site that names
     * arguments out of position is matched by `argName === $paramName`.
     *
     * @param  list<array{kind: string, name?: string, prop?: string, value?: string, argName?: string}>  $argExprs
     */
    private function literalFromCallSite(array $argExprs, int $paramIndex, ?string $paramName): ?string
    {
        // First: any named arg matching this parameter wins regardless of position.
        if ($paramName !== null) {
            foreach ($argExprs as $entry) {
                if (($entry['argName'] ?? null) !== $paramName) {
                    continue;
                }

                return $entry['kind'] === 'string_literal' ? ($entry['value'] ?? null) : null;
            }
        }

        // Fall back to positional. Skip any named-arg entries before this index
        // so positional reasoning matches PHP's resolution.
        $positional = [];

        foreach ($argExprs as $entry) {
            if (isset($entry['argName'])) {
                continue;
            }

            $positional[] = $entry;
        }

        $entry = $positional[$paramIndex] ?? null;

        if ($entry === null) {
            return null;
        }

        return $entry['kind'] === 'string_literal' ? ($entry['value'] ?? null) : null;
    }

    /**
     * Walks up the parent map to find the enclosing class + method, returning
     * the resolved FQCN, method name, and param index.
     *
     * @param  array<int, Node>  $parentMap
     * @return array{0: string, 1: string, 2: int}|null
     */
    private function resolveContainingMethod(Node\Param $param, array $parentMap, ?string $namespace): ?array
    {
        $current = $parentMap[spl_object_id($param)] ?? null;
        $method = null;
        $class = null;

        while ($current !== null) {
            if ($method === null && $current instanceof Node\Stmt\ClassMethod) {
                $method = $current;
            }

            if ($current instanceof Node\Stmt\Class_) {
                $class = $current;
                break;
            }

            $current = $parentMap[spl_object_id($current)] ?? null;
        }

        if ($method === null || $class === null || $class->name === null) {
            return null;
        }

        $shortName = $class->name->toString();
        $fqcn = $namespace !== null && $namespace !== ''
            ? $namespace . '\\' . $shortName
            : $shortName;

        $index = array_search($param, $method->params, true);

        if ($index === false) {
            return null;
        }

        return [$fqcn, $method->name->toString(), (int) $index];
    }

    /**
     * Strings that plausibly stand in for an enum case: short, lowercase-ish
     * identifiers, no whitespace, no special chars, not numeric, not empty.
     */
    private function looksLikeCaseName(string $value): bool
    {
        if ($value === '' || strlen($value) > 64) {
            return false;
        }

        return (bool) preg_match('/^[a-z_][a-z0-9_-]*$/i', $value);
    }

    /**
     * Best-effort guess at an enum name for a param when no enum exists yet.
     */
    private function suggestEnumName(string $paramName): string
    {
        $clean = trim($paramName, '_');

        if ($clean === '') {
            return 'Kind';
        }

        return ucfirst($clean);
    }
}
