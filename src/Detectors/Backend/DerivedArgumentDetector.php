<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\Derivation;
use JesseGall\CodeCommandments\Ast\Support\ReceiverResolver;
use JesseGall\CodeCommandments\Ast\Support\StructuralHash;
use JesseGall\CodeCommandments\Ast\Support\TypeResolver;
use JesseGall\CodeCommandments\Ast\TypeName;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\DerivedArgument;
use JesseGall\CodeCommandments\Sins\Sin;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;

/**
 * Flags a call site that hands over a PROJECTION of a value rather than the value itself, where following
 * the code shows the callee could have derived it — `persist($request, $request->getShopChannelId())`
 * wanted only `$request`.
 *
 * The mirror of {@see ParamResolvedFromParamDetector}: there a callee takes a container plus a key and
 * unpacks the target, so resolution moves UP to the caller; here the caller pre-computes a projection of
 * something it already holds, so the derivation moves DOWN into the callee.
 *
 * The evidence is the subject reached TWICE in one call — handed over whole and again flattened
 * (`persist($request, $request->shopId())`), or flattened two different ways (`reportsLoggedIn(
 * $t->output(), $t->exitCode())`). Either way the callee provably holds what the derivation needs, so one
 * site proves it and no counting of call sites is required. Reached once it is not evidence at all: every
 * scalar utility takes `$n->methodName()` and must not be taught what an AST node is.
 */
final class DerivedArgumentDetector implements Detector
{
    public function sin(): Sin
    {
        return new DerivedArgument();
    }

    public function find(Codebase $codebase): array
    {
        $resolver = TypeResolver::forCodebase($codebase);
        $findings = [];

        foreach ($this->callSites($codebase) as $call) {
            if ($this->reachesOneSubjectTwice($call, $resolver)) {
                $findings[] = $call;
            }
        }

        return $findings;
    }

    /**
     * Does this call reach the SAME subject through two or more of its arguments, at least one of them a
     * derivation over it — `persist($request, $request->shopId())`, `reportsLoggedIn($t->output(),
     * $t->exitCode())`?
     *
     * That is the whole rule, and it is provable at ONE site: the callee is handed a value twice over,
     * once flattened. Whatever it needs, it could have derived from the subject, so the subject is what
     * the parameter wanted. Reaching a subject ONCE proves nothing — every scalar utility in the world
     * takes `$n->methodName()` and has no business learning what an AST node is.
     */
    private function reachesOneSubjectTwice(NodeMatch $call, TypeResolver $resolver): bool
    {
        $callee = $this->calleeOf($call, $resolver);

        if ($callee === null || $this->buildsItsOwnType($call)) {
            return false;
        }

        $whole = [];
        $flattened = [];

        foreach ($call->arguments() as $position => $argument) {
            $subject = $this->subjectOf($argument, $call);

            if ($subject === null) {
                continue;
            }

            $hash = StructuralHash::of($subject);

            if ($subject === $argument->value) {
                $whole[$hash] = true;

                continue;
            }

            if ($this->fillsAScalar($callee, $position, $resolver)) {
                $flattened[$hash][$position] = true;
            }
        }

        foreach ($flattened as $hash => $positions) {
            if (isset($whole[$hash]) || count($positions) >= 2) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this call the type's OWN construction — `new self(...)`, `new static(...)`? The named
     * constructor mapping a subject onto its own fields is exactly where the rule sends the derivation,
     * so flagging it would make the sin unfixable: every fix would land on another finding.
     */
    private function buildsItsOwnType(NodeMatch $call): bool
    {
        $built = $call->node instanceof New_ ? $call->newClassName() : null;

        return $built !== null && $built === $call->enclosingClassName();
    }

    /**
     * The subject this argument reaches — the argument itself when it IS a plain subject (`$request`,
     * `Order::class`), the sole operand when it is a derivation over one (`$request->shopId()`), and null
     * for anything else.
     *
     * Null is the common answer, and deliberately: `$i + 1` mentions `$i` but derives nothing from it,
     * and an argument consuming two operands (`$row->get(Column::Brand)`) could not be moved into the
     * callee without dragging the other one along.
     *
     * A bare field read (`$pickList->id`) counts only when the SUBJECT travels in the same call. On its
     * own it is every id ever handed to anything; beside the object it came from it is plainly redundant.
     */
    private function subjectOf(Arg $argument, NodeMatch $call): ?Node
    {
        if ($argument->unpack || $argument->name !== null) {
            return null; // a named argument is matched by name, not position
        }

        $value = $argument->value;

        if ($value instanceof ClassConstFetch) {
            return $value;
        }

        if ($value instanceof Variable) {
            return $value->name === 'this' ? null : $value;
        }

        $sole = Derivation::soleOperandIn($value);

        // Only a value that could BE a parameter is a subject. A repeated literal is not one — passing
        // `'upgrade'` beside `['upgrade']` shares a spelling, not a thing the callee could have derived
        // from. Nor is `$this`, which no callee can be handed.
        if ($sole instanceof ClassConstFetch) {
            return $sole;
        }

        return $sole instanceof Variable && $sole->name !== 'this' ? $sole : null;
    }


    /**
     * Does this position fill a plain SCALAR parameter? A parameter already typed as an object is asking
     * for the object, and an unknown signature (a vendor method the scan cannot see) proves nothing.
     *
     * @param  array{0: string, 1: string}  $callee
     */
    private function fillsAScalar(array $callee, int $position, TypeResolver $resolver): bool
    {
        $declared = $resolver->paramTypeOf($callee[0], $callee[1], $position);

        return $declared !== null && ! TypeName::isClassName($declared);
    }

    /**
     * Every kind of call SITE — a method send, a `new`, a static call. A constructor is where this shape
     * lives most often, so a rule watching only method sends would miss the case it was asked for.
     *
     * @return list<NodeMatch>
     */
    private function callSites(Codebase $codebase): array
    {
        return [
            ...$codebase->whereMethod()->get(),
            ...$codebase->whereNew()->get(),
            ...$codebase->whereStaticCall()->get(),
        ];
    }

    /**
     * The signature this call site fills — the class that DECLARES it and the method name, so calls
     * through subclasses of one base collapse into a single group. A `new` fills a constructor; a static
     * call names its own class. Null when the callee isn't in the scanned tree.
     *
     * @return array{0: string, 1: string}|null
     */
    private function calleeOf(NodeMatch $call, TypeResolver $resolver): ?array
    {
        [$receiver, $method] = match (true) {
            $call->node instanceof New_ => [$call->newClassName(), '__construct'],
            $call->node instanceof StaticCall => [$call->staticCallClass(), $call->staticCallMethod()],
            default => [ReceiverResolver::typeOf($call), $call->methodCallName()],
        };

        $owner = $method === null ? null : $resolver->declaringClassOfMethod($receiver, $method);

        return $owner === null ? null : [$owner, $method];
    }

}
