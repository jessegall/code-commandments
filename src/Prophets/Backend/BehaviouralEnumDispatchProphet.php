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
 * Flag a WIDE per-case dispatch keyed on a closed-set enum where each arm is
 * BEHAVIOUR — it calls methods, branches, or delegates — and suggest extracting
 * one strategy/applicator object per case behind a small interface, dispatched
 * through a registration map. "Edit a growing match every time" becomes "add a
 * class + one map entry" (open for extension, closed for modification), and each
 * case is independently testable.
 *
 * The behavioural sibling of {@see PreferTypeMethodOverInlineDispatchProphet}:
 * that one moves VALUE/constant per-case maps onto the enum as a method; this one
 * fires on the case it deliberately leaves — arms whose logic calls the caller's
 * collaborators (so it cannot live as a pure enum method) and should become a
 * strategy object instead. ({@see ResolverPatternProphet} covers the third leg —
 * first-match dispatch → the resolver kernel.)
 *
 * Advisory, never a sin; not auto-fixable (extraction needs naming + DI choices).
 */
#[IntroducedIn('1.131.0')]
class BehaviouralEnumDispatchProphet extends PhpCommandment
{
    private const DEFAULT_MIN_ARMS = 5;

    public function description(): string
    {
        return 'Extract a wide behavioural per-enum-case dispatch into strategy objects + a registration map';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A wide `match`/`switch` (≥ 5 arms, configurable) dispatches per case of ONE closed-set enum, and each arm is BEHAVIOUR — it calls methods / branches inline / delegates to a collaborator — not a bare constant or value.')
            ->leaveWhen('the arms map to constants/values (that belongs ON the enum as a method — PreferTypeMethodOverInlineDispatch), there are only a few arms (inline is more readable than N files), the dispatch is already a method call on the type, or the arms share no common shape so a strategy interface would not fit.')
            ->whenUnsure('if adding a case means widening this match (and a sibling one) every time, and each arm is real logic with collaborators, extract one strategy object per case behind an `apply(...)`-style interface, and home the registration in a dedicated injected provider (`for($key): Strategy`) — not an inline map method on this class. If the arms are just values, push a method onto the enum instead.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A wide `match` on an enum where every arm DOES something — calls methods,
branches, reaches for collaborators — is a strategy table written inline. Each
new case widens the match (often a second sibling match too), and no case can be
tested on its own. Extract one object per case behind a small interface, keyed in
a registration map: adding a case becomes "a new class + one map entry".

Bad — a wide behavioural match (and a sibling one in `default`):
    private function applyEffect(NodeDescriptor $d, SocketEffect $rule, PickedValue $p): NodeDescriptor
    {
        return match ($rule->effect) {
            PickEffect::ResourceToken => $d->retypeInput($rule->port, WireType::resource($p->raw)->toToken()),
            PickEffect::PickedType    => $p->raw === WireType::MIXED ? $d : $d->retypeInput($rule->port, $p->raw),
            PickEffect::SchemaToken   => $this->objects->fieldsFor($p->raw)->isEmpty() ? $d : $d->retypeOutput(...),
            // … 4 more …
            default => $this->applyModelEffect($d, $rule, $p->modelClass),   // a SECOND wide match
        };
    }

Good — one applicator per case behind an interface, registered in a DEDICATED
injected provider (not an inline map method — that just reshapes the wide table
in place, leaving the construction + every strategy's deps on the original class):
    interface SocketEffectApplicator {
        public function apply(NodeDescriptor $d, SocketEffect $rule, PickedValue $p): NodeDescriptor;
    }
    final class ResourceTokenEffect implements SocketEffectApplicator { /* one effect's rewrite */ }
    // … one class per case …

    final class SocketEffectApplicators            // the registration lives HERE
    {
        /** @var array<string, SocketEffectApplicator> */
        private readonly array $applicators;
        public function __construct(SchemaTypeRegistry $objects) {
            $this->applicators = [
                PickEffect::ResourceToken->value => new ResourceTokenEffect,
                PickEffect::SchemaToken->value   => new SchemaTokenEffect($objects),
                // …
            ];
        }
        public function for(PickEffect $e): SocketEffectApplicator { return $this->applicators[$e->value]; }
    }

    // original class: inject the provider, delegate — owns no map, no strategy deps:
    private function applyEffect(NodeDescriptor $d, SocketEffect $rule, PickedValue $p): NodeDescriptor
    {
        return $this->effectApplicators->for($rule->effect)->apply($d, $rule, $p);
    }

NAMING — the provider is a TOTAL keyed lookup (`for($key): Strategy`,
return-or-throw over the closed keyspace). Name it for that: `XApplicators` /
`XStrategies` / a neutral `XMap`. NOT `*Resolver` (that is first-match kernel
dispatch — ResolverNamingHonesty would flag it) and NOT `*Factory` (overstates if
it hands back shared stateless strategies rather than building per call).

WHAT FIRES — a `match`/`switch` whose arms label ≥ 5 cases of ONE closed-set enum,
where the arm bodies are BEHAVIOUR: they call methods (`$this->…()`, `$x->…()`),
branch (inline ternaries), or `new` something. Wide enough that a strategy table
pays for itself.

WHAT DOES NOT — a value/constant map (`Case => 1`, `Case => OtherEnum::X`: push a
method onto the enum — PreferTypeMethodOverInlineDispatch); a small match (< 5
arms); a `match (true)` / non-enum subject; a dispatch already on the type
(`$x->method()`); or arms with no shared shape (a strategy interface needs a
common `apply(...)`). Advisory — extraction is a design call (naming, DI). Not
auto-fixable.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        // Don't flag a dispatch that lives INSIDE the enum's own file — that is
        // where value-maps belong, and behavioural ones there are already local.
        if ($this->declaresEnum($ast)) {
            return $this->righteous();
        }

        $finder = new NodeFinder;
        $minArms = $this->minArms();
        $warnings = [];

        foreach ($finder->findInstanceOf($ast, Expr\Match_::class) as $match) {
            $conds = $this->armConditions($match);
            $enum = $this->dispatchedEnum($conds);

            // Count only the ENUM-CASE arms toward the threshold — a `null`/`default`
            // arm (the "unmatched"/nullable-enum case) is not per-case behaviour.
            if ($enum === null || $this->enumCaseCount($conds) < $minArms) {
                continue;
            }

            if (! $this->armsAreBehaviour($this->matchArmBodies($match), $finder)) {
                continue;
            }

            $this->flag($match->getStartLine(), $enum, $content, $warnings);
        }

        foreach ($finder->findInstanceOf($ast, Node\Stmt\Switch_::class) as $switch) {
            $conds = array_values(array_filter(array_map(static fn (Node\Stmt\Case_ $c): ?Expr => $c->cond, $switch->cases)));
            $enum = $this->dispatchedEnum($conds);

            if ($enum === null || $this->enumCaseCount($conds) < $minArms) {
                continue;
            }

            $bodies = [];

            foreach ($switch->cases as $case) {
                $bodies = array_merge($bodies, $case->stmts);
            }

            if (! $this->armsAreBehaviour($bodies, $finder)) {
                continue;
            }

            $this->flag($switch->getStartLine(), $enum, $content, $warnings);
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * @param  list<\JesseGall\CodeCommandments\Results\Warning>  $warnings
     */
    private function flag(int $line, string $enum, string $content, array &$warnings): void
    {
        $warnings[] = $this->warningAt(
            $line,
            sprintf('This wide per-case dispatch on `%s` is behaviour, not a value map — consider one strategy object per case behind an `apply(...)`-style interface, so adding a case is a new class + one map entry instead of widening this match. Put the case→strategy registration in a DEDICATED injected provider class exposing a single `for($key): Strategy` lookup (a `%sApplicators`/`%sStrategies`/`%sMap` — NOT a `*Resolver`, which is first-match dispatch, nor a `*Factory`) — don\'t leave an inline map/builder method on this class, which only reshapes the wide dispatch in place.', $enum, $enum, $enum, $enum),
            $this->lineAt($content, $line),
            'behavioural-enum-dispatch:' . $enum,
        );
    }

    /**
     * The short name of the single enum all the ENUM-CASE arms label (>= 2
     * distinct `Enum::Case` of the SAME enum), or null when the subject is not
     * one enum. A `null =>` arm (#93: the nullable-enum/unmatched case, produced
     * by `Enum::tryFrom(...)` / `detect(...)->getOr(null)`) is SKIPPED, not a
     * disqualifier — the subject's own type is irrelevant; the case labels
     * identify the enum.
     *
     * @param  list<Expr>  $conds
     */
    private function dispatchedEnum(array $conds): ?string
    {
        $enum = null;
        $cases = 0;

        foreach ($conds as $cond) {
            if ($this->isNullConst($cond)) {
                continue; // the `null =>` / unmatched arm — not a case
            }

            if (! $cond instanceof Expr\ClassConstFetch || ! $cond->class instanceof Node\Name
                || ! $cond->name instanceof Node\Identifier
            ) {
                return null; // a non-enum-case literal/guard — not a closed-set enum map
            }

            $short = $cond->class->getLast();

            if ($enum !== null && $short !== $enum) {
                return null; // arms span more than one enum
            }

            $enum = $short;
            $cases++;
        }

        return ($enum !== null && $cases >= 2) ? $enum : null;
    }

    /**
     * The number of ENUM-CASE arm conditions (excluding a `null` arm), for the
     * threshold so it reflects real per-case behaviour.
     *
     * @param  list<Expr>  $conds
     */
    private function enumCaseCount(array $conds): int
    {
        return count(array_filter($conds, fn (Expr $c): bool => ! $this->isNullConst($c)));
    }

    private function isNullConst(Expr $expr): bool
    {
        return $expr instanceof Expr\ConstFetch
            && $expr->name instanceof Node\Name
            && strtolower($expr->name->toString()) === 'null';
    }

    /**
     * Arm CONDITIONS of a match (a multi-condition arm contributes each; a
     * `default` arm has no condition and is skipped — but still counted in armCount).
     *
     * @return list<Expr>
     */
    private function armConditions(Expr\Match_ $match): array
    {
        $conds = [];

        foreach ($match->arms as $arm) {
            foreach ($arm->conds ?? [] as $cond) {
                $conds[] = $cond;
            }
        }

        return $conds;
    }

    /**
     * @return list<Node>
     */
    private function matchArmBodies(Expr\Match_ $match): array
    {
        return array_map(static fn (Node\MatchArm $arm): Node => $arm->body, $match->arms);
    }

    /**
     * Whether the arm bodies are BEHAVIOUR — at least one calls a method/static/
     * new or branches (inline ternary). A set of bare constants/enum-cases/
     * scalars is a value map (PreferTypeMethod's job), not behaviour.
     *
     * @param  list<Node>  $bodies
     */
    private function armsAreBehaviour(array $bodies, NodeFinder $finder): bool
    {
        foreach ($bodies as $body) {
            $hit = $finder->findFirst([$body], static fn (Node $n): bool =>
                $n instanceof Expr\MethodCall
                || $n instanceof Expr\NullsafeMethodCall
                || $n instanceof Expr\StaticCall
                || $n instanceof Expr\New_
                || $n instanceof Expr\Ternary);

            if ($hit !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<Node>  $ast
     */
    private function declaresEnum(array $ast): bool
    {
        return (new NodeFinder)->findFirst($ast, static fn (Node $n): bool => $n instanceof Node\Stmt\Enum_) !== null;
    }

    private function minArms(): int
    {
        $min = (int) $this->config('min_arms', self::DEFAULT_MIN_ARMS);

        return $min > 0 ? $min : self::DEFAULT_MIN_ARMS;
    }

    private function lineAt(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return trim($lines[$line - 1] ?? '');
    }
}
