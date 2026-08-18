<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\Callee;
use JesseGall\CodeCommandments\Ast\Support\TypeResolver;
use JesseGall\CodeCommandments\Sins\Backend\ConvertedArgument;
use JesseGall\CodeCommandments\Sins\Sin;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

/**
 * Flags a parameter declared in the wrong currency: the caller holds a value, converts it into the form
 * the callee wants, and passes the conversion — `Raises::of(ClassAlias::of($interaction), …)`. The
 * evidence is the SAME wrapper at the same parameter of the same callee again and again, which says the
 * callee knows the conversion and is making every call site keep it. Widening the parameter to what
 * callers hold also closes a hole nothing catches: where both sides are `string`, a site that FORGETS the
 * wrapper compiles perfectly. The sibling of {@see DerivedArgumentDetector}, which reads a subject twice.
 */
final class ConvertedArgumentDetector extends RecurringPattern
{
    /**
     * The conversion must be what that parameter USUALLY gets. A wrapper on two of nineteen sites is one
     * caller's business — the parameter plainly takes the raw form and those two are converting for
     * reasons of their own; at half or more, the conversion is the parameter's real currency.
     */
    private const float DOMINANT = 0.5;

    /**
     * @var array<int, array<string, int>>  codebase id => slot => how many call sites fill it
     */
    private array $supplied = [];

    public function sin(): Sin
    {
        return new ConvertedArgument();
    }

    protected function candidates(Codebase $codebase): array
    {
        return $codebase->whereCallSite()->get();
    }

    /**
     * The group: `declaringClass::method#position=Wrapper::method`. Null unless this call wraps an
     * argument in a conversion the callee could have made itself.
     */
    protected function fingerprint(NodeMatch $call, Codebase $codebase): ?string
    {
        $resolver = TypeResolver::forCodebase($codebase);
        $callee = Callee::of($call, $resolver);

        if ($callee === null) {
            return null;
        }

        foreach ($call->arguments() as $position => $argument) {
            $wrapper = $this->conversionIn($argument, $call);

            if ($wrapper !== null && $callee->takesAScalarAt($position, $resolver)) {
                return $callee->slot($position) . '=' . $wrapper;
            }
        }

        return null;
    }

    /**
     * The conversion must DOMINATE the parameter it fills — see {@see DOMINANT}.
     *
     * @param  list<NodeMatch>  $occurrences
     */
    protected function qualifies(array $occurrences, Codebase $codebase): bool
    {
        $slot = $this->slotOf($occurrences[0], $codebase);
        $sites = $slot === null ? 0 : ($this->supplied($codebase)[$slot] ?? 0);

        return $sites > 0 && count($occurrences) / $sites >= self::DOMINANT;
    }

    /**
     * The single-argument CONVERSION this argument is wrapped in — `ClassAlias::of($x)` — as
     * `Class::method`, or null when the argument is not a conversion the callee could take over.
     *
     * A static factory, because that is a conversion with a name and a home the callee can reach. Not a
     * global function: `count($xs)`, `strlen($s)`, `__('…')` are language and framework vocabulary, and
     * a callee taking the array so it can count it is a worse signature, not a better one. Not
     * `self::`/`static::` either — that helper is the caller's own, and means a different class over
     * there.
     */
    private function conversionIn(Arg $argument, NodeMatch $call): ?string
    {
        $value = $argument->value;

        if ($argument->unpack || $argument->name !== null || ! $value instanceof StaticCall) {
            return null;
        }

        if (! $value->class instanceof Name || ! $value->name instanceof Identifier) {
            return null;
        }

        $class = $value->class->toString();

        if (count($value->args) !== 1 || in_array(strtolower($class), ['self', 'static', 'parent'], true)) {
            return null;
        }

        return $class === $call->enclosingClassName() ? null : $class . '::' . $value->name->toString();
    }

    /**
     * The parameter slot this call's conversion fills, or null when it has none.
     */
    private function slotOf(NodeMatch $call, Codebase $codebase): ?string
    {
        $key = $this->fingerprint($call, $codebase);

        return $key === null ? null : explode('=', $key)[0];
    }

    /**
     * How many call sites fill each parameter slot — the denominator behind {@see DOMINANT}. Memoised
     * per codebase, since it costs one walk of every call site.
     *
     * @return array<string, int>
     */
    private function supplied(Codebase $codebase): array
    {
        $id = spl_object_id($codebase);

        if (isset($this->supplied[$id])) {
            return $this->supplied[$id];
        }

        $resolver = TypeResolver::forCodebase($codebase);
        $counts = [];

        foreach ($this->candidates($codebase) as $call) {
            $callee = Callee::of($call, $resolver);

            if ($callee === null) {
                continue;
            }

            foreach (array_keys($call->arguments()) as $position) {
                $counts[$callee->slot($position)] = ($counts[$callee->slot($position)] ?? 0) + 1;
            }
        }

        return $this->supplied[$id] = $counts;
    }
}
