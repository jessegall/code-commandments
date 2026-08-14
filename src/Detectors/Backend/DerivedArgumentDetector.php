<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\Callee;
use JesseGall\CodeCommandments\Ast\Support\Derivation;
use JesseGall\CodeCommandments\Ast\Support\StructuralHash;
use JesseGall\CodeCommandments\Ast\Support\TypeResolver;
use JesseGall\CodeCommandments\Ast\TypeName;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\DerivedArgument;
use JesseGall\CodeCommandments\Sins\Sin;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
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
    /**
     * How many projections of one subject, with the subject itself NOWHERE in the call, say the callee is
     * REASSEMBLING it. Three, not two.
     *
     * Two is the shape of a generic primitive doing its job: `Header::of($screen->action->label,
     * $screen->action->description, 'zap')` fills a title and a subtitle slot, and a shared component
     * that took `ActionManifest` to read them itself would import a domain it exists to know nothing
     * about (#489). By three the argument list has stopped filling slots and started rebuilding an
     * object — `new AgentTurn($r->output(), $r->failed(), $r->errorOutput())` is a `$r` in pieces.
     *
     * The other arm needs no threshold: where the subject travels WHOLE in the same call, one projection
     * beside it is already proof the callee could have derived it.
     */
    private const int REASSEMBLED = 3;

    public function sin(): Sin
    {
        return new DerivedArgument();
    }

    public function find(Codebase $codebase): array
    {
        $resolver = TypeResolver::forCodebase($codebase);
        $redundant = [];
        $supplied = [];
        $named = [];

        $unresolved = [];

        foreach ($codebase->whereCallSite()->get() as $call) {
            $callee = Callee::of($call, $resolver);

            if ($callee === null) {
                // Only a METHOD send hides its callee: its receiver would not type, so the site could
                // be filling any slot of that name and every one of them is now half-seen. A `new` or a
                // static call names its class outright — if that did not resolve, the class is simply
                // not in the tree and owns no slot to cast doubt on. Poisoning by the bare name there
                // would let one unreadable `new` silence every constructor in the codebase at once.
                if ($call->node instanceof New_ || $call->node instanceof StaticCall) {
                    continue;
                }

                $unresolved[Callee::nameOf($call) ?? ''] = true;

                continue;
            }

            // A type building ITSELF is not a rival caller: `ReturnSlip::of($request)` doing
            // `new self($request->reference(), …)` is where this rule SENDS the derivation, so counting
            // it as "the parameter is filled from elsewhere" would veto the very fix being asked for.
            if ($this->buildsItsOwnType($call)) {
                continue;
            }

            foreach (array_keys($call->arguments()) as $position) {
                $supplied[$callee->slot($position)] ??= 0;
                $supplied[$callee->slot($position)]++;
            }

            foreach ($this->redundantPositions($call, $resolver) as $position) {
                $redundant[$callee->slot($position)][] = $call;
                $named[$callee->slot($position)] = $callee->method;
            }
        }

        $findings = [];

        foreach ($redundant as $slot => $calls) {
            if (count($calls) !== $supplied[$slot] || isset($unresolved[$named[$slot]])) {
                continue; // filled from elsewhere too, or from a caller we could not read — see the docblock
            }

            foreach ($calls as $call) {
                $findings[spl_object_id($call)] = $call;
            }
        }

        return array_values($findings);
    }

    /**
     * The argument POSITIONS at which this call hands over a projection of a subject it reaches twice —
     * `persist($request, $request->shopId())` answers `[1]`.
     *
     * Positions rather than a verdict, because whether the callee could have derived it is a question
     * about the PARAMETER: {@see find} then requires the same of every other caller filling that slot.
     * Reaching a subject once is no projection at all — every scalar utility takes `$n->methodName()`
     * and has no business learning what an AST node is.
     *
     * @return list<int>
     */
    private function redundantPositions(NodeMatch $call, TypeResolver $resolver): array
    {
        $callee = Callee::of($call, $resolver);

        if ($callee === null || $this->buildsItsOwnType($call)) {
            return [];
        }

        $whole = [];
        $flattened = [];

        foreach ($call->arguments() as $position => $argument) {
            $subject = $this->subjectOf($argument, $call);

            if ($subject === null) {
                continue;
            }

            if ($this->describesItself($subject, $call, $resolver)) {
                continue;
            }

            $hash = StructuralHash::of($subject);

            if ($subject === $argument->value) {
                $whole[$hash] = true;

                continue;
            }

            if ($callee->takesAScalarAt($position, $resolver)) {
                $flattened[$hash][$position] = true;
            }
        }

        $redundant = [];

        foreach ($flattened as $hash => $positions) {
            if (isset($whole[$hash]) || count($positions) >= self::REASSEMBLED) {
                $redundant = [...$redundant, ...array_keys($positions)];
            }
        }

        return $redundant;
    }

    /**
     * Is the subject the enclosing class DESCRIBING ITSELF — `static fn (self $s) => ListOption::of(
     * $s->id, $s->name, …)`?
     *
     * Then the derivation has nowhere to go. The callee is generic over every type that describes
     * itself to it and could only take this one by importing it; the only other move is renaming the
     * reads to `$this->` in the same class, where the knowledge already lives and nothing is decoupled.
     * A finding whose every available fix is a rename is a finding being silenced (#491) — the same
     * reason `$this` was never a subject, met here under another name.
     */
    private function describesItself(Node $subject, NodeMatch $call, TypeResolver $resolver): bool
    {
        $function = $call->enclosingFunction();
        $self = $call->enclosingClassName();

        if ($function === null || $self === null) {
            return false;
        }

        $type = $resolver->typeOf($subject, $function, $self);

        // `self`/`static` are the enclosing class under another name — a `static fn (self $x)` param is
        // typed exactly that way, and reads the same to the author.
        return $type === 'self' || $type === 'static' || $type === ltrim($self, '\\');
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
            return Derivation::namesAClass($value) ? $value : null;
        }

        if ($value instanceof Variable) {
            return $value->name === 'this' ? null : $value;
        }

        // A list is a bag of independent values, not a projection of one. What sits inside it was
        // computed for whoever consumes IT — `new Listen($r->id, LEFT, [ScrollToOffset::start($r->id)])`
        // hands the array to `Listen` and `$r->id` to `ScrollToOffset`, two callees, so `Listen` is not
        // being handed the same subject twice and could not derive the command anyway (#488).
        if ($value instanceof Array_) {
            return null;
        }

        // A derivation spelled with the CALLER's own helpers is the caller's to make. `self::fraction(
        // $draggable->corner->isTrailing())` cannot move into the callee: over there `self` is a
        // different class, and the helper is usually private to this one (#492).
        if (Derivation::routesThroughSelf($value)) {
            return null;
        }

        if (! Derivation::isReadOfSomething($value)) {
            return null;
        }

        $sole = Derivation::soleOperandIn($value);

        // Only a value that could BE a parameter is a subject. A repeated literal is not one — passing
        // `'upgrade'` beside `['upgrade']` shares a spelling, not a thing the callee could have derived
        // from. Nor is `$this`, which no callee can be handed.
        if ($sole instanceof ClassConstFetch) {
            return Derivation::namesAClass($sole) ? $sole : null;
        }

        return $sole instanceof Variable && $sole->name !== 'this' ? $sole : null;
    }

}
