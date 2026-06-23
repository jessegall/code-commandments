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
 * Flag the THIRD kind of misused absence (#96/#97): an "impossible-by-design"
 * none. A `match` dispatches a closed-set enum, every real case yields a value,
 * and the ONLY source of `null` / `Option::none()` is the `default =>` / `null =>`
 * fallthrough — so the absence can only occur for an UNHANDLED case (you added an
 * enum case and forgot to wire it). Modelling that as `?T` / `Option<T>` silently
 * swallows a programming error and makes every caller null-guard a state that
 * should have crashed.
 *
 * The inverse of {@see PreferOptionOverNullProphet}: that one pushes a GENUINE
 * domain absence toward Option; this one says the invariant-violation absence
 * should THROW a named exception (or drop the `default` so a new case is a match
 * error). Reserve Option for absence that is possible from valid input.
 *
 * Advisory, never a sin; not auto-fixable (the exception type is a design call).
 */
#[IntroducedIn('1.133.0')]
class ThrowOnUnhandledCaseProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Throw a named exception for an unhandled closed-set case — do not model an invariant violation as null/Option';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Correctness;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A `match` over a closed-set enum has a `default`/`null` arm that returns `null` or `Option::none()`, while EVERY real case arm yields a value — so the none can only happen for an unhandled case (a forgotten wiring). That is an invariant violation modelled as absence.')
            ->leaveWhen('the absence is genuinely possible from valid input — an optional lookup, untrusted external data, a real domain "not found". Then Option / Null Object is correct (PreferOptionOverNull\'s case), not a throw.')
            ->whenUnsure('ask what the none MEANS: "we forgot to handle this case" → throw a named exception (or drop the `default` so an added case is a compile-time match error). "there may legitimately be no value" → keep Option. Never let a should-crash bug become a silent empty value.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
There are three kinds of "absence" behind a `?T` / `Option<T>`:
  1. GENUINE domain absence (a lookup miss, an optional) — Option/Null Object is
     right; PreferOptionOverNull pushes here.
  2. NO absence at all — the value is always produced; the Option is ceremony —
     NoOptionOveruse catches this.
  3. IMPOSSIBLE / invariant-violation absence — the none can only happen if WE
     made a mistake (an unhandled enum case, an unregistered handler). Modelling
     it as null/Option SILENTLY SWALLOWS A BUG. THROW instead. ← this rule.

Bad — a forgotten case becomes a silent null:
    public function rendererFor(NodeType $type): ?Renderer
    {
        return match ($type) {
            NodeType::A => new RendererA(),
            NodeType::B => new RendererB(),
            default     => null,              // add NodeType::C, forget it → silent null
        };
    }

Good — exhaustive (a new case is a compile-time match error):
    public function rendererFor(NodeType $type): Renderer
    {
        return match ($type) {
            NodeType::A => new RendererA(),
            NodeType::B => new RendererB(),
            NodeType::C => new RendererC(),
        };
    }

Good — or throw a named exception on the impossible arm:
    default => throw UnhandledNodeType::for($type),

WHAT FIRES — a `match` whose arms label >= 2 cases of ONE backed enum, whose
`default`/`null` arm returns `null` or `Option::none()`/`self::none()`, and whose
EVERY enum-case arm yields a value (not none). The none exists only for the
unhandled case.

WHAT DOES NOT — a `default` that returns a real VALUE (a legitimate fallback); a
case arm that itself yields null/none (then the absence is genuine, not just the
fallthrough — PreferOptionOverNull's domain); a `match (true)` / non-enum subject;
or a value/constant map. Advisory — confirm the none really is "impossible" before
turning it into a throw.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $finder = new NodeFinder;
        $warnings = [];

        foreach ($finder->findInstanceOf($ast, Expr\Match_::class) as $match) {
            $enum = $this->dispatchedEnum($match);

            if ($enum === null) {
                continue;
            }

            // `match ($this)` over `self::CASE` reports the enum as "self"/"static"
            // — resolve it to the enclosing type's name for a readable message.
            if (in_array(strtolower($enum), ['self', 'static'], true)) {
                $enum = $this->enclosingTypeName($match, $ast, $finder) ?? $enum;
            }

            if (! $this->onlyFallthroughIsNone($match)) {
                continue;
            }

            $line = $match->getStartLine();
            $warnings[] = $this->warningAt(
                $line,
                sprintf('This `match` on `%s` returns null/none only from its `default`/`null` arm while every real case yields a value — the absence can only mean an UNHANDLED case (a forgotten wiring), an invariant violation modelled as absence. Throw a named exception (e.g. `Unhandled%s::for($case)`) or drop the `default` so an added case is a compile-time match error. Reserve Option/null for genuine domain absence.', $enum, $enum),
                $this->lineSnippet($content, $line),
                'unhandled-case:' . $enum,
            );
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * The short name of the single backed enum all the enum-case arms label
     * (>= 2 distinct `Enum::Case` of one enum), or null. A `null`/`default` arm
     * is skipped, not a disqualifier.
     */
    private function dispatchedEnum(Expr\Match_ $match): ?string
    {
        $enum = null;
        $cases = 0;

        foreach ($match->arms as $arm) {
            foreach ($arm->conds ?? [] as $cond) {
                if ($this->isNullConst($cond)) {
                    continue;
                }

                if (! $cond instanceof Expr\ClassConstFetch || ! $cond->class instanceof Node\Name
                    || ! $cond->name instanceof Node\Identifier
                ) {
                    return null; // a non-enum-case literal/guard — not a closed enum dispatch
                }

                $short = $cond->class->getLast();

                if ($enum !== null && $short !== $enum) {
                    return null;
                }

                $enum = $short;
                $cases++;
            }
        }

        return ($enum !== null && $cases >= 2) ? $enum : null;
    }

    /**
     * Whether the ONLY none-producing arm is the fallthrough (`default =>` or
     * `null =>`): that arm yields a none, AND every enum-case arm yields a value.
     */
    private function onlyFallthroughIsNone(Expr\Match_ $match): bool
    {
        $fallthroughIsNone = false;

        foreach ($match->arms as $arm) {
            $isFallthrough = $arm->conds === null
                || $arm->conds === []
                || (count($arm->conds) === 1 && $this->isNullConst($arm->conds[0]));

            $bodyIsNone = $this->isNoneExpr($arm->body);

            if ($isFallthrough) {
                if (! $bodyIsNone) {
                    return false; // default returns a real value — a legit fallback, not absence
                }

                $fallthroughIsNone = true;

                continue;
            }

            if ($bodyIsNone) {
                return false; // a real case yields none → genuine absence, not just the fallthrough
            }
        }

        return $fallthroughIsNone;
    }

    /**
     * Whether $expr is a "none": the `null` literal, or a no-arg `X::none()`
     * (Option::none() / self::none() / static::none()).
     */
    private function isNoneExpr(Node $expr): bool
    {
        if ($this->isNullConst($expr)) {
            return true;
        }

        return $expr instanceof Expr\StaticCall
            && $expr->name instanceof Node\Identifier
            && strtolower($expr->name->toString()) === 'none'
            && $expr->args === [];
    }

    private function isNullConst(Node $expr): bool
    {
        return $expr instanceof Expr\ConstFetch
            && $expr->name instanceof Node\Name
            && strtolower($expr->name->toString()) === 'null';
    }

    /**
     * @param  array<Node>  $ast
     */
    private function enclosingTypeName(Node $node, array $ast, NodeFinder $finder): ?string
    {
        $pos = (int) $node->getStartFilePos();
        $best = null;
        $bestStart = -1;

        foreach ($finder->findInstanceOf($ast, Node\Stmt\ClassLike::class) as $type) {
            $start = (int) $type->getStartFilePos();

            if ($type->name !== null && $start <= $pos && (int) $type->getEndFilePos() >= $pos && $start > $bestStart) {
                $best = $type->name->toString();
                $bestStart = $start;
            }
        }

        return $best;
    }

}
