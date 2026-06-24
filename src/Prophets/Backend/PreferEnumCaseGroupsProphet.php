<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\CallGraph\EnumCaseGroup;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Flag a recognisable subset of an enum's cases inlined as an array literal
 * when that same group is duplicated elsewhere in the codebase — it should
 * live as a named accessor on the enum, not be re-inlined.
 *
 *
 *
 * @method-generated-start
 * @method static excludeEnums(array $value)
 * @method static minGroup(int $value)
 * @method static minReuse(int $value)
 * @method-generated-end
 */
#[IntroducedIn('1.54.0')]
class PreferEnumCaseGroupsProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    private const DEFAULT_MIN_GROUP = 3;

    private const DEFAULT_MIN_REUSE = 1;

    private ?CodebaseIndex $index = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function description(): string
    {
        return 'Name reused subsets of an enum on the enum — do not re-inline the same case-group';
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A recognisable subset of an enum\'s cases is inlined as an array '
                . 'and the group has a clear name (numeric, textual, terminal, '
                . 'editable…). The inline list is a named concept in disguise; it '
                . 'belongs on the enum. Reuse makes it worse — every copy can drift '
                . 'independently — but even a single inline group reads better named.'
            )
            ->leaveWhen(
                'The array is a genuine one-off, or the grouping carries no '
                . 'meaningful name — an arbitrary ad-hoc selection that would never '
                . 'read well as a method on the enum.'
            )
            ->whenUnsure(
                'Leave it. Only promote a group to a named accessor when you can '
                . 'give it an honest name and it is actually reused.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
When a recognisable SUBSET of an enum's cases is inlined as an array
literal it is a named concept in disguise. Give it a name ON the enum
and call that, instead of inlining the subset where it is needed. If the
same group is also inlined elsewhere the case is stronger still — every
copy can drift independently — but a single inline group is enough.

Bad — two named groups, inlined by hand:

    $operators = $numeric
        ? [CompareOperator::Equals, CompareOperator::NotEquals, CompareOperator::GreaterThan,
           CompareOperator::GreaterOrEqual, CompareOperator::LessThan, CompareOperator::LessOrEqual]
        : [CompareOperator::Equals, CompareOperator::NotEquals, CompareOperator::StartsWith,
           CompareOperator::Contains, CompareOperator::EndsWith];

Good — each group is named once, on the enum:

    enum CompareOperator
    {
        // …cases…

        /** @return list<self> */
        public static function numeric(): array
        {
            return [self::Equals, self::NotEquals, self::GreaterThan,
                    self::GreaterOrEqual, self::LessThan, self::LessOrEqual];
        }

        /** @return list<self> */
        public static function textual(): array
        {
            return [self::Equals, self::NotEquals, self::StartsWith,
                    self::Contains, self::EndsWith];
        }
    }

    $operators = $numeric ? CompareOperator::numeric() : CompareOperator::textual();

WHAT FIRES — an `[...]` array literal is flagged when ALL hold:

  1. It has 3 or more items (configurable `min_group`, default 3), and
     every item is a plain enum-case fetch (`Enum::Case`) of the SAME
     enum. A mix of enums, or any non-case item, disqualifies it.
  2. The group occurs at least `min_reuse` times across the codebase
     (default 1 — a single inline group is flagged on sight). Raise
     `min_reuse` to 2+ to only flag groups that are actually duplicated.
     The group is canonicalised as the sorted, de-duplicated set of
     `EnumFqcn::CaseName` strings, so order and repetition inside the
     array don't matter, and the finding reports how many sites repeat it.
  3. It is NOT the haystack (2nd argument) of `in_array(...)` /
     `array_search(...)` — that one-of membership test belongs to the
     CompareSelf `equalsAny` rule; this rule must not double-flag it.
  4. It is NOT inside the enum's own file — that is exactly where the
     named-group accessor would live.

The rule is LOCAL: a nameable inline group is flagged with or without a
codebase index, so it fires under `--file` too. The index, when built
(always from the FULL scroll, even under `--file`/`--git`/`--staged`),
only enriches the finding with how many other sites repeat the group —
and is required if you raise `min_reuse` above 1.

The fix is not auto-fixable: the group's NAME is semantic and can't be
inferred. The prophet points at the group; you name it.

Configure via:

    Backend\PreferEnumCaseGroupsProphet::class => [
        'min_group' => 3,        // smallest inline group worth a name
        'min_reuse' => 1,        // sites required before flagging (1 = on sight)
        'exclude_enums' => [     // enums whose groups never get flagged
            // 'App\\Support\\SomeFlagEnum',
        ],
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        // The rule is local: a nameable inline group of enum cases is flagged
        // on sight, with or without a codebase index. The index, when present,
        // only enriches the finding with how many OTHER sites repeat the group.
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $minGroup = $this->minGroup();
        $minReuse = $this->minReuse();
        $excluded = $this->excludedEnums();

        $finder = new NodeFinder;
        $warnings = [];
        $seenLines = [];

        foreach ($this->namespaceScopes($ast) as [$namespace, $uses, $scope, $localEnumFqcns]) {
            $needles = $this->membershipNeedles($finder, $scope);

            /** @var array<Expr\Array_> $arrays */
            $arrays = $finder->findInstanceOf($scope, Expr\Array_::class);

            foreach ($arrays as $array) {
                if ($needles->offsetExists($array)) {
                    continue;
                }

                $resolved = EnumCaseGroup::resolve($array, $uses, $namespace, $minGroup);

                if ($resolved === null) {
                    continue;
                }

                // Exclusion 4: the enum's own file is where the accessor lives.
                // A `self::`/`static::` group can only appear inside the very
                // enum it names — that is the home for the accessor, never a
                // duplicate. (It resolves to a pseudo-FQCN like `Ns\self`, so
                // it would otherwise dodge the localEnumFqcns check below.)
                $shortEnum = $this->shortName($resolved['fqcn']);

                if ($shortEnum === 'self' || $shortEnum === 'static') {
                    continue;
                }

                if (isset($localEnumFqcns[$resolved['fqcn']])) {
                    continue;
                }

                if (in_array($resolved['fqcn'], $excluded, true)) {
                    continue;
                }

                $key = EnumCaseGroup::canonicalKey($resolved);
                // The group always occupies at least this one site; the index
                // (when built) knows about repeats elsewhere.
                $count = max(1, $this->index?->enumCaseGroupCount($key) ?? 1);

                if ($count < $minReuse) {
                    continue;
                }

                $line = $array->getStartLine();

                // De-dupe: one finding per array literal.
                if (isset($seenLines[$line . ':' . $key])) {
                    continue;
                }

                $seenLines[$line . ':' . $key] = true;
                $warnings[] = $this->warningFor($resolved, $count, $line);
            }
        }

        if ($warnings === []) {
            return $this->righteous();
        }

        return Judgment::withWarnings($warnings);
    }

    /**
     * @param  array{fqcn: string, cases: list<string>}  $resolved
     */
    private function warningFor(array $resolved, int $count, int $line): Warning
    {
        $short = $this->shortName($resolved['fqcn']);
        $caseCount = count(array_unique($resolved['cases']));
        $caseList = implode(', ', array_map(
            fn (string $case): string => $short . '::' . $case,
            array_values(array_unique($resolved['cases'])),
        ));

        $reuseNote = $count >= 2
            ? sprintf(' (the same group is inlined in %d sites)', $count)
            : '';

        $message = sprintf(
            'Inline group of %d %s cases [%s] is a named concept in disguise%s — give it a name on the enum '
            . '(e.g. `%s::someGroup(): array`) and call that instead of inlining the subset.',
            $caseCount,
            $short,
            $caseList,
            $reuseNote,
            $short,
        );

        $symbol = EnumCaseGroup::canonicalKey($resolved);

        return $this->warningAt($line, $message, null, $symbol);
    }

    /**
     * Split the AST into namespace scopes, each carrying its use-map and the
     * FQCNs of enums defined directly in it.
     *
     * @param  array<Node>  $ast
     * @return list<array{0: ?string, 1: array<string, string>, 2: array<Node>, 3: array<string, true>}>
     */
    private function namespaceScopes(array $ast): array
    {
        $out = [];

        foreach ($ast as $node) {
            $namespace = null;
            $scope = [$node];

            if ($node instanceof Node\Stmt\Namespace_) {
                $namespace = $node->name?->toString();
                $scope = $node->stmts;
            }

            $uses = $this->collectUses($scope);
            $localEnumFqcns = [];

            foreach ($scope as $stmt) {
                if ($stmt instanceof Node\Stmt\Enum_ && $stmt->name !== null) {
                    $short = $stmt->name->toString();
                    $fqcn = $namespace !== null && $namespace !== '' ? $namespace . '\\' . $short : $short;
                    $localEnumFqcns[$fqcn] = true;
                }
            }

            $out[] = [$namespace, $uses, $scope, $localEnumFqcns];
        }

        return $out;
    }

    /**
     * The set of array literals that are membership-test haystacks (and so
     * belong to the CompareSelf rule, not this one).
     *
     * @param  array<Node>  $scope
     */
    private function membershipNeedles(NodeFinder $finder, array $scope): \SplObjectStorage
    {
        $needles = new \SplObjectStorage;

        /** @var array<Expr\FuncCall> $calls */
        $calls = $finder->findInstanceOf($scope, Expr\FuncCall::class);

        foreach ($calls as $call) {
            foreach ($finder->findInstanceOf([$call], Expr\Array_::class) as $array) {
                assert($array instanceof Expr\Array_);

                if (EnumCaseGroup::isMembershipNeedle($array, $call)) {
                    $needles->offsetSet($array, null);
                }
            }
        }

        return $needles;
    }

    /**
     * @param  array<Node>  $stmts
     * @return array<string, string>  short alias => FQCN
     */
    private function collectUses(array $stmts): array
    {
        $uses = [];

        foreach ($stmts as $stmt) {
            if (! $stmt instanceof Node\Stmt\Use_) {
                continue;
            }

            foreach ($stmt->uses as $useUse) {
                $fqcn = $useUse->name->toString();
                $alias = $useUse->alias?->toString() ?? $useUse->name->getLast();
                $uses[$alias] = $fqcn;
            }
        }

        return $uses;
    }

    private function minGroup(): int
    {
        $value = $this->config('min_group', self::DEFAULT_MIN_GROUP);

        return is_numeric($value) ? max(2, (int) $value) : self::DEFAULT_MIN_GROUP;
    }

    private function minReuse(): int
    {
        $value = $this->config('min_reuse', self::DEFAULT_MIN_REUSE);

        return is_numeric($value) ? max(1, (int) $value) : self::DEFAULT_MIN_REUSE;
    }

    /**
     * @return list<string>
     */
    private function excludedEnums(): array
    {
        $value = $this->config('exclude_enums', []);

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts) ?: $fqcn;
    }
}
