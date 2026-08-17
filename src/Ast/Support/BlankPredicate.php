<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;

/**
 * Which static methods DECIDE whether their argument is blank — `EmptyString::is($x)` and the
 * `isNot` that delegates to it. A codebase that names the blankness question is still asking it, so a
 * rule reading "is this value read back as missing" must see through the helper as well as the `=== ''`
 * it replaced. Answered by reading the method's own body, never by its name.
 */
final class BlankPredicate
{
    use MemoisedPerCodebase;

    /**
     * @var array<string, bool>
     */
    private array $decides = [];

    public function __construct(private readonly Codebase $codebase) {}

    /**
     * Does `$class::$method($subject)` answer whether $subject is blank?
     */
    public function decidesBlankness(?string $class, ?string $method): bool
    {
        if ($class === null || $method === null) {
            return false;
        }

        $key = $class.'::'.$method;

        return $this->decides[$key] ??= $this->resolve($class, $method, [$key => true]);
    }

    /**
     * @param  array<string, true>  $seen  the predicates already being resolved, so a pair that defers to
     *                                     each other cannot recurse for ever
     */
    private function resolve(string $class, string $method, array $seen): bool
    {
        $declaration = $this->codebase->classNamed($class)->method($method);

        if ($declaration === null || ! $declaration->isStatic()) {
            return false;
        }

        $subject = $declaration->params[0]->var ?? null;

        if (! $subject instanceof Variable || ! is_string($subject->name)) {
            return false;
        }

        $body = new AstNode($declaration);

        if (in_array($subject->name, $body->variablesTestedForBlankness(), true)) {
            return true;
        }

        return $this->defersToAPredicate($body, $subject->name, $class, $seen);
    }

    /**
     * Does the body hand its own subject to another predicate — `isNot` returning `! self::is($value)`?
     *
     * @param  array<string, true>  $seen
     */
    private function defersToAPredicate(AstNode $body, string $subject, string $class, array $seen): bool
    {
        foreach ($body->descendantsOfType(StaticCall::class) as $call) {
            $argument = $call->arguments()[0]->value ?? null;

            if (! $argument instanceof Variable || $argument->name !== $subject) {
                continue;
            }

            $target = $call->staticCallClass() ?? $class;
            $key = $target.'::'.$call->staticCallMethod();

            if (! isset($seen[$key]) && $this->resolve($target, (string) $call->staticCallMethod(), [...$seen, $key => true])) {
                return true;
            }
        }

        return false;
    }
}
