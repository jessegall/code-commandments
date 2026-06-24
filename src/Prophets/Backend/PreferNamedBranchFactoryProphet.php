<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Steer a NON-TRIVIAL dispatch-branch factory toward a named `*Factory` static
 * method returning `callable`, instead of an inline `->then(fn () => …)` that
 * captures `$this` and does real work (#81).
 *
 * A resolver call site reads best as a TABLE — predicate → factory, one line
 * each. When a branch's result factory is more than a trivial constant
 * (it reaches for `$this` / a dependency and builds something), it earns a name:
 * `FieldFactory::object($this->objects)` documents intent, is reusable across
 * resolvers, and builds its dependency once. A bare `fn () => SomeEnum::Case`
 * stays inline — extracting a one-liner constant is pure ceremony.
 *
 * Advisory, never a sin; not auto-fixable (extracting a class is a refactor).
 *
 *
 *
 *
 *
 *
 *
 * @method-generated-start
 * @method static factoryMethods(array $value)
 * @method-generated-end
 */
#[IntroducedIn('1.129.0')]
class PreferNamedBranchFactoryProphet extends PhpCommandment
{
    /** Dispatch-pairing methods whose argument is the result factory. */
    private const DEFAULT_FACTORY_METHODS = ['then'];

    public function description(): string
    {
        return 'Extract a non-trivial ->then() branch factory into a named *Factory method returning callable';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A `->then(fn (...) => ...)` branch factory in a resolver chain is non-trivial: it captures `$this` (a constructor dependency) AND its body does real work (a method/static call or `new`), not just a bare `return <const/enum/property>`.')
            ->leaveWhen('the closure is a trivial constant/enum/property return (`fn () => SchemaFieldType::Int`), a first-class callable to an already-named method (`Capture::make()`, `T_Array::empty(...)`), or a genuine one-off with no dependency and no reuse.')
            ->whenUnsure('ask: does this factory deserve a name? A captured dependency, reuse across resolvers, or escaping a private builder = yes (extract to a `*Factory` static method returning `callable`); a trivial one-off constant = no, keep it inline.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A resolver chain reads like a dispatch TABLE — each line a predicate paired with
a result factory via `->then()`. When the factory is a trivial constant, an
inline closure is fine. When it captures `$this` and builds something, it has a
name hiding in it: pull it onto a dedicated `*Factory` class as a static method
returning `callable`, so the call site stays declarative, the factory is named
and reusable, and its dependency is built in one place.

Bad — an inline factory that captures a dependency and does work:
    Resolver::firstResultWins(
        IsObjectType::for($this->objects)
            ->then(fn (FieldTypeRequest $r) => CreatableFieldType::object(
                $this->objects->slugForToken((string) $r->type)->getOrThrow(),
            )),
    );

Good — a named factory, the call site is a table:
    final class FieldFactory
    {
        /** @return callable(FieldTypeRequest): CreatableFieldType */
        public static function object(SchemaTypeRegistry $objects): callable
        {
            return static fn (FieldTypeRequest $r) => CreatableFieldType::object(
                $objects->slugForToken((string) $r->type)->getOrThrow(),
            );
        }
    }
    // …->then(FieldFactory::object($this->objects))

WHAT FIRES — a `->then(<closure>)` whose closure body references `$this` AND
contains real work: a method call, a static call, or a `new`.

WHAT DOES NOT — a trivial `fn () => SomeEnum::Case` / `fn () => self::CONST` /
`fn () => $this->prop` (a constant or bare property — pure ceremony to extract),
a first-class callable to an already-named method (`->then(Capture::make())`,
`->then(WireType::scalar(...))`), or a closure that does not capture `$this`
(no dependency to home). Advisory — extraction is a refactor; weigh reuse.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $methods = $this->factoryMethods();
        $warnings = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Expr\MethodCall::class) as $call) {
            if (! $call->name instanceof Node\Identifier
                || ! in_array($call->name->toString(), $methods, true)
                || count($call->args) !== 1
                || ! $call->args[0] instanceof Node\Arg
            ) {
                continue;
            }

            $closure = $call->args[0]->value;

            if (! $this->isNonTrivialCapturingFactory($closure)) {
                continue;
            }

            $line = $closure->getStartLine();
            $warnings[] = $this->warningAt(
                $line,
                'This `->then()` branch factory captures `$this` and does real work — extract it to a named static method on a `*Factory` class returning `callable` (e.g. `FieldFactory::object($this->dep)`), so the resolver reads as a table and the factory is named and reusable.',
                $this->lineSnippet($content, $line),
                'branch-factory',
            );
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    private function isNonTrivialCapturingFactory(Expr $closure): bool
    {
        if (! $closure instanceof Expr\Closure && ! $closure instanceof Expr\ArrowFunction) {
            return false; // a first-class callable / named ref is already named
        }

        $body = $closure instanceof Expr\ArrowFunction ? [$closure->expr] : $closure->stmts;
        $finder = new NodeFinder;

        // Must capture $this (a dependency to home on the factory class).
        $capturesThis = $finder->findFirst($body, static fn (Node $n): bool =>
            $n instanceof Expr\Variable && $n->name === 'this') !== null;

        if (! $capturesThis) {
            return false;
        }

        // Must do real work — a method/static call or `new`. A bare const/enum/
        // property return is trivial (pure ceremony to extract).
        return $finder->findFirst($body, static fn (Node $n): bool =>
            $n instanceof Expr\MethodCall
            || $n instanceof Expr\NullsafeMethodCall
            || $n instanceof Expr\StaticCall
            || $n instanceof Expr\New_) !== null;
    }

    /**
     * @return list<string>
     */
    private function factoryMethods(): array
    {
        $methods = $this->config('factory_methods', self::DEFAULT_FACTORY_METHODS);

        return is_array($methods) && $methods !== [] ? array_values(array_map('strval', $methods)) : self::DEFAULT_FACTORY_METHODS;
    }

}
