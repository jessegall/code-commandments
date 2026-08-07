<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Laravel\LaravelNode;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\UseItem as UseUse;
use PhpParser\NodeFinder;

/**
 * Which container ABSTRACTS the codebase actually resolves. A binding only earns its keep if
 * something outside its OWN registration asks for the abstract — by type-hint, by `app()`/`make()`,
 * or by naming the class at all. A reference inside the registration OF THAT SAME ABSTRACT doesn't
 * count: a factory closure building the very thing it binds proves nothing about demand. A reference
 * inside a DIFFERENT binding's closure does count — one factory resolving another abstract is exactly
 * how a registry is reached, and that demand is as real as any type-hint.
 */
final class ContainerBindings
{
    use MemoisedPerCodebase;

    /**
     * @param  array<string, true>  $referenced  FQCN => true, counting only references outside registrations
     */
    private function __construct(private readonly array $referenced) {}

    /**
     * Does anything outside a binding registration reach for $abstract? False means the wiring is
     * dead — nothing can ever ask the container for it.
     */
    public function isResolvedSomewhere(?string $abstract): bool
    {
        // An abstract that could not be named is not one anything reaches for.
        return $abstract !== null && isset($this->referenced[$abstract]);
    }

    /**
     * The abstract a binding call registers, or null when this isn't a binding (or its abstract is
     * not a statically-known class). `bind`/`singleton`/`scoped`/`instance` and their `…If` variants,
     * on `$this->app`, an `$app`, or the `App` facade.
     */
    public static function boundAbstract(Node $node): ?string
    {
        return LaravelNode::boundAbstractOf($node);
    }

    protected static function build(Codebase $codebase): static
    {
        $referenced = [];
        $finder = new NodeFinder;

        foreach ($codebase->files() as $file) {
            // Keyed BY ABSTRACT: a registration only discounts references to the very thing IT binds.
            // A sibling binding's factory reaching for another abstract is real demand — the registry
            // that `bind(Provider::class, fn ($app) => $app->make(Registry::class)->get(…))` resolves
            // is load-bearing, and deleting it would break every Provider resolution.
            $registrations = [];

            foreach ($finder->find($file->ast, static fn (Node $n): bool => self::boundAbstract($n) !== null) as $call) {
                $registrations[(string) self::boundAbstract($call)][] = [$call->getStartFilePos(), $call->getEndFilePos()];
            }

            foreach ($finder->find($file->ast, static fn (Node $n): bool => self::namedClass($n) !== null) as $reference) {
                $named = (string) self::namedClass($reference);

                if (self::within($reference, $registrations[$named] ?? [])) {
                    continue;
                }

                $referenced[$named] = true;
            }
        }

        return new self($referenced);
    }

    /**
     * The class this node NAMES, however it spells it — a resolved `Name` (a type-hint, a `new`, an
     * `extends`), a `::class` fetch, or a string literal holding an FQCN (a container key written by
     * hand). Anything else names nothing.
     */
    private static function namedClass(Node $node): ?string
    {
        if ($node instanceof Name) {
            return self::isDemand($node) ? ltrim($node->toString(), '\\') : null;
        }

        if ($node instanceof String_) {
            return str_contains($node->value, '\\') ? ltrim($node->value, '\\') : null;
        }

        return null;
    }

    /**
     * Does naming a class HERE mean something demands it from the container? Two positions name a
     * class without asking for it, and both are everywhere in real code:
     *
     * - a `use` import — every consumer file lists the class at the top, so counting imports would
     *   make every binding look live and the rule inert;
     * - an `implements`/`extends` clause — the implementation declaring it fulfils the abstract is
     *   the SUPPLY side, not the demand side. A dead abstract still has its implementation.
     */
    private static function isDemand(Name $node): bool
    {
        $parent = $node->getAttribute('parent');

        return ! $parent instanceof UseUse && ! $parent instanceof ClassLike;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $spans
     */
    private static function within(Node $node, array $spans): bool
    {
        $start = $node->getStartFilePos();

        return array_any($spans, static fn (array $span): bool => $start >= $span[0] && $start <= $span[1]);
    }
}
