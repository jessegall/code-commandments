<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionType;
use Throwable;

/**
 * The type of each expression and the declaration each call binds to, inferred the way PHP itself resolves them: $this
 * and self to the enclosing class, new X to X, a parameter or property to its declared type, a call to its declared
 * return type, a local variable to what was last assigned to it. It reads the declarations of every judged file and,
 * once the project's autoloader is in, the classes it loads. Each expression it types carries the attribute `type`
 * ({name, nullable} or {keyword}), and each call and construction it binds the attribute `target`.
 */
final class Types
{
    /**
     * Each declared class by its lowercased name: its name, file, parent, interfaces, traits, methods by lowercased name
     * ({name, returns, static}) and properties by name (their type).
     *
     * @var array<string, array{name: string, file: ?string, parent: ?string, interfaces: list<string>, traits: list<string>, methods: array<string, array{name: string, returns: ?array, static: bool}>, properties: array<string, ?array>}>
     */
    private array $classes = [];

    /**
     * Each declared function's return type by its lowercased name.
     *
     * @var array<string, array{name: string, file: ?string, returns: ?array}>
     */
    private array $functions = [];

    private bool $loads = false;

    /**
     * The files whose declarations the file being inferred read, by path.
     *
     * @var array<string, true>
     */
    private array $read = [];

    /**
     * Reads every class and function a file declares.
     *
     * @param  list<Node>  $statements
     */
    public function declare(array $statements, string $path): void
    {
        $traverser = new NodeTraverser(new class($this, $path) extends NodeVisitorAbstract
        {
            public function __construct(private readonly Types $types, private readonly string $path) {}

            public function enterNode(Node $node): null
            {
                if ($node instanceof Stmt\ClassLike && $node->namespacedName !== null) {
                    $this->types->declareClass($node, $this->path);
                } elseif ($node instanceof Stmt\Function_ && $node->namespacedName !== null) {
                    $this->types->declareFunction($node, $this->path);
                }

                return null;
            }
        });
        $traverser->traverse($statements);
    }

    /**
     * From here on, a class no judged file declares is read through the project's autoloader.
     */
    public function loadsClasses(): void
    {
        $this->loads = true;
    }

    public function declareClass(Stmt\ClassLike $class, string $path): void
    {
        $name = $class->namespacedName->toString();
        $methods = [];
        $properties = [];
        foreach ($class->getMethods() as $method) {
            $methods[strtolower($method->name->toString())] = ['name' => $method->name->toString(), 'returns' => $this->written($method->returnType, null), 'static' => $method->isStatic(), 'abstract' => $method->isAbstract()];
            if ($method->name->toLowerString() === '__construct') {
                foreach ($method->params as $parameter) {
                    if ($parameter->flags !== 0 && $parameter->var instanceof Expr\Variable && is_string($parameter->var->name)) {
                        $written = $this->written($parameter->type, $name);
                        $documented = $parameter->getAttribute('documented');
                        $properties[$parameter->var->name] = isset($documented['element']) ? $this->narrowedBy($documented, $written) : $written;
                    }
                }
            }
        }
        foreach ($class->getProperties() as $property) {
            foreach ($property->props as $declared) {
                $properties[$declared->name->toString()] = $this->written($property->type, $name);
            }
        }
        $constants = [];
        $cases = [];
        foreach ($class->stmts as $statement) {
            if ($statement instanceof Stmt\ClassConst) {
                foreach ($statement->consts as $constant) {
                    $constants[$constant->name->toString()] = self::folded($constant->value) ?? true;
                }
            }
            if ($statement instanceof Stmt\EnumCase) {
                $constants[$statement->name->toString()] = true;
                $cases[] = $statement->name->toString();
            }
        }
        foreach ($class->getAttribute('documentedProperties', []) as $documented => $type) {
            if (! array_key_exists($documented, $properties)) {
                $properties[$documented] = $type;
            }
        }
        $traits = [];
        foreach ($class->getTraitUses() as $use) {
            foreach ($use->traits as $trait) {
                $traits[] = $trait->toString();
            }
        }
        $this->classes[strtolower($name)] = [
            'name' => $name,
            'file' => $path,
            'parent' => $class instanceof Stmt\Class_ && $class->extends !== null ? $class->extends->toString() : null,
            'interfaces' => array_map(static fn (Node\Name $each): string => $each->toString(), match (true) {
                $class instanceof Stmt\Class_, $class instanceof Stmt\Enum_ => $class->implements,
                $class instanceof Stmt\Interface_ => $class->extends,
                default => [],
            }),
            'traits' => $traits,
            'trait' => $class instanceof Stmt\Trait_,
            'methods' => $methods,
            'properties' => $properties,
            'constants' => $constants,
            'cases' => $class instanceof Stmt\Enum_ ? $cases : null,
        ];
    }

    /**
     * Whether a match with no default arm names every case of the enum its subject is: an enum's cases are closed, so
     * a match naming each cannot fall through to an UnhandledMatchError.
     */
    public function coversEveryCase(Expr\Match_ $match): bool
    {
        $enum = $match->cond->getAttribute('type')['name'] ?? null;
        $cases = $enum !== null ? ($this->classes[strtolower(ltrim($enum, '\\'))]['cases'] ?? null) : null;
        if ($cases === null) {
            return false;
        }
        $named = [];
        foreach ($match->arms as $arm) {
            foreach ($arm->conds ?? [] as $condition) {
                if (! $condition instanceof Expr\ClassConstFetch || ! $condition->name instanceof Node\Identifier || ! $condition->class instanceof Node\Name
                    || strtolower($condition->class->toString()) !== strtolower(ltrim($enum, '\\'))) {
                    return false;
                }
                $named[$condition->name->toString()] = true;
            }
        }

        return array_diff($cases, array_keys($named)) === [];
    }

    /**
     * The value a constant's initializer folds to when it is a literal: `{type, value}`, `{type: null}` for null.
     *
     * @return ?array{type: string, value?: string}
     */
    private static function folded(Expr $value): ?array
    {
        return match (true) {
            $value instanceof Node\Scalar\String_ => ['type' => 'string', 'value' => $value->value],
            $value instanceof Node\Scalar\Int_ => ['type' => 'int', 'value' => (string) $value->value],
            $value instanceof Node\Scalar\Float_ => ['type' => 'float', 'value' => (string) $value->value],
            $value instanceof Expr\ConstFetch && in_array($value->name->toLowerString(), ['true', 'false'], true) => ['type' => 'bool', 'value' => $value->name->toLowerString()],
            $value instanceof Expr\ConstFetch && $value->name->toLowerString() === 'null' => ['type' => 'null'],
            default => null,
        };
    }

    /**
     * The file the project declares a class in, when one of the files read declares it.
     */
    public function fileOf(string $class): ?string
    {
        return $this->classes[strtolower(ltrim($class, '\\'))]['file'] ?? null;
    }

    public function declared(string $class): ?array
    {
        return $this->declaration($class);
    }

    /**
     * @return list<string>
     */
    public function readSoFar(string $path): array
    {
        return array_values(array_filter(array_keys($this->read), static fn (string $file): bool => $file !== $path));
    }

    public function declareFunction(Stmt\Function_ $function, string $path): void
    {
        $name = $function->namespacedName->toString();
        $this->functions[strtolower($name)] = ['name' => $name, 'file' => $path, 'returns' => $this->written($function->returnType, null)];
    }

    /**
     * Types every expression of a file and binds every call, and says which other files' declarations it read.
     *
     * @param  list<Node>  $statements
     * @return list<string>
     */
    public function infer(array $statements, string $path): array
    {
        $this->read = [];
        $traverser = new NodeTraverser(new class($this) extends NodeVisitorAbstract
        {
            /**
             * @var list<array{class: ?string, variables: array<string, ?array>}>
             */
            private array $scopes = [['class' => null, 'variables' => []]];

            private ?string $class = null;

            /**
             * @var list<?string>
             */
            private array $enclosingClasses = [];

            public function __construct(private readonly Types $types) {}

            public function enterNode(Node $node): null
            {
                if ($node instanceof Stmt\ClassLike) {
                    $this->enclosingClasses[] = $this->class;
                    $this->class = $node->namespacedName?->toString();
                }
                if ($node instanceof Node\UseItem && ($file = $this->types->fileOf($node->name->toString())) !== null) {
                    $node->setAttribute('resolves', $file);
                }
                if ($node instanceof Stmt && ($documented = $node->getAttribute('documentedVariables')) !== null) {
                    foreach ($documented as $name => $type) {
                        $this->scopes[array_key_last($this->scopes)]['variables'][$name] = $type;
                    }
                }
                if ($node instanceof Stmt\ClassMethod && $this->class !== null) {
                    $node->setAttribute('obligations', $this->types->obligations($this->class, $node->name->toString()));
                }
                if ($node instanceof Stmt\Foreach_ && $node->valueVar instanceof Expr\Variable && is_string($node->valueVar->name)) {
                    $this->scopes[array_key_last($this->scopes)]['variables'][$node->valueVar->name] = null;
                    $node->expr->setAttribute('iteratedInto', $node->valueVar->name);
                }
                if ($node instanceof Node\FunctionLike) {
                    $variables = $node instanceof Expr\ArrowFunction ? end($this->scopes)['variables'] : [];
                    foreach ($node->getParams() as $parameter) {
                        if ($parameter->var instanceof Expr\Variable && is_string($parameter->var->name)) {
                            $variables[$parameter->var->name] = $this->types->narrowedBy($parameter->getAttribute('documented'), $this->types->written($parameter->type, $this->class));
                            $parameter->var->setAttribute('type', $variables[$parameter->var->name]);
                        }
                    }
                    if ($node instanceof Expr\Closure) {
                        foreach ($node->uses as $used) {
                            if (is_string($used->var->name)) {
                                $variables[$used->var->name] = end($this->scopes)['variables'][$used->var->name] ?? null;
                            }
                        }
                    }
                    $this->scopes[] = ['class' => $this->class, 'variables' => $variables];
                }

                return null;
            }

            public function leaveNode(Node $node): null
            {
                if ($node instanceof Stmt && ($documented = $node->getAttribute('documentedVariables')) !== null) {
                    foreach ($documented as $name => $type) {
                        $this->scopes[array_key_last($this->scopes)]['variables'][$name] = $type;
                    }
                }
                if ($node instanceof Expr) {
                    $scope = array_key_last($this->scopes);
                    $type = $this->types->typeOf($node, $this->class, $this->scopes[$scope]['variables']);
                    if ($node instanceof Expr\CallLike && ! $node instanceof Expr\New_ && $node->isFirstClassCallable()) {
                        $type = ['name' => 'Closure'];
                    }
                    if ($type !== null) {
                        $node->setAttribute('type', $type);
                    }
                    if ($node instanceof Expr\Match_ && $this->types->coversEveryCase($node)) {
                        $node->setAttribute('exhaustive', true);
                    }
                    if ($node instanceof Expr\Assign && $node->var instanceof Expr\Variable && is_string($node->var->name)) {
                        $this->scopes[$scope]['variables'][$node->var->name] = $type;
                    }
                    if (($into = $node->getAttribute('iteratedInto')) !== null) {
                        $this->scopes[$scope]['variables'][$into] = $type['element'] ?? null;
                    }
                }
                if ($node instanceof Node\FunctionLike) {
                    array_pop($this->scopes);
                }
                if ($node instanceof Stmt\ClassLike) {
                    $this->class = array_pop($this->enclosingClasses);
                }

                return null;
            }
        });
        $traverser->traverse($statements);
        unset($this->read[$path]);

        return array_keys($this->read);
    }

    /**
     * The type an expression gives, from what its parts were already given: none where PHP's own rules do not say.
     *
     * @param  array<string, ?array>  $variables
     */
    public function typeOf(Expr $expression, ?string $class, array $variables): ?array
    {
        $type = $this->typeWritten($expression, $class, $variables);
        $nullsafe = $expression instanceof Expr\NullsafeMethodCall || $expression instanceof Expr\NullsafePropertyFetch;

        return $nullsafe && $type !== null ? ['nullable' => true] + $type : $type;
    }

    /**
     * @param  array<string, ?array>  $variables
     */
    private function typeWritten(Expr $expression, ?string $class, array $variables): ?array
    {
        return match (true) {
            $expression instanceof Expr\Variable => $expression->name === 'this'
                ? ($class !== null ? ['name' => $class] : null)
                : (is_string($expression->name) ? ($variables[$expression->name] ?? null) : null),
            $expression instanceof Expr\New_ => $this->constructed($expression, $class),
            $expression instanceof Expr\MethodCall, $expression instanceof Expr\NullsafeMethodCall => $this->called($expression, $this->classOf($expression->var->getAttribute('type')), $class),
            $expression instanceof Expr\StaticCall => $expression->class instanceof Node\Name
                ? $this->called($expression, $this->named($expression->class, $class), $class)
                : $this->called($expression, $this->classOf($expression->class->getAttribute('type')), $class),
            $expression instanceof Expr\FuncCall => $this->function($expression),
            $expression instanceof Expr\PropertyFetch, $expression instanceof Expr\NullsafePropertyFetch => $expression->name instanceof Node\Identifier
                ? $this->property($expression, $this->classOf($expression->var->getAttribute('type')), $expression->name->toString())
                : null,
            $expression instanceof Expr\StaticPropertyFetch => $expression->class instanceof Node\Name && $expression->name instanceof Node\VarLikeIdentifier
                ? $this->property($expression, $this->named($expression->class, $class), $expression->name->toString())
                : null,
            $expression instanceof Expr\ClassConstFetch => $expression->name instanceof Node\Identifier && $expression->name->toLowerString() === 'class'
                ? ['keyword' => 'string']
                : ($expression->class instanceof Node\Name && $expression->name instanceof Node\Identifier
                    ? $this->constant($expression, $this->named($expression->class, $class), $expression->name->toString())
                    : null),
            $expression instanceof Expr\Assign => $expression->expr->getAttribute('type'),
            $expression instanceof Node\Scalar\String_, $expression instanceof Node\Scalar\InterpolatedString => ['keyword' => 'string'],
            $expression instanceof Node\Scalar\Int_ => ['keyword' => 'int'],
            $expression instanceof Node\Scalar\Float_ => ['keyword' => 'float'],
            $expression instanceof Expr\Array_ => ['keyword' => 'array'],
            $expression instanceof Expr\ConstFetch => match ($expression->name->toLowerString()) {
                'true', 'false' => ['keyword' => 'bool'],
                'null' => ['keyword' => 'null'],
                default => null,
            },
            $expression instanceof Expr\Clone_ => $expression->expr->getAttribute('type'),
            default => null,
        };
    }

    /**
     * A type as written on a declaration, its self and static read as the class that writes them.
     */
    public function written(?Node $type, ?string $class): ?array
    {
        if ($type instanceof Node\NullableType) {
            $inner = $this->written($type->type, $class);

            return $inner === null ? null : $inner + ['nullable' => true];
        }
        if ($type instanceof Node\UnionType) {
            $members = array_values(array_filter($type->types, static fn (Node $each): bool => ! ($each instanceof Node\Identifier && $each->toLowerString() === 'null')));
            if (count($members) === 1 && count($type->types) === 2) {
                $inner = $this->written($members[0], $class);

                return $inner === null ? null : $inner + ['nullable' => true];
            }

            return null;
        }
        if ($type instanceof Node\Identifier) {
            $keyword = $type->toLowerString();

            return in_array($keyword, ['self', 'static'], true) && $class !== null ? ['name' => $class] : ['keyword' => $keyword];
        }
        if ($type instanceof Node\Name) {
            $name = $type->toString();

            return in_array(strtolower($name), ['self', 'static'], true) ? ($class !== null ? ['name' => $class] : ['keyword' => strtolower($name)]) : ['name' => $name];
        }

        return null;
    }

    /**
     * A parameter's written type, or the class its doc comment documents when that class is the written one or a subtype of it.
     */
    public function narrowedBy(?array $documented, ?array $written): ?array
    {
        if (isset($documented['element'])) {
            return in_array($written['keyword'] ?? null, ['array', 'iterable'], true) ? $written + ['element' => $documented['element']] : $written;
        }
        if ($documented === null || $this->declaration($documented['name']) === null) {
            return $written;
        }
        if ($written !== null && ($written['name'] ?? null) === null) {
            return $written;
        }
        if ($written !== null && ! $this->isSubtype($documented['name'], $written['name'])) {
            return $written;
        }

        $nullable = $written !== null ? ($written['nullable'] ?? false) : ($documented['nullable'] ?? false);

        return ['name' => $documented['name']] + ($nullable ? ['nullable' => true] : []);
    }

    private function isSubtype(string $class, string $super): bool
    {
        $seen = [];
        $waiting = [$class];
        while ($waiting !== []) {
            $at = array_shift($waiting);
            if (strcasecmp(ltrim($at, '\\'), ltrim($super, '\\')) === 0) {
                return true;
            }
            if (isset($seen[strtolower($at)]) || ($declared = $this->declaration($at)) === null) {
                continue;
            }
            $seen[strtolower($at)] = true;
            if ($declared['parent'] !== null) {
                $waiting[] = $declared['parent'];
            }
            array_push($waiting, ...$declared['interfaces']);
        }

        return false;
    }

    private function constructed(Expr\New_ $new, ?string $class): ?array
    {
        if (! $new->class instanceof Node\Name) {
            return null;
        }
        $made = $this->named($new->class, $class);
        if ($made === null) {
            return null;
        }
        $declaring = $this->declaring($made, '__construct') ?? $made;
        $new->setAttribute('target', ['symbol' => "{$declaring}::__construct", 'type' => $declaring, 'name' => '__construct']);

        return ['name' => $made];
    }

    private function called(Expr\MethodCall|Expr\NullsafeMethodCall|Expr\StaticCall $call, ?string $receiver, ?string $class): ?array
    {
        if ($receiver === null || ! $call->name instanceof Node\Identifier) {
            return null;
        }
        $name = $call->name->toString();
        $declaring = $this->declaring($receiver, $name);
        if ($declaring === null) {
            return null;
        }
        $method = $this->methods($declaring)[strtolower($name)];
        $call->setAttribute('target', ['symbol' => "{$declaring}::{$method['name']}", 'type' => $declaring, 'name' => $method['name']]);
        $returns = $method['returns'];
        if ($returns !== null && isset($returns['keyword']) && in_array($returns['keyword'], ['self', 'static'], true)) {
            return ['name' => $receiver ?? $declaring] + array_intersect_key($returns, ['nullable' => true]);
        }

        return $returns;
    }

    private function function(Expr\FuncCall $call): ?array
    {
        if (! $call->name instanceof Node\Name) {
            return null;
        }
        $written = $call->name->getAttribute('namespacedName')?->toString();
        $declared = ($written !== null ? $this->functions[strtolower($written)] ?? null : null) ?? $this->functions[$call->name->toLowerString()] ?? null;
        if ($declared !== null) {
            $this->reads($declared['file']);
            $call->setAttribute('target', ['symbol' => $declared['name'], 'name' => $declared['name']]);

            return $declared['returns'];
        }
        $global = $call->name->getLast();
        if (function_exists($global) || $call->name->isUnqualified()) {
            $call->setAttribute('target', ['symbol' => $global, 'name' => $global]);
        }

        return null;
    }

    /**
     * Every declaration a method of the class overrides or implements, up its parents and interfaces: `Base::run`, and
     * whether the project declares it. A trait's concrete method binds when an ancestor uses the trait, never the
     * class's own trait, which its method replaces freely. An ancestor that cannot be read adds none.
     *
     * @return list<array{symbol: string, origin: string}>
     */
    public function obligations(string $class, string $method): array
    {
        $own = $this->declaration($class);
        if ($own === null) {
            return [];
        }
        $key = strtolower($method);
        $found = [];
        $seen = [strtolower($own['name']) => true];
        $pending = array_map(static fn (string $name): array => [$name, true], array_values(array_filter([$own['parent'] ?? null, ...($own['interfaces'] ?? [])])));
        foreach ($own['traits'] ?? [] as $trait) {
            $pending[] = [$trait, false];
        }
        while ($pending !== []) {
            [$at, $ofAnAncestor] = array_shift($pending);
            if (isset($seen[strtolower(ltrim($at, '\\'))])) {
                continue;
            }
            $seen[strtolower(ltrim($at, '\\'))] = true;
            $declared = $this->declaration($at);
            if ($declared === null) {
                continue;
            }
            $binds = isset($declared['methods'][$key]) && ($ofAnAncestor || ! ($declared['trait'] ?? false) || $declared['methods'][$key]['abstract']);
            if ($binds) {
                $found[] = ['symbol' => "{$declared['name']}::{$declared['methods'][$key]['name']}", 'origin' => $declared['file'] !== null ? 'project' : 'external'];
            }
            foreach (array_filter([$declared['parent'] ?? null, ...($declared['interfaces'] ?? [])]) as $above) {
                $pending[] = [$above, true];
            }
            foreach ($declared['traits'] ?? [] as $trait) {
                $pending[] = [$trait, $ofAnAncestor];
            }
        }

        return $found;
    }

    /** A class constant or enum case read: the member names it, `Class::NAME`, and an enum case is of its enum's type. */
    private function constant(Expr\ClassConstFetch $fetch, ?string $class, string $name): ?array
    {
        $seen = [];
        $pending = $class !== null ? [$class] : [];
        while ($pending !== []) {
            $at = array_shift($pending);
            if (isset($seen[strtolower($at)])) {
                continue;
            }
            $seen[strtolower($at)] = true;
            $declared = $this->declaration($at);
            if ($declared === null) {
                continue;
            }
            if (isset($declared['constants'][$name])) {
                $fetch->setAttribute('member', "{$declared['name']}::{$name}");
                if (is_array($declared['constants'][$name])) {
                    $fetch->setAttribute('constantValue', $declared['constants'][$name]);
                }

                return null;
            }
            array_push($pending, ...array_filter([$declared['parent'] ?? null, ...($declared['interfaces'] ?? [])]));
        }

        return null;
    }

    private function property(Expr $fetch, ?string $class, string $name): ?array
    {
        for ($at = $class; $at !== null; $at = $this->declaration($at)['parent'] ?? null) {
            $declared = $this->declaration($at);
            if ($declared !== null && array_key_exists($name, $declared['properties'])) {
                $fetch->setAttribute('member', "{$at}::\${$name}");

                return $declared['properties'][$name];
            }
        }

        return null;
    }

    /**
     * The class that declares the method a class answers to: the class itself, a trait it uses, a parent, or an
     * interface it implements, nearest first.
     */
    private function declaring(string $class, string $method): ?string
    {
        $lowered = strtolower($method);
        $seen = [];
        $waiting = [$class];
        while ($waiting !== []) {
            $at = array_shift($waiting);
            if (isset($seen[strtolower($at)])) {
                continue;
            }
            $seen[strtolower($at)] = true;
            $declared = $this->declaration($at);
            if ($declared === null) {
                continue;
            }
            if (isset($declared['methods'][$lowered])) {
                return $declared['name'];
            }
            array_push($waiting, ...$declared['traits']);
            if ($declared['parent'] !== null) {
                $waiting[] = $declared['parent'];
            }
            array_push($waiting, ...$declared['interfaces']);
        }

        return null;
    }

    /**
     * @return array<string, array{name: string, returns: ?array, static: bool}>
     */
    private function methods(string $class): array
    {
        return $this->declaration($class)['methods'] ?? [];
    }

    private function named(Node\Name $name, ?string $class): ?string
    {
        return match ($name->toLowerString()) {
            'self', 'static' => $class,
            'parent' => $class !== null ? $this->declaration($class)['parent'] ?? null : null,
            default => $name->toString(),
        };
    }

    private function classOf(?array $type): ?string
    {
        return $type['name'] ?? null;
    }

    private function builtIn(string $key): bool
    {
        foreach (['class_exists', 'interface_exists', 'trait_exists', 'enum_exists'] as $exists) {
            if ($exists($key, false)) {
                return (new \ReflectionClass($key))->isInternal();
            }
        }

        return false;
    }

    /**
     * A class as a judged file declares it, or as the autoloader loads it.
     */
    private function declaration(string $class): ?array
    {
        $key = strtolower(ltrim($class, '\\'));
        if (! isset($this->classes[$key]) && ($this->loads || $this->builtIn($key))) {
            $this->classes[$key] = $this->reflected(ltrim($class, '\\'));
        }
        $declared = $this->classes[$key] ?? null;
        if ($declared !== null) {
            $this->reads($declared['file']);
        }

        return $declared;
    }

    private function reads(?string $file): void
    {
        if ($file !== null) {
            $this->read[$file] = true;
        }
    }

    private function loadedAlready(string $class): bool
    {
        return class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false) || enum_exists($class, false);
    }

    /**
     * Whether loading a class would include no file twice: a file the autoloader would include for it may already be
     * included under another spelling of its path, as on a filesystem that ignores case, and PHP cannot recover from
     * declaring its class again.
     */
    private function loadableOnce(string $class): bool
    {
        $included = array_map('strtolower', get_included_files());
        foreach (spl_autoload_functions() as $loader) {
            if (is_array($loader) && is_object($loader[0]) && method_exists($loader[0], 'findFile')) {
                $file = $loader[0]->findFile($class);
                $real = is_string($file) ? realpath($file) : false;
                if ($real !== false && in_array(strtolower($real), $included, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function reflected(string $class): ?array
    {
        try {
            if (! $this->loadedAlready($class) && ! $this->loadableOnce($class)) {
                return null;
            }
            if (! class_exists($class) && ! $this->loadedAlready($class)) {
                return null;
            }
            $reflection = new ReflectionClass($class);
        } catch (Throwable) {
            return null;
        }
        $name = $reflection->getName();
        $methods = [];
        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() === $name) {
                $methods[strtolower($method->getName())] = ['name' => $method->getName(), 'returns' => $this->reflectedType($method->getReturnType(), null) ?? self::documentedReturn($method->getDocComment()), 'static' => $method->isStatic(), 'abstract' => $method->isAbstract()];
            }
        }
        $properties = [];
        foreach ($reflection->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() === $name) {
                $properties[$property->getName()] = $this->reflectedType($property->getType(), $name);
            }
        }
        $constants = [];
        foreach ($reflection->getReflectionConstants() as $constant) {
            if ($constant->getDeclaringClass()->getName() === $name) {
                $constants[$constant->getName()] = true;
            }
        }
        $parent = $reflection->getParentClass();

        return [
            'name' => $name,
            'file' => null,
            'parent' => $parent !== false ? $parent->getName() : null,
            'interfaces' => $reflection->getInterfaceNames(),
            'traits' => $reflection->getTraitNames(),
            'trait' => $reflection->isTrait(),
            'methods' => $methods,
            'properties' => $properties,
            'constants' => $constants,
        ];
    }

    /**
     * The type a library method's doc comment returns when it writes no native one, read only where no import is needed
     * to name it: a fully qualified class, or static, self and $this, which name the receiver. A generic argument is
     * dropped, and `|null` makes it nullable.
     */
    private static function documentedReturn(string|false $doc): ?array
    {
        if ($doc === false || preg_match('/@return\s+(\S+)/', $doc, $found) !== 1) {
            return null;
        }
        $written = $found[1];
        while (preg_match('/<[^<>]*>/', $written) === 1) {
            $written = (string) preg_replace('/<[^<>]*>/', '', $written);
        }
        $parts = explode('|', $written);
        $nullable = in_array('null', $parts, true) ? ['nullable' => true] : [];
        $named = array_values(array_diff($parts, ['null']));
        if (count($named) !== 1) {
            return null;
        }

        return match (true) {
            in_array(strtolower($named[0]), ['static', 'self', '$this'], true) => ['keyword' => 'static'] + $nullable,
            preg_match('/^\\\\[A-Za-z_][\\\\\w]*$/', $named[0]) === 1 => ['name' => ltrim($named[0], '\\')] + $nullable,
            default => null,
        };
    }

    private function reflectedType(?ReflectionType $type, ?string $class): ?array
    {
        if (! $type instanceof ReflectionNamedType) {
            return null;
        }
        $nullable = $type->allowsNull() && $type->getName() !== 'null' && $type->getName() !== 'mixed' ? ['nullable' => true] : [];
        if ($type->isBuiltin()) {
            return in_array($type->getName(), ['self', 'static'], true) && $class !== null ? ['name' => $class] + $nullable : ['keyword' => $type->getName()] + $nullable;
        }

        if (in_array(strtolower($type->getName()), ['self', 'static'], true)) {
            return ($class !== null ? ['name' => $class] : ['keyword' => strtolower($type->getName())]) + $nullable;
        }

        return ['name' => $type->getName()] + $nullable;
    }
}
