<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\Node\Import;
use JesseGall\CodeCommandments\Py\Node\Node;
use JesseGall\CodeCommandments\Py\Node\Param;
use JesseGall\PhpTypes\Option;

/**
 * The call graph of a Python codebase: which calls reach which `def`. A call is resolved through the
 * module's imports — absolute and relative, aliased or not — through a `def` nested in an enclosing
 * function, through `self` inside a method, through a parameter or variable annotated with a class, and
 * through an attribute of `self` whose class the class body or `__init__` declares; a method a class does
 * not declare is looked up in its bases. The Python twin of
 * {@see \JesseGall\CodeCommandments\Ast\CodebaseIndex}: a call that cannot be resolved is never guessed,
 * and an import that names more than one module resolves to none.
 */
final class CallIndex
{
    /**
     * @var array<int, list<ExprMatch>>|null  each `def`'s callers, by the def's object id
     */
    private ?array $callers = null;

    /**
     * @var array<int, FunctionDef>  the `def` each resolved call reaches, by the call's object id
     */
    private array $targets = [];

    /**
     * @var array<int, ModuleFile>|null  the module each class is declared in, by the class's object id
     */
    private ?array $homes = null;

    /**
     * @var array<int, true>  the calls made through a class named outright — `Cart.add(cart, sku)` — by the
     *                        call's object id, which hand an instance method its `self` themselves
     */
    private array $throughClass = [];

    /**
     * @var array<int, ModuleFile>|null  the module each `def` is declared in, by the def's object id
     */
    private ?array $declarations = null;

    /**
     * @var array<int, true>|null  the object ids of every `def` bound to an instance or class when called
     */
    private ?array $bound = null;

    /**
     * @var array<string, array{modules: array<string, ModuleFile>, members: array<string, Node>}>
     */
    private array $bindings = [];

    public function __construct(private readonly Codebase $codebase) {}

    /**
     * Every call that reaches $function.
     *
     * @return list<ExprMatch>
     */
    public function callersOf(FunctionDef $function): array
    {
        $this->callers ??= $this->graph();

        return $this->callers[spl_object_id($function)] ?? [];
    }

    /**
     * Where $function is declared — `path:line` — the name its calls share across processes.
     */
    public function declarationOf(FunctionDef $function): string
    {
        $module = $this->moduleOf($function);

        return "{$module->file}:{$module->lineAt($function->start)}";
    }

    /**
     * The module $function is declared in.
     */
    public function moduleOf(FunctionDef $function): ModuleFile
    {
        $this->declarations ??= $this->declarations();

        return $this->declarations[spl_object_id($function)];
    }

    /**
     * @return array<int, ModuleFile>
     */
    private function declarations(): array
    {
        $declarations = [];

        foreach ($this->codebase->modules() as $module) {
            foreach ($module->nodes() as $node) {
                if ($node instanceof FunctionDef) {
                    $declarations[spl_object_id($node)] = $module;
                }
            }
        }

        return $declarations;
    }

    /**
     * The `def` $call reaches — none when it cannot be resolved.
     *
     * @return Option<FunctionDef>
     */
    public function targetOf(Expr $call): Option
    {
        $this->callers ??= $this->graph();

        return Option::fromNullable($this->targets[spl_object_id($call)] ?? null);
    }

    /**
     * Does $call hand a string literal to a parameter its target uses as a key into another — the
     * `"title"` in `text_of(row, "title")` — and so read a dict by a string key one call deeper?
     */
    public function passesLiteralKey(Expr $call): bool
    {
        return $this->targetOf($call)->isSomeAnd(fn (FunctionDef $target): bool => $target->keyParameters() !== [] && $this->argumentsAt($call)->isSomeAnd(
            static fn (array $bound): bool => array_any(
                $target->keyParameters(),
                static fn (string $key): bool => ($bound[$key] ?? null)?->literalType()?->isText() === true,
            ),
        ));
    }

    /**
     * What each parameter of $call's target receives there — its name to the argument handed to it — with an
     * instance call's `self` (or a classmethod's `cls`) already bound, and the first extra positional in a
     * `*rest` parameter. None when the call does not resolve, or unpacks a `*` or `**` argument whose parts
     * no reading can place.
     *
     * @return Option<array<string, Expr>>
     */
    public function argumentsAt(Expr $call): Option
    {
        return $this->targetOf($call)->andThen(function (FunctionDef $target) use ($call): Option {
            if (array_any($call->get('arguments'), static fn (Expr $argument): bool => $argument->is(ExprKind::Starred))) {
                return Option::none();
            }

            $params = array_slice($target->params, $this->bindsFirstParameter($call, $target) ? 1 : 0);
            $positional = array_values(array_filter($params, static fn (Param $param): bool => $param->kind === '' && ! $param->keywordOnly));
            $rest = array_values(array_filter($params, static fn (Param $param): bool => $param->kind === '*'))[0] ?? null;
            $bound = [];
            $position = 0;

            foreach ($call->get('arguments') as $argument) {
                if ($argument->is(ExprKind::Keyword)) {
                    $bound[(string) $argument->get('name')] ??= $argument->get('value');

                    continue;
                }

                $name = ($positional[$position++] ?? $rest)?->name;

                if ($name !== null) {
                    $bound[$name] ??= $argument;
                }
            }

            return Option::some($bound);
        });
    }

    /**
     * Does $call arrive with its target's first parameter already bound — an instance method called on an
     * instance, or a classmethod called on anything?
     */
    private function bindsFirstParameter(Expr $call, FunctionDef $target): bool
    {
        if (! isset($this->bound()[spl_object_id($target)]) || ! $call->get('callee')->is(ExprKind::Attribute)) {
            return false;
        }

        return $target->isClassMethod() || ! isset($this->throughClass[spl_object_id($call)]);
    }

    /**
     * Note $call as made through a class $module names outright, when it is.
     */
    private function noteThroughClass(Expr $call, ModuleFile $module): void
    {
        $owner = $call->get('callee');
        $owner = $owner->is(ExprKind::Attribute) ? $owner->get('object') : null;

        if ($owner !== null && $owner->is(ExprKind::Name) && $this->named((string) $owner->get('name'), $module)->isSomeAnd(static fn (Node $found): bool => $found instanceof ClassDef)) {
            $this->throughClass[spl_object_id($call)] = true;
        }
    }

    /**
     * Does $method — a `def` in a class body of $module — override one a base class declares? It then
     * repeats that method's signature by contract rather than choosing its own.
     */
    public function isOverride(FunctionDef $method, ModuleFile $module): bool
    {
        $class = $module->ancestorsOf($method)[1] ?? null;

        return $class instanceof ClassDef && array_any(
            $class->bases,
            fn (Expr $base): bool => $this->classNamed($base, $module)->andThen(fn (ClassDef $parent) => $this->methodOf($parent, $method->name))->isSome(),
        );
    }

    /**
     * Does $method's class name a base this codebase does not declare — a contract from outside, which
     * any of its methods may be keeping?
     */
    public function extendsOutside(FunctionDef $method, ModuleFile $module): bool
    {
        $class = $module->ancestorsOf($method)[1] ?? null;

        return $class instanceof ClassDef && array_any(
            $class->bases,
            fn (Expr $base): bool => ! $base->is(ExprKind::Keyword) && $base->dottedName() !== 'object' && $this->classNamed($base, $module)->isNone(),
        );
    }

    /**
     * Does a class of this codebase that names $method's class as a base declare a method of the same
     * name — overriding it?
     */
    public function isOverridden(FunctionDef $method, ModuleFile $module): bool
    {
        $class = $module->ancestorsOf($method)[1] ?? null;

        return $class instanceof ClassDef && array_any($this->codebase->modules(), fn (ModuleFile $other): bool => array_any(
            $other->nodes(),
            fn (Node $node): bool => $node instanceof ClassDef
                && $this->methodOf($node, $method->name)->isSome()
                && array_any($node->bases, fn (Expr $base): bool => $this->classNamed($base, $other)->isSomeAnd(static fn (ClassDef $parent): bool => $parent === $class)),
        ));
    }

    /**
     * @return array<int, list<ExprMatch>>
     */
    private function graph(): array
    {
        $callers = [];

        foreach ($this->codebase->modules() as $module) {
            foreach ($module->nodes() as $node) {
                foreach ($node->expressions() as $expression) {
                    foreach ($expression->flatten() as $call) {
                        if (! $call->isCall()) {
                            continue;
                        }

                        $target = $this->resolve($call->get('callee'), $node, $module);

                        if ($target->isSome()) {
                            $callers[spl_object_id($target->unwrap())][] = new ExprMatch($call, $module);
                            $this->targets[spl_object_id($call)] = $target->unwrap();
                            $this->noteThroughClass($call, $module);
                        }
                    }
                }
            }
        }

        return $callers;
    }

    /**
     * The `def` $callee names, read where $node stands in $module.
     *
     * @return Option<FunctionDef>
     */
    private function resolve(Expr $callee, Node $node, ModuleFile $module): Option
    {
        if ($callee->is(ExprKind::Name)) {
            $name = (string) $callee->get('name');

            return $this->nestedIn($name, $node, $module)->orElse(fn () => $this->named($name, $module)
                ->filter(static fn (Node $found): bool => $found instanceof FunctionDef));
        }

        $dotted = $callee->dottedName();

        if ($dotted === '') {
            return Option::none();
        }

        $owner = substr($dotted, 0, (int) strrpos($dotted, '.'));
        $member = substr($dotted, strrpos($dotted, '.') + 1);
        $bound = $this->bindingsOf($module)['modules'][$owner] ?? null;

        if ($bound !== null) {
            return $bound->declared($member)->filter(static fn (Node $found): bool => $found instanceof FunctionDef);
        }

        return $this->classOf($owner, $node, $module)->andThen(fn (ClassDef $class) => $this->methodOf($class, $member));
    }

    /**
     * The `def` named $name that a function enclosing $node declares in its own body — the nearest one, as
     * Python looks a name up — none when no enclosing function declares one.
     *
     * @return Option<FunctionDef>
     */
    private function nestedIn(string $name, Node $node, ModuleFile $module): Option
    {
        foreach ([$node, ...$module->ancestorsOf($node)] as $scope) {
            if (! $scope instanceof FunctionDef) {
                continue;
            }

            $declared = array_filter($scope->body->descendants(), static fn (Node $inner): bool => $inner instanceof FunctionDef
                && $inner->name === $name
                && array_values(array_filter($module->ancestorsOf($inner), static fn (Node $around): bool => $around instanceof FunctionDef))[0] === $scope);

            if ($declared !== []) {
                return Option::some(array_values($declared)[0]);
            }
        }

        return Option::none();
    }

    /**
     * The class $owner stands for where $node sits: `self` in a method, a parameter or variable
     * annotated with a class, or a class named outright.
     *
     * @return Option<ClassDef>
     */
    private function classOf(string $owner, Node $node, ModuleFile $module): Option
    {
        $ancestors = [$node, ...$module->ancestorsOf($node)];
        $function = array_values(array_filter($ancestors, static fn (Node $ancestor): bool => $ancestor instanceof FunctionDef))[0] ?? null;

        if ($owner === 'self' && $function !== null) {
            $class = $module->ancestorsOf($function)[1] ?? null;

            return Option::fromNullable($class instanceof ClassDef ? $class : null);
        }

        $path = explode('.', $owner);

        if (count($path) === 2 && $path[0] === 'self') {
            return $this->classOf('self', $node, $module)
                ->andThen(static fn (ClassDef $class) => $class->attributeAnnotation($path[1]))
                ->andThen(fn (Expr $annotation) => $this->classNamed($annotation, $module));
        }

        $annotation = $function === null ? Option::none() : $function->annotationOf($owner);

        if ($annotation->isSome()) {
            return $this->classNamed($annotation->unwrap(), $module);
        }

        return str_contains($owner, '.') ? Option::none() : $this->classNamed(new Expr(ExprKind::Name, ['name' => $owner]), $module);
    }

    /**
     * The class an annotation or a name spells, read in $module — a string annotation spells it too.
     *
     * @return Option<ClassDef>
     */
    private function classNamed(Expr $spelled, ModuleFile $module): Option
    {
        $dotted = $spelled->literalType()?->isText() === true ? (string) $spelled->get('value') : $spelled->dottedName();
        $owner = substr($dotted, 0, max(0, (int) strrpos($dotted, '.')));
        $bound = $this->bindingsOf($module)['modules'][$owner] ?? null;
        $found = $bound !== null
            ? $bound->declared(substr($dotted, strrpos($dotted, '.') + 1))
            : $this->named($dotted, $module);

        return $found->filter(static fn (Node $node): bool => $node instanceof ClassDef);
    }

    /**
     * $name as $class declares it, or as the first of its bases that does.
     *
     * @return Option<FunctionDef>
     */
    private function methodOf(ClassDef $class, string $name): Option
    {
        foreach ($class->body->body as $member) {
            if ($member instanceof FunctionDef && $member->name === $name) {
                return Option::some($member);
            }
        }

        $home = $this->homes()[spl_object_id($class)] ?? null;

        foreach ($home === null ? [] : $class->bases as $base) {
            $inherited = $this->classNamed($base, $home)->andThen(fn (ClassDef $parent) => $this->methodOf($parent, $name));

            if ($inherited->isSome()) {
                return $inherited;
            }
        }

        return Option::none();
    }

    /**
     * What $name is bound to at the top of $module — a function or class it declares, or one it imports.
     *
     * @return Option<Node>
     */
    private function named(string $name, ModuleFile $module): Option
    {
        $imported = $this->bindingsOf($module)['members'][$name] ?? null;

        return $imported !== null ? Option::some($imported) : $module->declared($name);
    }

    /**
     * The names $module's imports bind: those bound to a module, and those bound to a function or class
     * another module declares.
     *
     * @return array{modules: array<string, ModuleFile>, members: array<string, Node>}
     */
    private function bindingsOf(ModuleFile $module): array
    {
        if (isset($this->bindings[$module->file])) {
            return $this->bindings[$module->file];
        }

        $modules = [];
        $members = [];

        foreach ($module->nodes() as $import) {
            if (! $import instanceof Import) {
                continue;
            }

            foreach ($import->names as $name => $alias) {
                if ($import->module === null) {
                    $this->moduleNamed($name, $module, 0)->inspect(function (ModuleFile $found) use (&$modules, $name, $alias): void {
                        $modules[$alias === explode('.', $name)[0] ? $name : $alias] = $found;
                    });

                    continue;
                }

                $source = $this->moduleNamed($import->module, $module, $import->level);
                $declared = $source->andThen(static fn (ModuleFile $found) => $found->declared($name));

                if ($declared->isSome()) {
                    $members[$alias] = $declared->unwrap();

                    continue;
                }

                $this->moduleNamed(ltrim("{$import->module}.{$name}", '.'), $module, $import->level)->inspect(function (ModuleFile $found) use (&$modules, $alias): void {
                    $modules[$alias] = $found;
                });
            }
        }

        return $this->bindings[$module->file] = ['modules' => $modules, 'members' => $members];
    }

    /**
     * Every module of this codebase an import in $module reaches, with the import that reaches it — the module
     * imported, the one a `from` imports a name out of, or the submodule it names. An import naming nothing
     * here reaches none.
     *
     * @return list<array{Import, ModuleFile}>
     */
    public function importsOf(ModuleFile $module): array
    {
        $reached = [];

        foreach ($module->nodes() as $import) {
            if (! $import instanceof Import) {
                continue;
            }

            foreach (array_keys($import->names) as $name) {
                $this->importedModule($import, (string) $name, $module)->inspect(static function (ModuleFile $found) use (&$reached, $import): void {
                    $reached[] = [$import, $found];
                });
            }
        }

        return $reached;
    }

    /**
     * The module $import reaches for $name, read from $module.
     *
     * @return Option<ModuleFile>
     */
    private function importedModule(Import $import, string $name, ModuleFile $module): Option
    {
        if ($import->module === null) {
            return $this->moduleNamed($name, $module, 0);
        }

        $source = $this->moduleNamed($import->module, $module, $import->level);

        if ($source->isSomeAnd(static fn (ModuleFile $found): bool => $found->binds($name))) {
            return $source;
        }

        $submodule = $this->moduleNamed(ltrim("{$import->module}.{$name}", '.'), $module, $import->level);

        return $submodule->isSome() ? $submodule : $source;
    }

    /**
     * The one module $dotted names, read from $from — `$level` dots up from its package for a relative
     * import. None when no module or more than one has that name.
     *
     * @return Option<ModuleFile>
     */
    private function moduleNamed(string $dotted, ModuleFile $from, int $level): Option
    {
        if ($level > 0) {
            $package = dirname($from->file, $level);
            $path = $package . ($dotted === '' ? '' : '/' . str_replace('.', '/', $dotted));
            $found = array_filter($this->codebase->modules(), static fn (ModuleFile $module): bool => $module->file === "{$path}.py" || $module->file === "{$path}/__init__.py");
        } else {
            return $this->codebase->moduleCalled($dotted);
        }

        return count($found) === 1 ? Option::some(array_values($found)[0]) : Option::none();
    }

    /**
     * @return array<int, ModuleFile>
     */
    private function homes(): array
    {
        if ($this->homes !== null) {
            return $this->homes;
        }

        $this->homes = [];

        foreach ($this->codebase->modules() as $module) {
            foreach ($module->nodes() as $node) {
                if ($node instanceof ClassDef) {
                    $this->homes[spl_object_id($node)] = $module;
                }
            }
        }

        return $this->homes;
    }

    /**
     * @return array<int, true>
     */
    private function bound(): array
    {
        if ($this->bound !== null) {
            return $this->bound;
        }

        $this->bound = [];

        foreach ($this->codebase->modules() as $module) {
            foreach ($module->nodes() as $node) {
                if ($node instanceof FunctionDef && $module->isMethod($node) && ! $node->isStatic()) {
                    $this->bound[spl_object_id($node)] = true;
                }
            }
        }

        return $this->bound;
    }
}
