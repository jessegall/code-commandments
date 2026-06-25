<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Support\Resolvers\Ast\FileImports;
use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Contracts\ParameterizedRepenter;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;
use JesseGall\CodeCommandments\Results\RepentInput;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;

/**
 * Suggest an enum for a `string`-typed field whose NAME reads as a closed set —
 * `direction`, `status`, `kind`, `mode`, … — even when no literal and
 * no enum exist yet.
 *
 * Its sibling, {@see StringsThatShouldBeEnumsProphet}, is literal-anchored: it
 * needs a string literal (a default, a named arg, a closed call-site set, a
 * match/switch/if) to fire. A bare `public string $direction` on a Data class
 * hydrated from an array offers no such anchor — yet the NAME alone is a strong
 * signal. This prophet fills that gap with a name heuristic, emits an advisory
 * WARNING, and (as a {@see ParameterizedRepenter}) can CREATE the enum and
 * retype the field once you supply the class name and cases:
 *
 *     repent --prophet=PreferEnumForClosedSetField --file=… \
 *         --input create-enum-class=SocketDirection --input cases=input,output
 *
 *
 *
 *
 *
 *
 *
 * @method-generated-start
 * @method static compareSelfTrait(string $value)
 * @method static dataBaseSuffix(string $value)
 * @method static names(array $value)
 * @method-generated-end
 */
#[IntroducedIn('1.91.0')]
class PreferEnumForClosedSetFieldProphet extends PhpCommandment implements ParameterizedRepenter, NeedsCodebaseIndex
{
    private ?CodebaseIndex $index = null;

    /**
     * Field name endings that almost always denote a finite, closed set.
     * Matched case-insensitively at a word boundary (camelCase or snake_case),
     * so `sortDirection` and `node_status` match but `prototype` does not.
     *
     * `type` is deliberately NOT here (#204): it is the most overloaded noun in the
     * language — a class-string (`@param class-string $type`), a wire/MIME/resource
     * token (`resource:<x>`, `application/json`), a registry-populated or raw-decode
     * value — far more often than a code-defined closed set, so the bare-name signal
     * is near-useless for it. A genuine closed-set `$type` still gets flagged by the
     * literal-anchored sibling (StringsThatShouldBeEnums) the moment it shows
     * evidence; add `'type'` back via config if your codebase wants the name signal.
     *
     * @var list<string>
     */
    private const DEFAULT_NAMES = [
        'direction', 'status', 'state', 'kind', 'mode', 'level',
        'severity', 'visibility', 'role', 'format', 'strategy', 'operator',
        'phase', 'stage', 'category', 'variant', 'priority', 'alignment',
        'orientation', 'unit', 'period', 'scope', 'tier',
    ];

    /** @var array<string, string> */
    private array $repentInput = [];

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }

    public function description(): string
    {
        return 'Suggest an enum for a string field whose name denotes a closed set';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A `string`-typed field whose name denotes a closed set '
                . '(`$direction`, `$status`, `$kind`, `$mode`, …) — a value '
                . 'with a known, finite set of cases that is currently stringly-typed. '
                . '(`type` is excluded by default — too overloaded; #204.)'
            )
            ->leaveWhen(
                'The value is genuinely open free text that merely shares the name '
                . '(a `$type` holding an arbitrary MIME string, a `$format` holding a '
                . 'user-supplied pattern).'
            )
            ->whenUnsure(
                'Ask "is the set of valid values finite and known?" If yes, it is an '
                . 'enum. Reuse an existing enum only if the field is genuinely the '
                . 'SAME concept (typically its value already comes from that enum) — '
                . 'not just because values overlap; otherwise create a new, '
                . 'purpose-specific one. If the value is genuinely open, leave it.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A closed-set value — a direction, status, kind, mode — belongs in an
enum, not a `string`. Stringly-typed closed sets bypass static analysis, IDE
refactors, and exhaustive `match`; every consumer re-validates by hand.

The companion rule (StringsThatShouldBeEnums) needs a literal to fire — a
default, a named argument, a closed set of call-site values, a match/switch/if.
A field hydrated from an array (a Spatie Data property, say) has no such anchor:

    class NodeSocketData extends Data
    {
        public function __construct(
            public string $direction,   // ← no default, no literal — invisible to the literal rule
        ) {}
    }

But the NAME is signal enough. This rule flags a `string` field (a class
property or PROMOTED constructor property — not a transient method parameter,
which too often carries a class-string) whose name ends in a closed-set noun
(at a camelCase or snake_case boundary, so `prototype` is not mistaken for
`…type`) and suggests creating a purpose-specific enum.

It is an ADVISORY warning, not a sin: the name is a softer signal than a
literal. A genuinely open `string` that merely shares the name should be left.

REUSE AN EXISTING ENUM, OR CREATE A NEW ONE?

  Reuse one ONLY when the field is genuinely the SAME concept — not merely when
  the values happen to coincide. Two enums can share the strings `'active'` /
  `'archived'` and still mean different things; reusing the wrong one couples
  unrelated concerns and breaks the moment one set evolves. Shared values are
  NOT the same type.

  - Reuse — the field IS that concept, usually because its VALUE ALREADY COMES
    FROM that enum:
        Field::$type            → SchemaFieldType   (a schema field's type IS one)
        WorkflowAiMessageData::$role → AiRole        (hydrated from an AiRole value)
    The strongest tell is the source: if the value is produced by something
    already typed as `EnumX`, the field is an `EnumX`.

  - Create a new, purpose-specific enum — the DEFAULT, and what `repent` does.
    A socket's `$direction` (input/output) is NOT a sort `Direction` (asc/desc)
    even if both could spell `'asc'`-ish tokens; give it its own
    `SocketDirection`. When in doubt, a new enum named for THIS field's concept
    is safer than forcing the value into an unrelated one.

  (Reuse is a human judgement; the auto-fix only ever CREATES a new enum, so it
  never silently couples you to the wrong existing one.)

[AUTO-FIXABLE on Spatie Data classes, needs input] — `repent` can create the
enum and retype the field, but it cannot guess the class name or the cases, so
it asks for them:

    repent --prophet=PreferEnumForClosedSetField --file=path/to/Data.php \
        --input create-enum-class=SocketDirection \
        --input cases=input,output

It generates `enum SocketDirection: string { case Input = 'input'; … }` in the
file's namespace and retypes the field — AND rewrites every same-file usage it
can reach: the default value, `$this->field === '…'` comparisons (→ the enum
case), and `$this->field` read where a string is required (a `.` concat, or
returned — directly or through `?:`/`??` — from a `: string` method) gains
`->value`. Cross-file readers in OTHER files remain the dev's job. To REUSE an
existing enum instead, pass
`--input enum-class=App\Enums\WorkflowRunStatus` (retype only, no creation; the
FQCN is imported for you). A bare short name (`--input enum-class=WorkflowRunStatus`)
is resolved to its FQCN against the codebase index and imported too; if it cannot
be resolved (no match, or an ambiguous short name) the retype still happens but
the penance tells you to add the `use` import by hand. Use `--input field=<name>`
to pick which field when a file has more than one.

The auto-fix always converts THIS file in full — the property, its default, and
every same-file `$this->field` read/compare/assign. On a Spatie Data subclass
that is the whole job: `::from()` bridges string↔enum at the boundary, so
cross-file construction/serialization literals keep working untouched. On a
PLAIN class there is no bridge — cross-file usages (`new X('…')` args and
`$x->field` reads/compares in OTHER files) genuinely need editing, and a
single-file rewrite cannot reach them safely (resolving an arbitrary
`$x->field` to this class needs full type inference). So for a non-Data class
`repent` converts this file AND hands back a precise CHECKLIST of the cross-file
usages to finish by hand — it never silently rewrites another file's `->field`.

WHAT FIRES — a `string` / `?string` typed property or promoted ctor property
whose name matches the configured closed-set list at a word boundary.

WHAT DOES NOT — non-string types, a plain method parameter, a field that
carries ANY attribute (`#[Input]`, `#[Pick*]`, a container binding: it is
hydrated with a raw string regardless of the declared type, so an enum retype
would throw — only Spatie Data `::from()`, on attribute-free promoted props,
casts string→enum), a name not in the list, or — when the list is configured
empty — nothing.

Configure via:

    Backend\PreferEnumForClosedSetFieldProphet::class => [
        'names' => ['direction', 'status', 'kind', 'mode', /* … */],  // add 'type' here if your codebase wants it (#204)
        'data_base_suffix' => 'Data',  // a parent ending in this → safe to auto-fix
        'compare_self_trait' => 'App\\Support\\Enums\\CompareSelf',  // reused enums using it → emit Case->equals($x)
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $warnings = [];

        foreach ($this->collectFields($ast) as $field) {
            $enum = ucfirst($field['noun']);

            $head = sprintf(
                'The string field `$%s` reads like a closed set (a %s). Stringly-typed closed sets bypass static analysis, IDE refactors, and exhaustive `match`. If its values are a known finite set, model it as a purpose-specific enum (e.g. `enum %s: string { case … }`) and type `$%s` as it.',
                $field['name'],
                $field['noun'],
                $enum,
                $field['name'],
            );

            $autoFixable = true;

            if ($field['isData']) {
                // Spatie Data: ::from() bridges string↔enum both ways, so the
                // retype is a safe, complete auto-fix.
                $tail = sprintf(
                    ' Auto-fixable: `repent --input create-enum-class=%s --input cases=…` creates the enum and retypes the field (or `--input enum-class=ExistingEnum` to reuse one). If it is genuinely open free text, leave it.',
                    $enum,
                );
            } else {
                // Plain class: no ::from() bridge. The auto-fix converts this
                // whole file (property, default, same-file reads/compares), but
                // cross-file usages cannot be reached — `repent` returns a precise
                // checklist for those.
                $tail = sprintf(
                    ' Auto-fixable IN-FILE: `repent --input create-enum-class=%s --input cases=…` (or `--input enum-class=Existing`) converts this file fully; since this is not a Spatie Data class, `repent` also prints a checklist of cross-file usages (`new …(\'…\')`, `$x->%s` in other files) to finish by hand. If it is genuinely open free text, leave it.',
                    $enum,
                    $field['name'],
                );
            }

            $warnings[] = $this->warningAt(
                $field['line'],
                $head . $tail,
                null,
                "closed-set-field:{$field['name']}",
                $autoFixable,
            );
        }

        if ($warnings === []) {
            return $this->righteous();
        }

        return Judgment::withWarnings($warnings);
    }

    public function repentInputs(): array
    {
        return [
            RepentInput::optional('create-enum-class', 'Name of a NEW enum to create (pair with cases). Omit if reusing one.', 'SocketDirection'),
            RepentInput::optional('cases', 'Comma-separated case values for the NEW enum (pair with create-enum-class)', 'input,output'),
            RepentInput::optional('enum-class', 'OR reuse an EXISTING enum (retype only, no creation) — short name or FQCN', 'App\\Enums\\WorkflowRunStatus'),
            RepentInput::optional('field', 'Which field to convert (when the file has more than one)', 'status'),
        ];
    }

    public function setRepentInput(array $values): void
    {
        $this->repentInput = $values;
    }

    public function canRepent(string $filePath): bool
    {
        return str_ends_with($filePath, '.php');
    }

    public function repent(string $filePath, string $content): RepentanceResult
    {
        if (! $this->canRepent($filePath)) {
            return RepentanceResult::unchanged();
        }

        $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($content);

        if ($ast === null) {
            return RepentanceResult::unrepentant('Unable to parse PHP file');
        }

        $fields = $this->collectFields($ast);

        if ($fields === []) {
            return RepentanceResult::unchanged();
        }

        $target = $this->pickField($fields);

        if ($target === null) {
            $names = implode(', ', array_map(static fn (array $f): string => '$' . $f['name'], $fields));

            return RepentanceResult::unrepentant("More than one closed-set field here ({$names}) — pick one with --input field=<name>");
        }

        // On a Spatie Data class, ::from() bridges string↔enum, so cross-file
        // usages need no rewrite. On a plain class they do — but the single-file
        // pass can't reach OTHER files, so we convert everything in THIS file and
        // hand back a precise cross-file checklist instead of silently breaking.
        $crossFile = $target['isData'] ? [] : [$this->crossFileChecklist($target['name'])];

        $reuse = trim($this->repentInput['enum-class'] ?? '');
        $createName = trim($this->repentInput['create-enum-class'] ?? '');
        $casesRaw = trim($this->repentInput['cases'] ?? '');

        // Reuse path: retype to an existing enum, create nothing.
        if ($reuse !== '') {
            $short = $this->shortClassName($reuse);
            // Resolve the full FQCN once: a bare short name (`--input
            // enum-class=EditorActionType`) is resolved against the codebase
            // index, so both the case-map reflection and the import below see the
            // real class (#191).
            $fqcn = $this->resolveReuseFqcn($reuse);
            $reflectTarget = $fqcn ?? $reuse;

            // A freshly created enum never uses CompareSelf, but a reused one
            // might — emit the `Case->equals($x)` form then, so the rewrite does
            // not leave a SuggestCompareSelfTrait finding behind.
            $content = $this->applyRetype($content, $ast, $target, $short, $this->caseMapForReuse($reflectTarget), $this->enumUsesCompareSelf($reflectTarget));

            $penance = ["Retyped \${$target['name']} to existing enum {$short} (and its same-file usages)", ...$crossFile];

            // Make the retype REFERENCEABLE: import the enum (FQCN given, or a
            // short name resolved via the index) so the file is never left
            // pointing at an unimported class. ensureUse must run AFTER
            // applyRetype — it shifts byte offsets the AST edits rely on.
            [$content, $importNote] = $this->ensureReuseImport($content, $ast, $reuse, $fqcn);

            if ($importNote !== null) {
                $penance[] = $importNote;
            }

            return RepentanceResult::absolved($content, $penance);
        }

        // Create path: generate a new enum, then retype.
        if ($createName === '' || $casesRaw === '') {
            return RepentanceResult::unrepentant(
                'Provide either --input enum-class=<ExistingEnum> (reuse) or --input create-enum-class=<New> --input cases=<a,b,…> (create).'
            );
        }

        $short = $this->shortClassName($createName);
        $cases = $this->parseCases($casesRaw);

        if ($cases === []) {
            return RepentanceResult::unrepentant('No cases parsed from --input cases');
        }

        $namespace = $this->namespaceOf($ast);
        $enumContent = $this->renderEnum($namespace, $short, $cases);
        $enumPath = dirname($filePath) . '/' . $short . '.php';

        $caseMap = [];
        foreach ($cases as $value) {
            $caseMap[$value] = $this->caseName($value);
        }

        $content = $this->applyRetype($content, $ast, $target, $short, $caseMap, false);

        return RepentanceResult::absolved(
            $content,
            ["Created enum {$short} ({$enumPath}) and retyped \${$target['name']} (and its same-file readers)", ...$crossFile],
            createdFiles: [$enumPath => $enumContent],
        );
    }

    /**
     * A precise, actionable checklist for the cross-file usages a single-file
     * rewrite cannot safely reach on a non-Data class.
     */
    private function crossFileChecklist(string $field): string
    {
        return sprintf(
            'CROSS-FILE (not auto-converted — no Spatie Data ::from() bridge here): convert usages of $%s in OTHER files by hand. Construction args `new …(\'<value>\')` and `$x->%s` reads/comparisons → the enum case (string literal) or `->value` (where a string is read). Grep `->%s` and the class\'s constructor calls.',
            $field,
            $field,
            $field,
        );
    }

    /**
     * Splice the field's type node to the enum (`string` → `Short`, `?string` →
     * `?Short`) AND convert its default, if any — a `string` default on an
     * enum-typed property is a fatal. `EnumX::Case->value` loses its `->value`;
     * a bare string literal that matches a case becomes that case. Edits are
     * applied right-to-left so byte offsets stay valid.
     *
     * @param  array<Node>  $ast
     * @param  array{name: string, line: int, noun: string, typeNode: Node, nullable: bool, isData: bool, default: ?Node}  $target
     * @param  array<string, string>  $caseMap  case value → case name
     */
    private function applyRetype(string $content, array $ast, array $target, string $short, array $caseMap, bool $usesCompareSelf): string
    {
        $typeNode = $target['typeNode'];

        $edits = [[
            'start' => $typeNode->getStartFilePos(),
            'end' => $typeNode->getEndFilePos(),
            'text' => ($target['nullable'] ? '?' : '') . $short,
        ]];

        $defaultEdit = $this->defaultEdit($target['default'], $short, $caseMap);

        if ($defaultEdit !== null) {
            $edits[] = $defaultEdit;
        }

        // Convert every same-file reader of $this-><field> that the single-file
        // rewrite can reach (cross-file readers remain the dev's job).
        $edits = [...$edits, ...$this->readerEdits($content, $ast, $target, $short, $caseMap, $usesCompareSelf)];

        usort($edits, static fn (array $a, array $b): int => $b['start'] <=> $a['start']);

        foreach ($edits as $edit) {
            $content = substr($content, 0, $edit['start']) . $edit['text'] . substr($content, $edit['end'] + 1);
        }

        return $content;
    }

    /**
     * Edits that convert in-file `$this-><field>` usages to match its new enum
     * type, deterministically:
     *  - `$this->f === '<caseValue>'` / `!==`     → `… === Short::Case`
     *  - `$this->f = '<caseValue>'`               → `$this->f = Short::Case`
     *  - `$this->f` where a string is required    → `$this->f->value`
     *    (a `.` concat operand, or returned — directly or through `?:` / `??`
     *    — from a method whose return type mentions `string`)
     *
     * @param  array<Node>  $ast
     * @param  array{name: string, line: int, noun: string, typeNode: Node, nullable: bool, isData: bool, default: ?Node}  $target
     * @param  array<string, string>  $caseMap
     * @return list<array{start: int, end: int, text: string}>
     */
    private function readerEdits(string $content, array $ast, array $target, string $short, array $caseMap, bool $usesCompareSelf): array
    {
        $traverser = new NodeTraverser;
        $traverser->addVisitor(new ParentConnectingVisitor);
        $traverser->traverse($ast);

        $field = $target['name'];
        $edits = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Expr\PropertyFetch::class) as $fetch) {
            if (! $this->isThisProperty($fetch, $field)) {
                continue;
            }

            $parent = $fetch->getAttribute('parent');

            // Comparison against a string literal → compare against the case.
            if (($parent instanceof Node\Expr\BinaryOp\Identical || $parent instanceof Node\Expr\BinaryOp\NotIdentical)) {
                $other = $parent->left === $fetch ? $parent->right : $parent->left;

                if ($other instanceof Node\Scalar\String_) {
                    $case = $this->caseFor($other->value, $caseMap);

                    if ($usesCompareSelf) {
                        // Emit the case-anchored form SuggestCompareSelfTrait wants,
                        // so the rewrite is self-consistent.
                        $fieldSource = substr($content, $fetch->getStartFilePos(), $fetch->getEndFilePos() - $fetch->getStartFilePos() + 1);
                        $not = $parent instanceof Node\Expr\BinaryOp\NotIdentical ? '! ' : '';
                        $edits[] = ['start' => $parent->getStartFilePos(), 'end' => $parent->getEndFilePos(), 'text' => "{$not}{$short}::{$case}->equals({$fieldSource})"];
                    } else {
                        $edits[] = ['start' => $other->getStartFilePos(), 'end' => $other->getEndFilePos(), 'text' => "{$short}::{$case}"];
                    }
                }

                continue;
            }

            // Assignment of a string literal → assign the case.
            if ($parent instanceof Node\Expr\Assign && $parent->var === $fetch && $parent->expr instanceof Node\Scalar\String_) {
                $expr = $parent->expr;
                $edits[] = ['start' => $expr->getStartFilePos(), 'end' => $expr->getEndFilePos(), 'text' => $short . '::' . $this->caseFor($expr->value, $caseMap)];

                continue;
            }

            // Used where a string is required → unwrap with ->value.
            if ($this->needsStringValue($fetch)) {
                $edits[] = ['start' => $fetch->getEndFilePos() + 1, 'end' => $fetch->getEndFilePos(), 'text' => '->value'];
            }
        }

        return $edits;
    }

    private function isThisProperty(Node\Expr\PropertyFetch $fetch, string $field): bool
    {
        return $fetch->var instanceof Node\Expr\Variable
            && $fetch->var->name === 'this'
            && $fetch->name instanceof Node\Identifier
            && $fetch->name->toString() === $field;
    }

    /**
     * Whether this expression sits in a position that requires a string: a `.`
     * concat operand, or a value returned (directly or through `?:`/`??`) from a
     * function whose return type mentions `string`.
     */
    private function needsStringValue(Node $node): bool
    {
        while (true) {
            $parent = $node->getAttribute('parent');

            if ($parent === null) {
                return false;
            }

            if ($parent instanceof Node\Expr\BinaryOp\Concat) {
                return true;
            }

            // Pass through value-producing branches of `?:` / `??`.
            if (($parent instanceof Node\Expr\Ternary && ($parent->if === $node || $parent->else === $node))
                || ($parent instanceof Node\Expr\BinaryOp\Coalesce && ($parent->left === $node || $parent->right === $node))) {
                $node = $parent;

                continue;
            }

            if ($parent instanceof Node\Stmt\Return_) {
                return $this->enclosingReturnsString($parent);
            }

            return false;
        }
    }

    private function enclosingReturnsString(Node $node): bool
    {
        while ($node = $node->getAttribute('parent')) {
            if ($node instanceof Node\FunctionLike) {
                return $this->typeMentionsString($node->getReturnType());
            }
        }

        return false;
    }

    private function typeMentionsString(?Node $type): bool
    {
        if ($type instanceof Node\NullableType) {
            $type = $type->type;
        }

        if ($type instanceof Node\UnionType) {
            foreach ($type->types as $member) {
                if ($member instanceof Node\Identifier && strtolower($member->toString()) === 'string') {
                    return true;
                }
            }

            return false;
        }

        return $type instanceof Node\Identifier && strtolower($type->toString()) === 'string';
    }

    /**
     * The edit that converts a `string` default into an enum case so it matches
     * the now-enum-typed property — or null when there is no convertible default.
     *
     * @param  array<string, string>  $caseMap
     * @return array{start: int, end: int, text: string}|null
     */
    private function defaultEdit(?Node $default, string $short, array $caseMap): ?array
    {
        if ($default === null) {
            return null;
        }

        // `EnumX::Case->value` → `Short::Case` (the property is now `Short`).
        if ($default instanceof Node\Expr\PropertyFetch
            && $default->name instanceof Node\Identifier
            && strtolower($default->name->toString()) === 'value'
            && $default->var instanceof Node\Expr\ClassConstFetch
            && $default->var->name instanceof Node\Identifier) {
            return [
                'start' => $default->getStartFilePos(),
                'end' => $default->getEndFilePos(),
                'text' => $short . '::' . $default->var->name->toString(),
            ];
        }

        // A bare string literal default → `Short::CaseName`.
        if ($default instanceof Node\Scalar\String_) {
            return [
                'start' => $default->getStartFilePos(),
                'end' => $default->getEndFilePos(),
                'text' => $short . '::' . $this->caseFor($default->value, $caseMap),
            ];
        }

        return null;
    }

    /**
     * The case name for a backing value: the mapped name when known, else the
     * studly-cased value (the common backed-enum convention).
     *
     * @param  array<string, string>  $caseMap
     */
    private function caseFor(string $value, array $caseMap): string
    {
        return $caseMap[$value] ?? $this->caseName($value);
    }

    /**
     * Whether the reused enum uses the CompareSelf trait — so the comparison
     * rewrite should emit `Case->equals($x)` (what SuggestCompareSelfTrait wants)
     * instead of `$x === Case`. Best effort: only when the FQCN is autoloadable.
     */
    private function enumUsesCompareSelf(string $reuse): bool
    {
        $fqcn = ltrim($reuse, '\\');

        if (! str_contains($fqcn, '\\') || ! enum_exists($fqcn)) {
            return false;
        }

        $configured = ltrim((string) $this->config('compare_self_trait', 'App\\Support\\Enums\\CompareSelf'), '\\');

        foreach ((new \ReflectionClass($fqcn))->getTraitNames() as $trait) {
            $trait = ltrim($trait, '\\');

            if ($trait === $configured || str_ends_with($trait, '\\CompareSelf')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Case value → case name, by reflecting a reusable backed enum (best effort;
     * only when the FQCN is given and autoloadable).
     *
     * @return array<string, string>
     */
    private function caseMapForReuse(string $reuse): array
    {
        $fqcn = ltrim($reuse, '\\');

        if (! str_contains($fqcn, '\\') || ! enum_exists($fqcn)) {
            return [];
        }

        $map = [];

        foreach ($fqcn::cases() as $case) {
            if ($case instanceof \BackedEnum) {
                $map[(string) $case->value] = $case->name;
            }
        }

        return $map;
    }

    /**
     * Add `use <fqcn>;` after the namespace declaration unless already imported.
     */
    /**
     * The full FQCN for a reuse target: the value itself when an explicit FQCN
     * was passed, otherwise a bare short name resolved against the codebase
     * index (only when exactly one enum carries that short name — an ambiguous
     * or unknown name resolves to null). Null when it cannot be determined.
     */
    private function resolveReuseFqcn(string $reuse): ?string
    {
        $reuse = ltrim($reuse, '\\');

        if (str_contains($reuse, '\\')) {
            return $reuse;
        }

        $matches = $this->index?->enumsByShortName($reuse) ?? [];

        return count($matches) === 1 ? ltrim($matches[0]->fqcn, '\\') : null;
    }

    /**
     * Ensure the reused enum is referenceable after the retype, returning the
     * (possibly) edited content and an optional penance note.
     *
     * An explicit or index-resolved FQCN is imported (unless the enum already
     * lives in this file's namespace or is already imported). A bare short name
     * that the index can't resolve — no index, no match, or an ambiguous one —
     * leaves a note telling the dev to add the import by hand, so the file is
     * never silently left referencing an unimported class (#191).
     *
     * @param  array<Node>  $ast
     * @return array{0: string, 1: ?string}
     */
    private function ensureReuseImport(string $content, array $ast, string $reuse, ?string $fqcn): array
    {
        $short = $this->shortClassName($reuse);

        // Already imported / aliased in this file → nothing to add.
        if ($this->isImported($content, $short)) {
            return [$content, null];
        }

        if ($fqcn !== null) {
            // No import needed when the enum lives in this file's own namespace.
            if ($this->namespacePart($fqcn) === $this->namespaceOf($ast)) {
                return [$content, null];
            }

            return [FileImports::ensure($content, $fqcn), null];
        }

        // A bare short name we could not resolve to a FQCN — never guess one.
        $matchCount = count($this->index?->enumsByShortName($short) ?? []);
        $why = $matchCount > 1
            ? "{$matchCount} enums share the short name — re-run with the full FQCN"
            : 'it was given as a short name and no matching enum was found in the scanned codebase — re-run with the full FQCN or add the import yourself';

        return [$content, "Add a `use` import for the reused enum `{$short}` by hand: {$why}."];
    }

    /**
     * Whether `$short` is already imported (directly or via an `as` alias).
     */
    private function isImported(string $content, string $short): bool
    {
        $q = preg_quote($short, '/');

        return preg_match('/^\s*use\s+(?:[^;]*\\\\)?' . $q . '\s*;/m', $content) === 1
            || preg_match('/^\s*use\s+[^;]*\bas\s+' . $q . '\s*;/m', $content) === 1;
    }

    /**
     * The namespace portion of a FQCN (`App\Enums\Foo` → `App\Enums`), or null
     * for a class in the global namespace.
     */
    private function namespacePart(string $fqcn): ?string
    {
        $fqcn = ltrim($fqcn, '\\');
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? null : substr($fqcn, 0, $pos);
    }

    /**
     * The `string`-typed, closed-set-named data fields in the file, each tagged
     * with whether its declaring class is a Spatie Data subclass — because the
     * auto-fix is only SAFE there (Data's `::from()` bridges string↔enum, so the
     * literals at construction/serialization need no rewrite; a plain class has
     * no such bridge and its string assignments/comparisons would break).
     *
     * @param  array<Node>  $ast
     * @return list<array{name: string, line: int, noun: string, typeNode: Node, nullable: bool, isData: bool}>
     */
    private function collectFields(array $ast): array
    {
        $names = $this->closedSetNames();

        if ($names === []) {
            return [];
        }

        $fields = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            $isData = $this->extendsDataBase($class);

            // A field whose values are sourced from class CONSTANTS anywhere in the
            // class (`type: WireType::MIXED`, `$type = Foo::BAR`, `'type' => Foo::X`,
            // `Foo::class`) is already modelled by that class — it is NOT a bare
            // closed set to mint a NEW enum for. Either that class is the enum-to-be
            // (a different concern) or it is a value class with an OPEN token space
            // (e.g. a wire-type carrying `resource:<x>` forms — #137); `::class`
            // backing is a class-string, which the advisory already calls open.
            // Suppress the field either way.
            $constBacked = $this->classConstBackedFieldNames($class);

            // Promoted constructor properties. A field carrying an attribute is
            // skipped: an attribute (`#[Input]`, `#[Pick*]`, a container binding)
            // signals hydration that hands it a raw STRING regardless of the
            // declared type, so an enum retype would throw a TypeError.
            $constructor = $class->getMethod('__construct');

            if ($constructor !== null) {
                foreach ($constructor->params as $param) {
                    if ($param->flags === 0
                        || $param->attrGroups !== []
                        || ! $param->var instanceof Node\Expr\Variable
                        || ! is_string($param->var->name)
                        || in_array($param->var->name, $constBacked, true)) {
                        continue;
                    }

                    $this->addField($fields, $param->type, $param->var->name, $param->getStartLine(), $names, $isData, $param->default);
                }
            }

            // Class properties (same attribute exemption).
            foreach ($class->getProperties() as $property) {
                if ($property->attrGroups !== []) {
                    continue;
                }

                foreach ($property->props as $prop) {
                    if (in_array($prop->name->toString(), $constBacked, true)) {
                        continue;
                    }

                    $this->addField($fields, $property->type, $prop->name->toString(), $prop->getStartLine(), $names, $isData, $prop->default);
                }
            }
        }

        return $fields;
    }

    /**
     * Field names whose VALUES are sourced from class constants anywhere in the
     * class — a named argument `field: Foo::BAR`, an array entry `'field' => Foo::X`,
     * a param/property default `Foo::BAR`, or an assignment `$this->field = Foo::X`
     * (`::class` included — that is a class-string). Such a field is already modelled
     * by that class, so it is not a fresh closed set to enum-ify.
     *
     * @return list<string>
     */
    private function classConstBackedFieldNames(Node\Stmt\Class_ $class): array
    {
        $names = [];
        $finder = new NodeFinder;

        // named args: `field: Foo::BAR`
        foreach ($finder->findInstanceOf($class, Node\Arg::class) as $arg) {
            if ($arg->name instanceof Node\Identifier && $arg->value instanceof Node\Expr\ClassConstFetch) {
                $names[] = $arg->name->toString();
            }
        }

        // array entries: `'field' => Foo::BAR`
        foreach ($finder->findInstanceOf($class, Node\Expr\ArrayItem::class) as $item) {
            if ($item->key instanceof Node\Scalar\String_ && $item->value instanceof Node\Expr\ClassConstFetch) {
                $names[] = $item->key->value;
            }
        }

        // assignments: `$this->field = Foo::BAR`
        foreach ($finder->findInstanceOf($class, Node\Expr\Assign::class) as $assign) {
            if ($assign->var instanceof Node\Expr\PropertyFetch
                && $assign->var->name instanceof Node\Identifier
                && $assign->expr instanceof Node\Expr\ClassConstFetch) {
                $names[] = $assign->var->name->toString();
            }
        }

        // param / property defaults: `$field = Foo::BAR`
        foreach ($finder->findInstanceOf($class, Node\Param::class) as $param) {
            if ($param->default instanceof Node\Expr\ClassConstFetch
                && $param->var instanceof Node\Expr\Variable
                && is_string($param->var->name)) {
                $names[] = $param->var->name;
            }
        }

        foreach ($class->getProperties() as $property) {
            foreach ($property->props as $prop) {
                if ($prop->default instanceof Node\Expr\ClassConstFetch) {
                    $names[] = $prop->name->toString();
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param  list<array{name: string, line: int, noun: string, typeNode: Node, nullable: bool, isData: bool, default: ?Node}>  $fields
     */
    private function addField(array &$fields, ?Node $type, string $name, int $line, array $names, bool $isData, ?Node $default = null): void
    {
        if (! $this->isStringType($type)) {
            return;
        }

        $noun = $this->matchedNoun($name, $names);

        if ($noun === null) {
            return;
        }

        assert($type instanceof Node);

        $fields[] = [
            'name' => $name,
            'line' => $line,
            'noun' => $noun,
            'typeNode' => $type,
            'nullable' => $type instanceof Node\NullableType,
            'isData' => $isData,
            'default' => $default,
        ];
    }

    /**
     * Whether the class extends a Spatie-Data-style base — heuristically, a
     * parent whose short name ends in `Data` (`Data`, `BaseData`, …). That is
     * where `::from()` bridges string↔enum, making the retype safe.
     */
    private function extendsDataBase(Node\Stmt\Class_ $class): bool
    {
        if (! $class->extends instanceof Node\Name) {
            return false;
        }

        $suffix = (string) $this->config('data_base_suffix', 'Data');

        return str_ends_with($class->extends->getLast(), $suffix);
    }

    private function isStringType(?Node $type): bool
    {
        if ($type instanceof Node\NullableType) {
            $type = $type->type;
        }

        return $type instanceof Node\Identifier && strtolower($type->toString()) === 'string';
    }

    /**
     * The field to convert: the one named by `--input field`, or the sole field
     * when there is exactly one. Null when ambiguous.
     *
     * @param  list<array{name: string, line: int, noun: string, typeNode: Node, nullable: bool}>  $fields
     * @return array{name: string, line: int, noun: string, typeNode: Node, nullable: bool}|null
     */
    private function pickField(array $fields): ?array
    {
        $wanted = trim($this->repentInput['field'] ?? '');

        if ($wanted !== '') {
            foreach ($fields as $field) {
                if ($field['name'] === $wanted) {
                    return $field;
                }
            }

            return null;
        }

        return count($fields) === 1 ? $fields[0] : null;
    }

    /**
     * @param  list<string>  $cases
     */
    private function renderEnum(?string $namespace, string $short, array $cases): string
    {
        $lines = ["<?php", '', 'declare(strict_types=1);', ''];

        if ($namespace !== null) {
            $lines[] = "namespace {$namespace};";
            $lines[] = '';
        }

        $lines[] = "enum {$short}: string";
        $lines[] = '{';

        foreach ($cases as $value) {
            $lines[] = sprintf("    case %s = '%s';", $this->caseName($value), $value);
        }

        $lines[] = '}';

        return implode("\n", $lines) . "\n";
    }

    /**
     * @return list<string>
     */
    private function parseCases(string $raw): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $v): bool => $v !== ''));
    }

    private function caseName(string $value): string
    {
        $words = preg_split('/[^a-zA-Z0-9]+/', $value) ?: [$value];
        $studly = implode('', array_map(static fn (string $w): string => ucfirst(strtolower($w)), array_filter($words)));

        // A case name cannot start with a digit.
        return ctype_digit($studly[0] ?? 'x') ? 'Case' . $studly : ($studly === '' ? 'Case' : $studly);
    }

    private function shortClassName(string $class): string
    {
        $class = trim($class, '\\');
        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }

    /**
     * @param  array<Node>  $ast
     */
    private function namespaceOf(array $ast): ?string
    {
        $namespace = (new NodeFinder)->findFirstInstanceOf($ast, Node\Stmt\Namespace_::class);

        return $namespace?->name?->toString();
    }

    /**
     * The closed-set noun a name ends in, at a camelCase or snake_case boundary
     * — or null. `sortDirection` → `direction`, `node_type` → `type`,
     * `prototype` → null.
     *
     * @param  list<string>  $names
     */
    private function matchedNoun(string $name, array $names): ?string
    {
        $lower = strtolower($name);

        foreach ($names as $noun) {
            if ($lower === $noun) {
                return $noun;
            }

            if (! str_ends_with($lower, $noun)) {
                continue;
            }

            $start = strlen($name) - strlen($noun);
            $boundaryChar = $name[$start];
            $previous = $start > 0 ? $name[$start - 1] : '';

            if (ctype_upper($boundaryChar) || $previous === '_') {
                return $noun;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function closedSetNames(): array
    {
        $configured = $this->config('names', self::DEFAULT_NAMES);

        if (! is_array($configured)) {
            return self::DEFAULT_NAMES;
        }

        return array_values(array_map(
            strtolower(...),
            array_filter($configured, 'is_string'),
        ));
    }
}
