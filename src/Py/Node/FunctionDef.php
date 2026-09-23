<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use JesseGall\CodeCommandments\Py\Docstring;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\Expr\LiteralType;
use JesseGall\PhpTypes\Option;

/**
 * A `def` — a module function or a method alike — with its decorators, parameters, return annotation
 * and body.
 */
final class FunctionDef extends Node
{
    /**
     * The builtin scalar types a loose value is annotated with.
     */
    private const array SCALARS = ['str', 'int', 'float', 'bool'];

    /**
     * @param  list<Param>  $params
     * @param  list<Expr>  $decorators
     */
    public function __construct(
        public readonly string $name,
        public readonly array $params,
        public readonly Block $body,
        public readonly ?Expr $returns = null,
        public readonly array $decorators = [],
        public readonly bool $async = false,
    ) {}

    /**
     * `async` or nothing — an async one cannot share a body with its sync twin.
     */
    public function variant(): string
    {
        return $this->async ? 'async' : '';
    }

    public function children(): array
    {
        return [...$this->params, $this->body];
    }

    public function isScope(): bool
    {
        return true;
    }

    public function expressions(): array
    {
        return $this->decorators;
    }

    public function declaredNames(): array
    {
        return [$this->name];
    }

    public function functionBody(): Option
    {
        return Option::some($this->body);
    }

    /**
     * The annotation $name carries in this function — as one of its parameters, or as a local it
     * declares with one.
     *
     * @return Option<Expr>
     */
    public function annotationOf(string $name): Option
    {
        foreach ($this->params as $param) {
            if ($param->name === $name && $param->annotation !== null) {
                return Option::some($param->annotation);
            }
        }

        foreach ($this->body->descendants() as $statement) {
            if ($statement instanceof AnnAssign && $statement->target->dottedName() === $name) {
                return Option::some($statement->annotation);
            }
        }

        return Option::none();
    }

    /**
     * Is this a named constructor — a `@classmethod` that returns `cls(...)`? It is where loose data
     * becomes the class, the one place reading that data by key belongs.
     */
    public function isNamedConstructor(): bool
    {
        return $this->isClassMethod() && array_any(
            $this->body->descendants(),
            static fn (Node $node): bool => $node->returnedValue()->isSomeAnd(
                static fn (Expr $value): bool => $value->isCall() && $value->get('callee')->dottedName() === 'cls',
            ),
        );
    }

    /**
     * The scalar-typed parameters this function takes — `str`, `int`, `float`, `bool` — as sorted
     * `type name` pairs, when there are three or more of them; empty otherwise. Named as the backend
     * names it: two functions with one signature take the same loose values.
     *
     * @return list<string>
     */
    public function valueParamSignature(): array
    {
        $fields = [];

        foreach ($this->params as $param) {
            $type = $param->annotation?->dottedName() ?? '';

            if (in_array($type, self::SCALARS, true)) {
                $fields[] = "{$type} {$param->name}";
            }
        }

        sort($fields);

        return count($fields) >= 3 ? $fields : [];
    }

    /**
     * The parameters this function uses as keys into another of its parameters — `key` in
     * `raw.get(key)` or `raw[key]`, and `keys` in `for key in keys: raw.get(key)`. A caller passing a
     * literal there is reading that dict by a string key, one call deeper.
     *
     * @return list<string>
     */
    public function keyParameters(): array
    {
        $params = array_map(static fn (Param $param): string => $param->name, $this->params);
        $loops = $this->keyLoops();
        $keys = [];

        foreach ($this->parameterReads() as $key) {
            $name = $key->dottedName();
            $keys[] = in_array($name, $params, true) ? $name : ($loops[$name] ?? '');
        }

        return array_values(array_unique(array_filter($keys)));
    }

    /**
     * Is this a `@property` getter, computed on every read?
     */
    public function isPropertyGetter(): bool
    {
        return array_any($this->decorators, static fn (Expr $decorator): bool => $decorator->dottedName() === 'property');
    }

    /**
     * Is this a setter or deleter of another property — `@kind.setter`?
     */
    public function isPropertyAccessorOf(string $name): bool
    {
        return array_any($this->decorators, static fn (Expr $decorator): bool => in_array($decorator->dottedName(), ["{$name}.setter", "{$name}.deleter"], true));
    }

    /**
     * Is this a `@contextmanager` — a function whose declared job is a change it undoes when the block ends?
     */
    public function isContextManager(): bool
    {
        return array_any($this->decorators, static fn (Expr $decorator): bool => in_array($decorator->dottedName(), ['contextmanager', 'contextlib.contextmanager', 'asynccontextmanager', 'contextlib.asynccontextmanager'], true));
    }

    /**
     * Is this a `@staticmethod` — called with no instance or class bound to its first parameter?
     */
    public function isStatic(): bool
    {
        return array_any($this->decorators, static fn (Expr $decorator): bool => $decorator->dottedName() === 'staticmethod');
    }

    /**
     * The locals this function assigns exactly once, from a plain `name = value` — name → value. A local
     * written twice has no single meaning, so it is left out.
     *
     * @return array<string, Expr>
     */
    public function soleAssignments(): array
    {
        $values = [];
        $writes = [];

        foreach ($this->body->descendants() as $node) {
            foreach ($node->writtenTargets() as $target) {
                $writes[] = $target->dottedName();
            }

            if ($node instanceof Assign && count($node->targets) === 1 && $node->targets[0]->is(ExprKind::Name)) {
                $values[(string) $node->targets[0]->get('name')] = $node->value;
            }
        }

        $counts = array_count_values($writes);

        return array_filter($values, static fn (string $name): bool => $counts[$name] === 1, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Is this function's whole body one two-way branch on one of its own parameters — an `if`/`else` on a
     * `bool`, or on whether an optional one was given? Then the parameter selects which of two functions
     * the caller wanted.
     */
    public function switchesEntirelyOnAParameter(): bool
    {
        return array_any($this->params, fn (Param $param): bool => $this->body->isTwoWayBranch($param->isSelectedBy(...)));
    }

    /**
     * Is this a dunder — `__init__`, `__sub__` — a protocol method whose name and signature the language
     * fixes?
     */
    public function isDunder(): bool
    {
        return str_starts_with($this->name, '__') && str_ends_with($this->name, '__');
    }

    /**
     * Does this take any keyword it is handed — a `**changes` rest?
     */
    public function takesKeywordRest(): bool
    {
        return array_any($this->params, static fn (Param $param): bool => $param->kind === '**');
    }

    /**
     * Is this a `@classmethod` — called with the class, not an instance, bound to its first parameter?
     */
    public function isClassMethod(): bool
    {
        return array_any($this->decorators, static fn (Expr $decorator): bool => $decorator->dottedName() === 'classmethod');
    }

    /**
     * Does this declare it hands nothing back — `-> None`, `-> NoReturn`, `-> Never`?
     */
    public function returnsNothing(): bool
    {
        return $this->returns?->literalType() === LiteralType::None
            || in_array($this->returns?->dottedName(), ['NoReturn', 'typing.NoReturn', 'Never', 'typing.Never'], true);
    }

    /**
     * Does this declare it hands back an instance of $class — `-> Self`, `-> $class`, `-> "$class"`?
     */
    public function returnsInstanceOf(string $class): bool
    {
        return in_array($this->returns?->spelledType(), ['Self', 'typing.Self', 'typing_extensions.Self', $class], true);
    }

    /**
     * Does this function answer a missed lookup with an invented `""`, `0` or `False`? It returns an
     * empty scalar, and every other value it returns comes from looking a parameter up by key — so the
     * empty one is what a miss gets instead of the absence.
     */
    public function isInventingOnMiss(): bool
    {
        $returned = [];

        foreach ($this->body->descendants() as $node) {
            $node->returnedValue()->inspect(static function (Expr $value) use (&$returned): void {
                $returned[] = $value;
            });
        }

        $empty = array_filter($returned, static fn (Expr $value): bool => $value->isEmptyScalar());
        $found = array_filter($returned, static fn (Expr $value): bool => ! $value->isEmptyScalar());

        return $empty !== [] && $found !== [] && array_all($found, fn (Expr $value) => $this->isLookedUp($value));
    }

    /**
     * Is $value what a lookup of a parameter found — the read itself, or a name assigned from one?
     */
    private function isLookedUp(Expr $value): bool
    {
        $found = [];

        foreach ($this->body->descendants() as $node) {
            $readsKey = array_any($node->expressions(), fn (Expr $expression): bool => array_any($expression->flatten(), $this->isLookup(...)));

            if ($node instanceof Assign && $readsKey && count($node->targets) === 1 && $node->targets[0]->is(ExprKind::Name)) {
                $found[] = (string) $node->targets[0]->get('name');
            }
        }

        return array_any(
            $value->flatten(),
            fn (Expr $part): bool => $this->isLookup($part) || ($part->is(ExprKind::Name) && in_array($part->get('name'), $found, true)),
        );
    }

    /**
     * Is $part a lookup into one of this function's parameters as a dict — a `.get(...)`, or a subscript
     * by a string or by a key the function is handed? Indexing a list by a counter is not one.
     */
    private function isLookup(Expr $part): bool
    {
        $params = array_map(static fn (Param $param): string => $param->name, $this->params);

        return $part->keyReadOf($params)->isSomeAnd(
            fn (Expr $key): bool => $part->isCall() || $key->literalType()?->isText() === true || in_array($key->dottedName(), [...$params, ...array_keys($this->keyLoops())], true),
        );
    }

    /**
     * The loop variables that walk one of this function's parameters — `key` in `for key in keys:` — by
     * the parameter they walk.
     *
     * @return array<string, string>
     */
    private function keyLoops(): array
    {
        $params = array_map(static fn (Param $param): string => $param->name, $this->params);
        $loops = [];

        foreach ($this->body->descendants() as $node) {
            if ($node instanceof ForLoop && $node->target->is(ExprKind::Name) && in_array($node->iterable->dottedName(), $params, true)) {
                $loops[(string) $node->target->get('name')] = $node->iterable->dottedName();
            }
        }

        return $loops;
    }

    /**
     * The keys this function reads its parameters by, one per read.
     *
     * @return list<Expr>
     */
    private function parameterReads(): array
    {
        $params = array_map(static fn (Param $param): string => $param->name, array_slice($this->params, $this->params !== [] && in_array($this->params[0]->name, ['self', 'cls'], true) ? 1 : 0));
        $keys = [];

        foreach ($this->body->descendants() as $node) {
            foreach ($node->expressions() as $expression) {
                foreach ($expression->flatten() as $part) {
                    $key = $part->keyReadOf($params);

                    if ($key->isSome()) {
                        $keys[] = $key->unwrap();
                    }
                }
            }
        }

        return $keys;
    }

    public function docstring(): Option
    {
        return $this->body->docstring();
    }

    /**
     * Does this open with a docstring that only restates its annotated signature — no summary, every entry
     * a type the annotations already give?
     */
    public function hasCeremonyDocstring(): bool
    {
        $annotated = array_map(static fn (Param $param) => $param->name, array_values(array_filter($this->params, static fn (Param $param): bool => $param->annotation !== null)));

        return $this->docstring()->isSomeAnd(fn (string $docstring) => Docstring::onlyRestates($docstring, $annotated, $this->returns !== null));
    }
}
