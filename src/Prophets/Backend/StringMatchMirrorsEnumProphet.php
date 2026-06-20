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
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Flag a `match`/`switch` over STRING literals whose arm-set is EXACTLY a backed
 * enum's value-set — a stringly-typed dispatch mirroring an enum that already
 * exists. The cross-artifact congruence sibling of {@see PreferConfigDrivenRegistryProphet}
 * (config↔enum): here it is match-arms↔enum-cases.
 *
 * The same closed set then lives in two places (the enum's cases AND these string
 * arms), so adding a member means editing both, and the dispatch loses the enum's
 * exhaustiveness + type safety. Type the subject as the enum and match the enum's
 * cases instead. ADVISORY (a WARNING). GENERIC: pure AST + the enum index, no name
 * lists.
 */
#[IntroducedIn('2.15.0')]
class StringMatchMirrorsEnumProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    private ?CodebaseIndex $index = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }

    public function description(): string
    {
        return 'A match/switch over strings that mirror an enum\'s cases should dispatch on the enum';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A `match`/`switch` whose arm conditions are STRING literals has a set '
                . 'EXACTLY equal to the backed values of an existing enum, and the subject '
                . 'is a raw string (not the enum). The closed set is hardcoded twice.'
            )
            ->leaveWhen(
                'the subject is already the enum (or its `->value`); the string set is only '
                . 'a PARTIAL overlap with the enum; the arms are not all literals; or no '
                . 'enum with that exact value-set exists.'
            )
            ->whenUnsure(
                'type the subject as the enum (`Foo::from($string)` at the boundary, or '
                . 'accept `Foo`) and `match ($foo) { Foo::Bar => … }` — you gain '
                . 'exhaustiveness and type safety, and the set lives in ONE place (the enum).'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A `match`/`switch` over string literals that are EXACTLY an enum's backed values is
stringly-typed dispatch shadowing an enum that already exists. The closed set is
declared twice — the enum's cases and these arms — so they drift independently, and
the dispatch loses exhaustiveness checking and type safety.

Bad — a string match mirroring `enum Suit: string { Hearts='hearts'; Spades='spades'; }`:
    match ($suitString) {
        'hearts'  => …,
        'spades'  => …,
    };

Good — dispatch on the enum:
    match (Suit::from($suitString)) {
        Suit::Hearts => …,
        Suit::Spades => …,
    };

WHAT FIRES — a `match`/`switch` whose arm conditions are ALL string literals (a
default arm aside), the literal set has >= 2 members and EXACTLY equals the backed
values of some `enum: string`, and the subject is not that enum.

WHAT DOES NOT — partial overlap, non-literal arms, a default-only match, a subject
already typed as the enum, or no enum with that value-set. Advisory (a WARNING);
not auto-fixable.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null || $this->index === null) {
            return $this->righteous();
        }

        $finder = new NodeFinder;
        $warnings = [];

        foreach ($finder->findInstanceOf($ast, Node\Expr\Match_::class) as $match) {
            $strings = $this->armStringSet($match->arms);

            if ($strings === null) {
                continue;
            }

            $enum = $this->enumWithValueSet($strings);

            if ($enum !== null && ! $this->subjectIsEnum($match->cond, $enum)) {
                $warnings[] = $this->warn($match->getStartLine(), $strings, $enum);
            }
        }

        foreach ($finder->findInstanceOf($ast, Node\Stmt\Switch_::class) as $switch) {
            $strings = $this->caseStringSet($switch->cases);

            if ($strings === null) {
                continue;
            }

            $enum = $this->enumWithValueSet($strings);

            if ($enum !== null && ! $this->subjectIsEnum($switch->cond, $enum)) {
                $warnings[] = $this->warn($switch->getStartLine(), $strings, $enum);
            }
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * The set of string-literal arm conditions, or null if any non-default arm has a
     * non-string-literal condition (mixed → not a pure stringly dispatch).
     *
     * @param  list<Node\MatchArm>  $arms
     * @return list<string>|null
     */
    private function armStringSet(array $arms): ?array
    {
        $strings = [];

        foreach ($arms as $arm) {
            if ($arm->conds === null) {
                continue; // default arm
            }

            foreach ($arm->conds as $cond) {
                if (! $cond instanceof Node\Scalar\String_) {
                    return null;
                }

                $strings[$cond->value] = true;
            }
        }

        return count($strings) >= 2 ? array_keys($strings) : null;
    }

    /**
     * @param  list<Node\Stmt\Case_>  $cases
     * @return list<string>|null
     */
    private function caseStringSet(array $cases): ?array
    {
        $strings = [];

        foreach ($cases as $case) {
            if ($case->cond === null) {
                continue; // default
            }

            if (! $case->cond instanceof Node\Scalar\String_) {
                return null;
            }

            $strings[$case->cond->value] = true;
        }

        return count($strings) >= 2 ? array_keys($strings) : null;
    }

    /**
     * The FQCN of a backed (string) enum whose value-set EXACTLY equals $strings, or null.
     *
     * @param  list<string>  $strings
     */
    private function enumWithValueSet(array $strings): ?string
    {
        $want = $this->normalise($strings);

        foreach ($this->index?->allEnums() ?? [] as $enum) {
            if ($enum->backing !== 'string') {
                continue;
            }

            // EnumSummary::cases is keyed by backing value for backed enums.
            if ($this->normalise(array_keys($enum->cases)) === $want) {
                return $enum->fqcn;
            }
        }

        return null;
    }

    /** Whether the match subject is plainly the enum already (an enum case / ->from / typed). */
    private function subjectIsEnum(Node $subject, string $enumFqcn): bool
    {
        $short = $this->shortName($enumFqcn);

        // `Enum::from($x)`, `Enum::tryFrom($x)`, `Enum::Case` — already enum-typed.
        if ($subject instanceof Node\Expr\StaticCall && $subject->class instanceof Node\Name && $subject->class->getLast() === $short) {
            return true;
        }

        return $subject instanceof Node\Expr\ClassConstFetch
            && $subject->class instanceof Node\Name
            && $subject->class->getLast() === $short;
    }

    /**
     * @param  list<string>  $values
     */
    private function warn(int $line, array $values, string $enumFqcn): \JesseGall\CodeCommandments\Results\Warning
    {
        $short = $this->shortName($enumFqcn);

        return $this->warningAt(
            $line,
            sprintf(
                'This match/switch dispatches on STRING literals {%s} that are EXACTLY the backed values of enum `%s`. The closed set is hardcoded twice (the enum cases AND these arms) — they drift independently, and the dispatch loses exhaustiveness + type safety. Type the subject as `%s` (e.g. `%s::from($string)` at the boundary) and match its cases instead, so the set lives in ONE place.',
                implode(', ', $values),
                $enumFqcn,
                $short,
                $short,
            ),
            null,
            'string-match-mirrors-enum:' . $short,
        );
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function normalise(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts) ?: $fqcn;
    }
}
