<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\ReceiverResolver;
use JesseGall\CodeCommandments\Ast\Support\TypeResolver;
use JesseGall\CodeCommandments\Detectors\Backend\Config\RepeatedNamedCallConfig;
use JesseGall\CodeCommandments\Sins\Backend\RepeatedNamedCall;
use JesseGall\CodeCommandments\Sins\Sin;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

/**
 * Flags a `with`-style (variadic-parameter) method — `copyWith(mixed ...$changes)` — called with the SAME
 * named argument, carrying construction boilerplate, at {@see $threshold}+ sites that resolve to the SAME
 * method (grouped by its declaring class, so inherited calls on subclasses of one base collapse into one
 * group and unrelated classes stay separate). The repeated call is a missing named method on the type.
 */
final class RepeatedNamedCallDetector extends RecurringPattern
{
    use RepeatedNamedCallConfig;

    public function sin(): Sin
    {
        return new RepeatedNamedCall();
    }

    protected function candidates(Codebase $codebase): array
    {
        return $codebase->whereMethod()->get();
    }

    protected function minimumOccurrences(): int
    {
        return $this->threshold;
    }

    /**
     * The group key a repeated call belongs to — `declaringClass::method#namedKeys` — or null when the call
     * isn't a repeatable variadic named-argument construction.
     */
    protected function fingerprint(NodeMatch $call, Codebase $codebase): ?string
    {
        $resolver = TypeResolver::forCodebase($codebase);
        $named = $call->namedArguments();

        if ($named === [] || ! $this->carriesConstruction($named)) {
            return null;
        }

        $method = $call->methodCallName();
        $receiver = ReceiverResolver::typeOf($call);

        if ($method === null || $receiver === null || ! $resolver->methodIsVariadic($receiver, $method)) {
            return null;
        }

        // Group by the named key AND the value's structural SHAPE, so only genuinely same-shaped repetitions
        // collapse — `metadata: Payload::from([...])->toArray()` stays apart from a one-off `metadata: [...]`.
        $slots = array_map(fn (Arg $arg): string => $arg->name?->toString() . '=' . $this->shapeOf($arg->value), $named);
        sort($slots);

        return $resolver->declaringClassOfMethod($receiver, $method) . '::' . $method . '#' . implode(',', $slots);
    }

    /**
     * A class-agnostic structural signature of an argument value — its call/construction skeleton with the
     * concrete classes and literals stripped, so `A::from([...])->toArray()` and `B::from([...])->toArray()`
     * share a shape but a spread array or an `array_map` do not.
     */
    private function shapeOf(Node $expr): string
    {
        return match (true) {
            $expr instanceof MethodCall, $expr instanceof NullsafeMethodCall =>
                $this->shapeOf($expr->var) . '->' . ($expr->name instanceof Identifier ? $expr->name->toString() : '?'),
            $expr instanceof StaticCall => '::' . ($expr->name instanceof Identifier ? $expr->name->toString() : '?'),
            $expr instanceof FuncCall => 'fn:' . ($expr->name instanceof Name ? $expr->name->toString() : '?'),
            $expr instanceof New_ => 'new',
            $expr instanceof Array_ => '[]',
            default => $expr::class,
        };
    }

    /**
     * Does any named argument carry a CONSTRUCTED value — a call, a `new`, or an array literal — rather than
     * a bare variable/scalar? That construction boilerplate is what the helper hides; a trivial pass-through
     * isn't worth a method.
     *
     * @param  list<Arg>  $named
     */
    private function carriesConstruction(array $named): bool
    {
        foreach ($named as $arg) {
            if ($arg->value instanceof StaticCall
                || $arg->value instanceof MethodCall
                || $arg->value instanceof New_
                || $arg->value instanceof Array_) {
                return true;
            }
        }

        return false;
    }
}
