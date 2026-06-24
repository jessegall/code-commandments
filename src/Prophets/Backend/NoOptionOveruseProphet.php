<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\CallGraph\OptionConsumptionResolver;
use JesseGall\CodeCommandments\Support\Resolvers\Ast\FileImports;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * The counterweight to PreferOptionOverNull: flag Option used as ceremony,
 * where there is no absence to model. Three smells:
 *
 *   1. a method typed `: Option` whose every return is `some(...)` and never
 *      `none()` — the value is never absent, so the Option only adds noise;
 *   2. constructing an Option and immediately unwrapping it
 *      (`Option::some($x)->getOrThrow()`).
 *   3. (cross-cutting, needs the index) a method typed `: Option` whose EVERY
 *      call site only UNWRAPS or queries it (`->getOrElse`/`->isSome`) and never
 *      keeps it as an Option (no `->map`/chain) — the box no caller wants.
 *
 * Option is for value-OR-nothing. When there is always a value, return it
 * (or throw when it genuinely cannot be produced).
 *
 *
 *
 * @method-generated-start
 * @method static optionClass(string $value)
 * @method-generated-end
 */
#[IntroducedIn('1.75.0')]
class NoOptionOveruseProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    private ?CodebaseIndex $index = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }

    private const DEFAULT_OPTION_CLASS = 'App\\Support\\Option';

    private const DEFAULT_SOME_METHODS = ['some', 'of'];

    private const DEFAULT_NONE_METHODS = ['none', 'empty', 'nothing'];

    private const DEFAULT_UNWRAP_METHODS = ['getOrThrow', 'getOr'];

    public function description(): string
    {
        return 'Do not use Option as ceremony where there is no absence to model';
    }

    /**
     * Never flag the configured Option primitive itself.
     *
     * @return list<class-string>
     */
    public function exemptClasses(): array
    {
        $class = ltrim((string) $this->config('option_class', self::DEFAULT_OPTION_CLASS), '\\');

        return $class === '' ? [] : [$class];
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A method typed `: Option` never returns an empty one (every '
                . 'return is `some(...)`), or an Option is constructed and '
                . 'immediately unwrapped — ceremony with no absence to model.'
            )
            ->leaveWhen(
                'The method genuinely returns `none()` on some path, or the Option '
                . 'flows to callers that map()/each() over it. Then the absence is '
                . 'real and the Option earns its keep.'
            )
            ->whenUnsure(
                'If the value is never absent, return it directly — or throw when it '
                . 'genuinely cannot be produced. Option is for value-or-nothing, not '
                . 'always-value.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Option models value-OR-NOTHING. When there is always a value, wrapping it in an
Option is ceremony — the empty case never happens, so every caller pays the
unwrap cost for an absence that cannot occur. This is the failure mode of
over-applying "prefer Option over null": the cure becomes the disease.

Bad — never empty, so the Option lies:
    public function current(): Option
    {
        return Option::some($this->value);   // every path is some() — no none()
    }

Good — there is always a value, so return it:
    public function current(): Value
    {
        return $this->value;
    }

Bad — construct then immediately unwrap (pure noise):
    $value = Option::some($this->compute())->getOrThrow();

Good:
    $value = $this->compute();

WHAT FIRES —
  1. a method whose return type is the Option class and whose EVERY return is
     `Option::some(...)`/`::of(...)` with no `Option::none()`/`::empty()` on any
     path (the value is never absent);
  2. `Option::some(...)->getOrThrow()` / `Option::none()->getOr(...)` — an Option
     constructed and unwrapped in the same expression.

WHAT DOES NOT — a method that returns `none()` on some path, returns a variable
or a delegated/`map()`ed Option (absence may be real), or unwraps an Option it
received rather than just made.

Configure via:

    Backend\NoOptionOveruseProphet::class => [
        'option_class' => App\Support\Option::class,
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $finder = new NodeFinder;
        $optionShort = $this->optionShort();
        $warnings = [];

        $this->flagAlwaysSomeMethods($finder, $ast, $optionShort, $warnings);
        $this->flagConstructThenUnwrap($finder, $ast, $optionShort, $warnings);
        $this->flagCeremonyOnlyCallers($finder, $ast, $optionShort, $warnings);

        if ($warnings === []) {
            return $this->righteous();
        }

        return Judgment::withWarnings($warnings);
    }

    /**
     * Smell 3 (cross-cutting): a method typed `: Option` whose EVERY resolved call
     * site immediately unwraps or queries it and NONE keeps it as an Option (no
     * map/chain) — the Option boxes a value every caller only unboxes, so it earns
     * nothing a nullable would not. Silent when there are no resolved callers (we
     * cannot tell) or any caller chains the Option (then it does earn its keep).
     *
     * @param  array<Node>  $ast
     * @param  list<\JesseGall\CodeCommandments\Results\Warning>  $warnings
     */
    private function flagCeremonyOnlyCallers(NodeFinder $finder, array $ast, string $optionShort, array &$warnings): void
    {
        if ($this->index === null) {
            return;
        }

        $namespace = FileImports::namespace($ast);
        $resolver = new OptionConsumptionResolver;

        foreach ($finder->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if ($class->name === null) {
                continue;
            }

            $fqcn = ($namespace !== null && $namespace !== '' ? $namespace . '\\' : '') . $class->name->toString();

            foreach ($class->getMethods() as $method) {
                if ($method->stmts === null || $this->returnTypeShort($method) !== $optionShort) {
                    continue;
                }

                $consumptions = $resolver->consumptions($fqcn, $method->name->toString(), $this->index);

                // No resolved callers → cannot judge. Any caller that CHAINS the
                // Option (map/filter/…) means it earns its keep → leave it.
                if ($consumptions === [] || in_array('chain', $consumptions, true)) {
                    continue;
                }

                // Need at least one ROUND-TRIP unwrap (`getOrElse`/`getOrNull`) as the
                // ceremony tell — a value boxed only to be unboxed. Callers that only
                // `getOrThrow` (require presence) are a legit use, not ceremony.
                if (! in_array('unwrap', $consumptions, true)) {
                    continue;
                }

                // …and EVERY consumption must be ceremony (unwrap / query / require).
                // A 'nullcheck'/'passed'/'other' means the caller does something more.
                $ceremony = array_filter($consumptions, static fn (string $k): bool => in_array($k, ['unwrap', 'query', 'require'], true));

                if (count($ceremony) !== count($consumptions)) {
                    continue;
                }

                $warnings[] = $this->warningAt(
                    $method->getStartLine(),
                    sprintf(
                        '%s() returns `: %s` but every one of its %d resolved call site(s) immediately unwraps or queries it (%s) — no caller keeps it as an Option (no map/chain). The Option boxes a value the callers only unbox; return the nullable directly.',
                        $method->name->toString(),
                        $optionShort,
                        count($consumptions),
                        implode('/', array_values(array_unique($consumptions))),
                    ),
                    null,
                    'option-overuse-ceremony-callers:' . $method->name->toString(),
                );
            }
        }
    }

    /**
     * @param  array<Node>  $ast
     * @param  list<\JesseGall\CodeCommandments\Results\Warning>  $warnings
     */
    private function flagAlwaysSomeMethods(NodeFinder $finder, array $ast, string $optionShort, array &$warnings): void
    {
        /** @var array<Node\Stmt\ClassMethod> $methods */
        $methods = $finder->findInstanceOf($ast, Node\Stmt\ClassMethod::class);

        foreach ($methods as $method) {
            if ($method->stmts === null || $this->returnTypeShort($method) !== $optionShort) {
                continue;
            }

            /** @var array<Node\Stmt\Return_> $returns */
            $returns = $finder->findInstanceOf($method->stmts, Node\Stmt\Return_::class);

            $anySome = false;

            foreach ($returns as $return) {
                $kind = $this->optionConstructorKind($return->expr, $optionShort);

                // A none(), or anything that is not a some() construction
                // (a variable, a delegated/mapped Option, …) means absence may
                // be real — stay silent.
                if ($kind !== 'some') {
                    continue 2;
                }

                $anySome = true;
            }

            if ($anySome) {
                $warnings[] = $this->warningAt(
                    $method->getStartLine(),
                    sprintf(
                        '%s() is typed `: %s` but every return is `%s::some(...)` — it is never empty, so the Option only adds an unwrap at each call site. Return the value directly (or throw when it genuinely cannot be produced). Option is for value-or-nothing.',
                        $method->name->toString(),
                        $optionShort,
                        $optionShort,
                    ),
                    null,
                    'option-overuse-always-some:' . $method->name->toString(),
                );
            }
        }
    }

    /**
     * @param  array<Node>  $ast
     * @param  list<\JesseGall\CodeCommandments\Results\Warning>  $warnings
     */
    private function flagConstructThenUnwrap(NodeFinder $finder, array $ast, string $optionShort, array &$warnings): void
    {
        $unwrap = $this->unwrapMethods();

        foreach ($finder->findInstanceOf($ast, Expr\MethodCall::class) as $call) {
            if (! $call->name instanceof Node\Identifier
                || ! in_array($call->name->toString(), $unwrap, true)
                || $call->isFirstClassCallable()
            ) {
                continue;
            }

            // The receiver is an Option constructed right here.
            if ($this->optionConstructorKind($call->var, $optionShort) === null) {
                continue;
            }

            $warnings[] = $this->warningAt(
                $call->getStartLine(),
                sprintf(
                    'An Option is constructed and immediately unwrapped with `->%s()` — pure ceremony. Use the value directly instead of wrapping then unwrapping it.',
                    $call->name->toString(),
                ),
                null,
                'option-overuse-unwrap',
            );
        }
    }

    /**
     * 'some' / 'none' / null — whether $expr is `Option::some(...)`/`::of(...)`,
     * `Option::none()`/`::empty()`, or neither.
     */
    private function optionConstructorKind(?Node $expr, string $optionShort): ?string
    {
        if (! $expr instanceof Expr\StaticCall
            || ! $expr->class instanceof Node\Name
            || $expr->class->getLast() !== $optionShort
            || ! $expr->name instanceof Node\Identifier
        ) {
            return null;
        }

        $method = $expr->name->toString();

        if (in_array($method, $this->someMethods(), true)) {
            return 'some';
        }

        if (in_array($method, $this->noneMethods(), true)) {
            return 'none';
        }

        return null;
    }

    private function returnTypeShort(Node\Stmt\ClassMethod $method): ?string
    {
        $type = $method->returnType;

        if ($type instanceof Node\NullableType) {
            $type = $type->type;
        }

        return $type instanceof Node\Name ? $type->getLast() : null;
    }

    private function optionShort(): string
    {
        $class = (string) $this->config('option_class', self::DEFAULT_OPTION_CLASS);
        $parts = explode('\\', ltrim($class, '\\'));

        return end($parts) ?: 'Option';
    }

    /**
     * @return list<string>
     */
    private function someMethods(): array
    {
        return $this->stringList('some_methods', self::DEFAULT_SOME_METHODS);
    }

    /**
     * @return list<string>
     */
    private function noneMethods(): array
    {
        return $this->stringList('none_methods', self::DEFAULT_NONE_METHODS);
    }

    /**
     * @return list<string>
     */
    private function unwrapMethods(): array
    {
        return $this->stringList('unwrap_methods', self::DEFAULT_UNWRAP_METHODS);
    }

    /**
     * @param  list<string>  $default
     * @return list<string>
     */
    private function stringList(string $key, array $default): array
    {
        $configured = $this->config($key, $default);

        return is_array($configured) && $configured !== []
            ? array_values(array_filter($configured, 'is_string'))
            : $default;
    }
}
