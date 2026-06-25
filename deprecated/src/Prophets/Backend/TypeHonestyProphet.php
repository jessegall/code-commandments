<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\CallGraph\CallSite;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\Resolvers\Ast\FileImports;
use JesseGall\CodeCommandments\Support\Resolvers\Ast\ReceiverTypeResolver;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use ReflectionClass;
use ReflectionNamedType;

/**
 * The Boundary & Typing discipline — types must tell the truth about absence.
 * Two disjoint verdicts (see docs/disciplines.md, BoundaryTypingDiscipline):
 *
 *   V1 FAKE-REQUIRED (sin) — a nullable value coalesced to an EMPTY STRING
 *      (`?? ''`, `?? T_String::empty()`, `?? T_String::EMPTY`) to fill a REQUIRED,
 *      non-nullable, no-default `string` constructor slot. An empty id/name/summary
 *      is a manufactured value, not a real one: assert it (throw) or make the field
 *      nullable. Only STRING empties fire — `[]`/`0`/`false` are legitimate values
 *      whose "required" status can only be judged by following their use (deferred).
 *
 *   V2 PHANTOM-NULLABLE (warn) — a boundary DTO (Spatie Data) whose EVERY field is
 *      `?T = null` (≥2 fields). An all-nullable boundary validates nothing and pushes
 *      every required-field check downstream. A non-nullable discriminator (the
 *      common type-tagged shape) breaks the ratio and is intentionally left alone.
 *
 * Detection is reflection / AST over the EFFECTIVE constructor — never name lists.
 */
#[IntroducedIn('3.13.0')]
class TypeHonestyProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    /** Framework bases whose subclasses are deserialization boundaries. */
    private const BOUNDARY_BASES = [
        'Spatie\\LaravelData\\Data',
        'Illuminate\\Foundation\\Http\\FormRequest',
    ];

    /** php-types short names whose `::empty()` / `::EMPTY` is the string empty identity. */
    private const STRING_EMPTY_WRAPPERS = ['T_String'];

    private ?CodebaseIndex $index = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }

    public function description(): string
    {
        return 'Type honesty at the boundary — no empty-string fake for a required slot; no all-nullable boundary DTO';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Correctness;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A boundary DTO declares every field `?T = null` (V2), or a `::from([...])` hydration fills a REQUIRED non-nullable `string` constructor slot with an empty-string default — `?? \'\'` / `?? T_String::empty()` (V1). An empty required id/name/summary is a fake value, and an all-nullable DTO validates nothing.')
            ->leaveWhen('the slot is genuinely optional (the field is nullable or has its own default — then the empty default is fine); the coalesce fills an array/int/bool slot (an empty array / 0 / false is a real value, judged by use, not flagged here); or the DTO has a non-nullable discriminator/required field (ratio < 1.0).')
            ->whenUnsure('if a required field can really be absent, make it nullable and let the absence flow; if it cannot, assert it at the boundary (throw) so hydration fails loudly — never synthesize `\'\'` to satisfy the type.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A type is a contract. When a constructor declares `string $id` (non-nullable, no
default), it promises every instance has a real id. Coalescing a nullable source
to an empty string to satisfy that slot breaks the promise quietly:

Bad — a fake identity manufactured to satisfy a required slot:
    return AssistantUpdateNodeAction::from([
        'id'      => $raw->id ?? '',                  // V1
        'summary' => $raw->summary ?? T_String::empty(),
        'nodeId'  => $raw->nodeId,
    ]);

Good — assert the required value, or make the field honest about being optional:
    if ($raw->id === null || $raw->nodeId === null) {
        throw UnusableActionException::missingIdentity();
    }
    return AssistantUpdateNodeAction::from([
        'id'      => $raw->id,
        'summary' => $raw->summary,                    // action declares ?string $summary
        'nodeId'  => $raw->nodeId,
    ]);

The same rot starts one layer up, at the boundary DTO:

Bad — every field nullable, so the type validates nothing (V2):
    final class RawAction extends Data {
        public function __construct(
            public readonly ?string $id = null,
            public readonly ?string $name = null,
        ) {}
    }

Good — the fields that are actually required are non-nullable, so `from()` throws on
a missing value and the strategy downstream can trust its input.

WHAT FIRES — V1: a `::from([...])` array item `<key> => <expr> ?? <empty string>`
(`''`, `T_String::EMPTY`, or `T_String::empty()`) whose key maps to a constructor
param that is non-nullable, has no default, and is typed `string`. V2: a class
extending a deserialization boundary (Spatie Data / FormRequest) whose every field
(≥2) is a nullable type with a `= null` default.

WHAT DOES NOT — an optional (nullable / defaulted) target slot; an empty ARRAY / 0 /
false default (legitimate values, judged by use, not flagged here); a DTO with any
non-nullable field; a target class whose constructor cannot be resolved (conservative).

NOT auto-fixable: the two honest fixes (throw vs. make-the-field-nullable) are a
human judgment about whether the value is truly required.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $sins = array_merge(
            $this->fakeRequired($ast, $content),
            $this->requiredButNullable($ast, $content),
        );
        $warnings = array_merge(
            $this->phantomNullable($ast, $content),
            $this->nonNullGuard($ast, $content),
            $this->boolUnion($ast, $content),
            $this->dtoOrArraySeam($ast, $content),
            $this->mixedSeam($ast, $content),
            $this->discriminatedPunt($ast, $content),
        );

        if ($sins === [] && $warnings === []) {
            return $this->righteous();
        }

        return new Judgment($sins, $warnings);
    }

    // ---------------------------------------------------------------------
    // V1 — FAKE-REQUIRED
    // ---------------------------------------------------------------------

    /**
     * @param  array<Node>  $ast
     * @return list<\JesseGall\CodeCommandments\Results\Sin>
     */
    private function fakeRequired(array $ast, string $content): array
    {
        $sins = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Expr\StaticCall::class) as $call) {
            if (! $call->name instanceof Node\Identifier || strtolower($call->name->toString()) !== 'from') {
                continue;
            }

            if (count($call->args) !== 1) {
                continue;
            }

            $arg = $call->args[0];

            if (! $arg instanceof Node\Arg || ! $arg->value instanceof Expr\Array_ || ! $call->class instanceof Node\Name) {
                continue;
            }

            $enclosing = ReceiverTypeResolver::enclosingClass($call, $ast);
            $params = $this->resolveConstructorParams($call->class, $ast, $enclosing);

            if ($params === null) {
                continue; // unresolvable target — never fire a sin
            }

            $targetLabel = $call->class->getLast();

            foreach ($arg->value->items as $item) {
                if (! $item instanceof Node\Expr\ArrayItem || $item->key === null) {
                    continue;
                }

                $key = $this->arrayKeyName($item->key, $enclosing, $ast);

                if ($key === null || ! $item->value instanceof Expr\BinaryOp\Coalesce) {
                    continue;
                }

                if (! $this->isEmptyStringLiteral($item->value->right)) {
                    continue;
                }

                $param = $params[$key] ?? null;

                if ($param === null || $param['nullable'] || $param['hasDefault'] || $param['type'] !== 'string') {
                    continue;
                }

                $sins[] = $this->sinAt(
                    $item->getStartLine(),
                    sprintf(
                        '`%s::$%s` is a required, non-nullable string, but `::from()` fills it with an empty-string default (`?? \'\'`). An empty required id/name/summary is a manufactured value, not a real one — assert it at the boundary (throw when absent) or make the field nullable, never synthesize `\'\'` to satisfy the type.',
                        $targetLabel,
                        $key,
                    ),
                    $this->lineSnippet($content, $item->getStartLine()),
                    null,
                    'fake-required:' . $targetLabel . '::' . $key,
                    false,
                );
            }
        }

        return $sins;
    }

    // ---------------------------------------------------------------------
    // V2 — PHANTOM-NULLABLE
    // ---------------------------------------------------------------------

    /**
     * @param  array<Node>  $ast
     * @return list<\JesseGall\CodeCommandments\Results\Warning>
     */
    private function phantomNullable(array $ast, string $content): array
    {
        $warnings = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if ($class->name === null || ! $this->isBoundaryDto($class, $ast)) {
                continue;
            }

            $fields = $this->dtoFields($class);
            $total = count($fields);

            if ($total < 2) {
                continue;
            }

            foreach ($fields as $field) {
                if (! $field['nullable'] || ! $field['defaultIsNull']) {
                    continue 2; // a non-nullable or non-null-default field breaks the all-nullable ratio
                }
            }

            // Use-following gate: only fire when a consumer actually treats a field as
            // a required value (de-nulls it). A DTO whose fields are only branched-on
            // (`=== null`) — a genuinely-optional value object — stays silent.
            if (! $this->hasConsumedField($class, $ast)) {
                continue;
            }

            $label = $class->name->toString();

            $warnings[] = $this->warningAt(
                $class->getStartLine(),
                sprintf(
                    '`%s` is a boundary DTO whose every field is nullable-with-null-default (`?T = null`). An all-nullable boundary validates nothing and pushes every required-field check downstream where it scatters and drifts — make the fields that are actually required non-nullable so hydration fails loudly on a missing value.',
                    $label,
                ),
                $this->lineSnippet($content, $class->getStartLine()),
                'phantom-nullable:' . $label,
                false,
            );
        }

        return $warnings;
    }

    /**
     * The class's own fields (promoted constructor params + declared non-static
     * properties), each tagged nullable / default-is-null.
     *
     * @return list<array{nullable: bool, defaultIsNull: bool}>
     */
    private function dtoFields(Node\Stmt\Class_ $class): array
    {
        $fields = [];

        foreach ($class->getMethods() as $method) {
            if (strtolower($method->name->toString()) !== '__construct') {
                continue;
            }

            foreach ($method->params as $param) {
                if ($param->flags === 0) {
                    continue; // not a promoted property — a plain ctor argument
                }

                $fields[] = [
                    'nullable' => $this->isNullableType($param->type),
                    'defaultIsNull' => $this->isNullDefault($param->default),
                ];
            }
        }

        foreach ($class->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            foreach ($property->props as $prop) {
                $fields[] = [
                    'nullable' => $this->isNullableType($property->type),
                    'defaultIsNull' => $this->isNullDefault($prop->default),
                ];
            }
        }

        return $fields;
    }

    /**
     * @param  array<Node>  $ast
     */
    private function isBoundaryDto(Node\Stmt\Class_ $class, array $ast): bool
    {
        if (! $class->extends instanceof Node\Name) {
            return false;
        }

        $parentFqcn = $this->resolveFqcn($class->extends, $ast, $class);

        if ($parentFqcn === null) {
            return false;
        }

        if (in_array($parentFqcn, self::BOUNDARY_BASES, true)) {
            return true;
        }

        if (class_exists($parentFqcn)) {
            foreach (self::BOUNDARY_BASES as $base) {
                if (is_subclass_of($parentFqcn, $base)) {
                    return true;
                }
            }
        }

        // In-file (or indexed) parent: recurse so a 2-level boundary still resolves.
        $parentNode = $this->findClassNode($ast, $class->extends->getLast());

        if ($parentNode !== null && $parentNode !== $class) {
            return $this->isBoundaryDto($parentNode, $ast);
        }

        return false;
    }

    // ---------------------------------------------------------------------
    // V5 — REQUIRED-BUT-NULLABLE
    // ---------------------------------------------------------------------

    /**
     * @param  array<Node>  $ast
     * @return list<\JesseGall\CodeCommandments\Results\Sin>
     */
    private function requiredButNullable(array $ast, string $content): array
    {
        $sins = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if ($class->name === null || ! $this->isBoundaryDto($class, $ast)) {
                continue;
            }

            $fields = $this->fieldEntries($class);
            $requiredByRules = $this->requiredRuleFields($class);

            foreach ($fields as $name => $field) {
                $required = $field['requiredAttr'] || in_array($name, $requiredByRules, true);

                if (! $required || ! $field['nullable']) {
                    continue;
                }

                $sins[] = $this->sinAt(
                    $field['line'],
                    sprintf(
                        '`%s::$%s` is typed nullable (`?T`) but the class marks it `required` (via its `rules()` / a `#[Required]` attribute) — the type and the validation contract contradict. Make the field non-nullable (it is always present) or drop the `required` rule.',
                        $class->name->toString(),
                        $name,
                    ),
                    $this->lineSnippet($content, $field['line']),
                    null,
                    'required-but-nullable:' . $class->name->toString() . '::' . $name,
                    false,
                );
            }
        }

        return $sins;
    }

    /**
     * @return array<string, array{nullable: bool, requiredAttr: bool, line: int}>
     */
    private function fieldEntries(Node\Stmt\Class_ $class): array
    {
        $entries = [];

        foreach ($class->getMethods() as $method) {
            if (strtolower($method->name->toString()) === '__construct') {
                foreach ($method->params as $param) {
                    if ($param->flags !== 0 && $param->var instanceof Expr\Variable && is_string($param->var->name)) {
                        $entries[$param->var->name] = [
                            'nullable' => $this->isNullableType($param->type),
                            'requiredAttr' => $this->hasRequiredAttribute($param->attrGroups),
                            'line' => $param->getStartLine(),
                        ];
                    }
                }
            }
        }

        foreach ($class->getProperties() as $property) {
            if (! $property->isStatic()) {
                foreach ($property->props as $prop) {
                    $entries[$prop->name->toString()] = [
                        'nullable' => $this->isNullableType($property->type),
                        'requiredAttr' => $this->hasRequiredAttribute($property->attrGroups),
                        'line' => $property->getStartLine(),
                    ];
                }
            }
        }

        return $entries;
    }

    /**
     * Top-level field names a `rules()` method marks UNCONDITIONALLY required — a bare
     * `required` token (not `required_if`/`required_with`), and not alongside `nullable`.
     *
     * @return list<string>
     */
    private function requiredRuleFields(Node\Stmt\Class_ $class): array
    {
        $array = null;

        foreach ($class->getMethods() as $method) {
            if (strtolower($method->name->toString()) !== 'rules' || $method->stmts === null) {
                continue;
            }

            foreach ((new NodeFinder)->findInstanceOf($method->stmts, Node\Stmt\Return_::class) as $return) {
                if ($return->expr instanceof Expr\Array_) {
                    $array = $return->expr;

                    break 2;
                }
            }
        }

        if ($array === null) {
            return [];
        }

        $required = [];

        foreach ($array->items as $item) {
            if (! $item instanceof Node\Expr\ArrayItem || ! $item->key instanceof Node\Scalar\String_) {
                continue;
            }

            $field = explode('.', $item->key->value)[0];
            $tokens = $this->ruleTokens($item->value);

            if (in_array('required', $tokens, true) && ! in_array('nullable', $tokens, true)) {
                $required[] = $field;
            }
        }

        return array_values(array_unique($required));
    }

    /**
     * The validation rule tokens for one field — from a `'required|string'` pipe
     * string or a `['required', 'string']` array (string elements only).
     *
     * @return list<string>
     */
    private function ruleTokens(Expr $value): array
    {
        if ($value instanceof Node\Scalar\String_) {
            return explode('|', $value->value);
        }

        if ($value instanceof Expr\Array_) {
            $tokens = [];

            foreach ($value->items as $item) {
                if ($item instanceof Node\Expr\ArrayItem && $item->value instanceof Node\Scalar\String_) {
                    $tokens[] = $item->value->value;
                }
            }

            return $tokens;
        }

        return [];
    }

    /**
     * @param  array<Node\AttributeGroup>  $attrGroups
     */
    private function hasRequiredAttribute(array $attrGroups): bool
    {
        foreach ($attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if (strtolower($attr->name->getLast()) === 'required') {
                    return true;
                }
            }
        }

        return false;
    }

    // ---------------------------------------------------------------------
    // V2 use-following — does any consumer treat a field as a required value?
    // ---------------------------------------------------------------------

    /**
     * True when some consumer DE-NULLS one of $class's fields — derefs it, coalesces
     * it to a non-null default, casts it, passes it as an arg, or iterates it — as
     * opposed to merely branching on its null (a genuinely-optional value object).
     * Scans the current file plus, via the index, the files that hydrate the DTO.
     *
     * @param  array<Node>  $ast
     */
    private function hasConsumedField(Node\Stmt\Class_ $class, array $ast): bool
    {
        $short = $class->name?->toString();

        if ($short === null) {
            return false;
        }

        $fields = $this->dtoFieldNames($class);

        if ($fields === []) {
            return false;
        }

        $factories = $this->selfReturningFactories($class);
        $asts = [$ast];

        if ($this->index !== null) {
            $fqcn = $this->classFqcn($class, $ast);
            $files = [];

            foreach ($factories as $factory) {
                foreach ($this->index->callersOf($fqcn, $factory) as $callSite) {
                    $files[$callSite->callerFile] = true;
                }
            }

            foreach ($this->index->instantiationsOf($fqcn) as $instantiation) {
                $files[$instantiation['file']] = true;
            }

            foreach (array_keys($files) as $file) {
                $fileContent = @file_get_contents($file);

                if (is_string($fileContent)) {
                    $fileAst = $this->parse($fileContent);

                    if ($fileAst !== null) {
                        $asts[] = $fileAst;
                    }
                }
            }
        }

        foreach ($asts as $candidate) {
            foreach ($this->cTypedFieldAccesses($candidate, $short, $factories, $fields) as $access) {
                if ($this->isAccessConsumed($access, $candidate)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function dtoFieldNames(Node\Stmt\Class_ $class): array
    {
        $names = [];

        foreach ($class->getMethods() as $method) {
            if (strtolower($method->name->toString()) === '__construct') {
                foreach ($method->params as $param) {
                    if ($param->flags !== 0 && $param->var instanceof Expr\Variable && is_string($param->var->name)) {
                        $names[] = $param->var->name;
                    }
                }
            }
        }

        foreach ($class->getProperties() as $property) {
            if (! $property->isStatic()) {
                foreach ($property->props as $prop) {
                    $names[] = $prop->name->toString();
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Static factory method names that return `self`/`static`/the class — the entry
     * points that yield a typed instance. `from`/`make` (the FromArrayOnly trait) are
     * always included.
     *
     * @return list<string>
     */
    private function selfReturningFactories(Node\Stmt\Class_ $class): array
    {
        $names = ['from', 'make'];
        $short = strtolower($class->name?->toString() ?? '');

        foreach ($class->getMethods() as $method) {
            if (! $method->isStatic()) {
                continue;
            }

            $return = $method->returnType;
            $name = $return instanceof Node\Identifier
                ? strtolower($return->toString())
                : ($return instanceof Node\Name ? strtolower($return->getLast()) : null);

            if (in_array($name, ['self', 'static', $short], true)) {
                $names[] = $method->name->toString();
            }
        }

        return array_values(array_unique($names));
    }

    private function classFqcn(Node\Stmt\Class_ $class, array $ast): string
    {
        $namespace = FileImports::namespace($ast);
        $name = $class->name?->toString() ?? '';

        return $namespace === null ? $name : $namespace . '\\' . $name;
    }

    /**
     * Property-fetch nodes `<recv>-><field>` whose receiver is provably the class
     * $short and whose field is one of $fields.
     *
     * @param  array<Node>  $ast
     * @param  list<string>  $factories
     * @param  list<string>  $fields
     * @return list<Expr\PropertyFetch>
     */
    private function cTypedFieldAccesses(array $ast, string $short, array $factories, array $fields): array
    {
        $cVars = $this->varsAssignedFromClass($ast, $short, $factories);
        $declares = $this->findClassNode($ast, $short) !== null;
        $accesses = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Expr\PropertyFetch::class) as $fetch) {
            if (! $fetch->name instanceof Node\Identifier || ! in_array($fetch->name->toString(), $fields, true)) {
                continue;
            }

            if ($this->receiverIsClass($fetch->var, $short, $factories, $cVars, $declares, $fetch, $ast)) {
                $accesses[] = $fetch;
            }
        }

        return $accesses;
    }

    /**
     * @param  list<string>  $factories
     * @param  list<string>  $cVars
     * @param  array<Node>  $ast
     */
    private function receiverIsClass(Expr $recv, string $short, array $factories, array $cVars, bool $declares, Node $context, array $ast): bool
    {
        $lower = strtolower($short);

        // C::factory(...)->field
        if ($recv instanceof Expr\StaticCall && $recv->class instanceof Node\Name
            && strtolower($recv->class->getLast()) === $lower
            && $recv->name instanceof Node\Identifier && in_array($recv->name->toString(), $factories, true)) {
            return true;
        }

        // new C(...)->field
        if ($recv instanceof Expr\New_ && $recv->class instanceof Node\Name && strtolower($recv->class->getLast()) === $lower) {
            return true;
        }

        if ($recv instanceof Expr\Variable && is_string($recv->name)) {
            // $v->field where $v = C::factory(...) / new C(...)
            if (in_array($recv->name, $cVars, true)) {
                return true;
            }

            // $this->field inside the class's own file
            if ($recv->name === 'this' && $declares) {
                return true;
            }

            // a param/local typed C
            $type = ReceiverTypeResolver::paramTypeNode($recv->name, $context, $ast);

            if ($type instanceof Node\Name && strtolower($type->getLast()) === $lower) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $factories
     * @param  array<Node>  $ast
     * @return list<string>
     */
    private function varsAssignedFromClass(array $ast, string $short, array $factories): array
    {
        $lower = strtolower($short);
        $vars = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Expr\Assign::class) as $assign) {
            if (! $assign->var instanceof Expr\Variable || ! is_string($assign->var->name)) {
                continue;
            }

            $expr = $assign->expr;

            $fromFactory = $expr instanceof Expr\StaticCall && $expr->class instanceof Node\Name
                && strtolower($expr->class->getLast()) === $lower
                && $expr->name instanceof Node\Identifier && in_array($expr->name->toString(), $factories, true);

            $fromNew = $expr instanceof Expr\New_ && $expr->class instanceof Node\Name
                && strtolower($expr->class->getLast()) === $lower;

            if ($fromFactory || $fromNew) {
                $vars[] = $assign->var->name;
            }
        }

        return array_values(array_unique($vars));
    }

    /**
     * Whether $access is consumed as a required value — iterated, coalesced to a
     * non-null default, cast, dereferenced, or passed as a call argument — rather
     * than merely assigned or null-branched.
     *
     * @param  array<Node>  $ast
     */
    private function isAccessConsumed(Expr\PropertyFetch $access, array $ast): bool
    {
        $start = (int) $access->getStartFilePos();
        $end = (int) $access->getEndFilePos();
        $within = fn (?Node $n): bool => $n !== null
            && (int) $n->getStartFilePos() <= $start && (int) $n->getEndFilePos() >= $end;
        $finder = new NodeFinder;

        foreach ($finder->findInstanceOf($ast, Node\Stmt\Foreach_::class) as $foreach) {
            if ($within($foreach->expr)) {
                return true;
            }
        }

        foreach ($finder->findInstanceOf($ast, Expr\BinaryOp\Coalesce::class) as $coalesce) {
            if (! $this->isNullConst($coalesce->right) && $within($coalesce->left)) {
                return true;
            }
        }

        foreach ($finder->findInstanceOf($ast, Expr\Cast::class) as $cast) {
            if ($within($cast->expr)) {
                return true;
            }
        }

        // Deref: a fetch/call/index whose receiver is (or contains) the access.
        foreach ($finder->find($ast, static fn (Node $n): bool =>
            $n instanceof Expr\PropertyFetch || $n instanceof Expr\NullsafePropertyFetch
            || $n instanceof Expr\MethodCall || $n instanceof Expr\NullsafeMethodCall
            || $n instanceof Expr\ArrayDimFetch) as $deref) {
            if ($deref !== $access && $within($deref->var)) {
                return true;
            }
        }

        // Passed as a call argument.
        foreach ($finder->find($ast, static fn (Node $n): bool =>
            $n instanceof Expr\FuncCall || $n instanceof Expr\MethodCall
            || $n instanceof Expr\StaticCall || $n instanceof Expr\NullsafeMethodCall
            || $n instanceof Expr\New_) as $call) {
            foreach ($call->args as $arg) {
                if ($arg instanceof Node\Arg && $within($arg->value)) {
                    return true;
                }
            }
        }

        return false;
    }

    // ---------------------------------------------------------------------
    // V7 — NONNULL-GUARD
    // ---------------------------------------------------------------------

    /**
     * @param  array<Node>  $ast
     * @return list<\JesseGall\CodeCommandments\Results\Warning>
     */
    private function nonNullGuard(array $ast, string $content): array
    {
        $warnings = [];
        $finder = new NodeFinder;

        $checks = $finder->find($ast, static fn (Node $n): bool =>
            $n instanceof Expr\BinaryOp\Identical || $n instanceof Expr\BinaryOp\NotIdentical);

        foreach ($checks as $cmp) {
            /** @var Expr\BinaryOp $cmp */
            $value = $this->nullComparisonOperand($cmp);

            if ($value === null || ! $this->isNonNullableValue($value, $cmp, $ast)) {
                continue;
            }

            $warnings[] = $this->nonNullGuardWarning($value, $cmp->getStartLine(), $content);
        }

        foreach ($finder->findInstanceOf($ast, Expr\FuncCall::class) as $call) {
            if (! $call->name instanceof Node\Name || strtolower($call->name->toString()) !== 'is_null') {
                continue;
            }

            $arg = $call->args[0] ?? null;

            if (! $arg instanceof Node\Arg || ! $this->isNonNullableValue($arg->value, $call, $ast)) {
                continue;
            }

            $warnings[] = $this->nonNullGuardWarning($arg->value, $call->getStartLine(), $content);
        }

        return $warnings;
    }

    private function nonNullGuardWarning(Expr $value, int $line, string $content): \JesseGall\CodeCommandments\Results\Warning
    {
        $label = $this->exprLabel($value);

        return $this->warningAt(
            $line,
            sprintf(
                '`%s` is declared non-nullable, so this null check can never be true — the guard is dead. Drop it, or change the declared type if the value really can be null.',
                $label,
            ),
            $this->lineSnippet($content, $line),
            'nonnull-guard:' . $label,
            false,
        );
    }

    /** The non-null operand of an `=== null` / `!== null` comparison, or null when neither side is `null`. */
    private function nullComparisonOperand(Expr\BinaryOp $cmp): ?Expr
    {
        if ($this->isNullConst($cmp->right)) {
            return $cmp->left;
        }

        if ($this->isNullConst($cmp->left)) {
            return $cmp->right;
        }

        return null;
    }

    private function isNullConst(Expr $expr): bool
    {
        return $expr instanceof Expr\ConstFetch && strtolower($expr->name->toString()) === 'null';
    }

    /**
     * Whether $value resolves to a declared NON-nullable type (a typed param or a
     * `$this` property) — so a null check on it is dead.
     *
     * @param  array<Node>  $ast
     */
    private function isNonNullableValue(Expr $value, Node $context, array $ast): bool
    {
        $type = $this->declaredTypeNode($value, $context, $ast);

        return $type !== null && $this->isNonNullableTypeNode($type);
    }

    /**
     * @param  array<Node>  $ast
     */
    private function declaredTypeNode(Expr $value, Node $context, array $ast): ?Node
    {
        if ($value instanceof Expr\Variable && is_string($value->name)) {
            return ReceiverTypeResolver::paramTypeNode($value->name, $context, $ast);
        }

        if (($value instanceof Expr\PropertyFetch || $value instanceof Expr\NullsafePropertyFetch)
            && $value->var instanceof Expr\Variable
            && $value->var->name === 'this'
            && $value->name instanceof Node\Identifier
        ) {
            $class = ReceiverTypeResolver::enclosingClass($context, $ast);

            return $class === null ? null : ReceiverTypeResolver::propertyTypeNode($class, $value->name->toString());
        }

        return null;
    }

    private function isNonNullableTypeNode(Node $type): bool
    {
        if ($type instanceof Node\NullableType) {
            return false;
        }

        if ($type instanceof Node\Identifier) {
            $name = strtolower($type->toString());

            return $name !== 'null' && $name !== 'mixed';
        }

        if ($type instanceof Node\Name) {
            return true;
        }

        if ($type instanceof Node\UnionType) {
            foreach ($type->types as $member) {
                if ($member instanceof Node\Identifier && in_array(strtolower($member->toString()), ['null', 'mixed'], true)) {
                    return false;
                }
            }

            return true;
        }

        return $type instanceof Node\IntersectionType;
    }

    private function exprLabel(Expr $value): string
    {
        if ($value instanceof Expr\Variable && is_string($value->name)) {
            return '$' . $value->name;
        }

        if (($value instanceof Expr\PropertyFetch || $value instanceof Expr\NullsafePropertyFetch)
            && $value->name instanceof Node\Identifier
        ) {
            return '$this->' . $value->name->toString();
        }

        return 'value';
    }

    // ---------------------------------------------------------------------
    // V8 — DISCRIMINATED-PUNT
    // ---------------------------------------------------------------------

    /**
     * @param  array<Node>  $ast
     * @return list<\JesseGall\CodeCommandments\Results\Warning>
     */
    private function discriminatedPunt(array $ast, string $content): array
    {
        $warnings = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if ($class->name === null || ! $this->isBoundaryDto($class, $ast)) {
                continue;
            }

            $types = $this->fieldTypeMap($class);
            $mixed = [];
            $discriminators = [];

            foreach ($types as $name => $field) {
                $type = $field['type'];

                if ($type instanceof Node\Identifier && strtolower($type->toString()) === 'mixed') {
                    $mixed[$name] = $field['line'];
                } elseif (($type instanceof Node\Identifier && strtolower($type->toString()) === 'string') || $type instanceof Node\Name) {
                    $discriminators[$name] = true;
                }
            }

            if ($mixed === [] || $discriminators === []) {
                continue;
            }

            $short = $class->name->toString();
            $factories = $this->selfReturningFactories($class);
            $fired = false;

            foreach ($this->consumerAsts($class, $ast) as $candidate) {
                if ($this->hasTaggedUnionMatch($candidate, $short, $factories, array_keys($discriminators), array_keys($mixed))) {
                    $fired = true;

                    break;
                }
            }

            if (! $fired) {
                continue;
            }

            $payload = (string) array_key_first($mixed);

            $warnings[] = $this->warningAt(
                $mixed[$payload],
                sprintf(
                    '`%s` is a tagged-union punt — a `mixed` payload `$%s` whose shape a consumer decides by `match()`-ing on a sibling discriminator and coercing it per arm. Model it as a typed union (one payload type per discriminator case) so each shape is named once, not re-coerced at every reader.',
                    $short,
                    $payload,
                ),
                $this->lineSnippet($content, $mixed[$payload]),
                'discriminated-punt:' . $short,
                false,
            );
        }

        return $warnings;
    }

    /**
     * @return array<string, array{type: ?Node, line: int}>
     */
    private function fieldTypeMap(Node\Stmt\Class_ $class): array
    {
        $map = [];

        foreach ($class->getMethods() as $method) {
            if (strtolower($method->name->toString()) === '__construct') {
                foreach ($method->params as $param) {
                    if ($param->flags !== 0 && $param->var instanceof Expr\Variable && is_string($param->var->name)) {
                        $map[$param->var->name] = ['type' => $param->type, 'line' => $param->getStartLine()];
                    }
                }
            }
        }

        foreach ($class->getProperties() as $property) {
            if (! $property->isStatic()) {
                foreach ($property->props as $prop) {
                    $map[$prop->name->toString()] = ['type' => $property->type, 'line' => $property->getStartLine()];
                }
            }
        }

        return $map;
    }

    /**
     * Whether some `match`/`switch` in $ast discriminates on a class-$short field and,
     * within it, reads one of the class's `mixed` payload fields off a provably-$short
     * receiver — the untyped tagged-union shape.
     *
     * @param  array<Node>  $ast
     * @param  list<string>  $factories
     * @param  list<string>  $discriminators
     * @param  list<string>  $mixedFields
     */
    private function hasTaggedUnionMatch(array $ast, string $short, array $factories, array $discriminators, array $mixedFields): bool
    {
        $cVars = $this->varsAssignedFromClass($ast, $short, $factories);
        $declares = $this->findClassNode($ast, $short) !== null;
        $finder = new NodeFinder;

        $switches = $finder->find($ast, static fn (Node $n): bool =>
            $n instanceof Expr\Match_ || $n instanceof Node\Stmt\Switch_);

        foreach ($switches as $switch) {
            /** @var Expr\Match_|Node\Stmt\Switch_ $switch */
            $cond = $switch->cond;

            if (! $cond instanceof Expr\PropertyFetch || ! $cond->name instanceof Node\Identifier
                || ! in_array($cond->name->toString(), $discriminators, true)
                || ! $this->receiverIsClass($cond->var, $short, $factories, $cVars, $declares, $cond, $ast)) {
                continue;
            }

            foreach ($finder->findInstanceOf([$switch], Expr\PropertyFetch::class) as $fetch) {
                if ($fetch->name instanceof Node\Identifier
                    && in_array($fetch->name->toString(), $mixedFields, true)
                    && $this->receiverIsClass($fetch->var, $short, $factories, $cVars, $declares, $fetch, $ast)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The current file plus, via the index, the files that hydrate the class — the
     * set of places a consumer could discriminate it.
     *
     * @param  array<Node>  $ast
     * @return list<array<Node>>
     */
    private function consumerAsts(Node\Stmt\Class_ $class, array $ast): array
    {
        $asts = [$ast];

        if ($this->index === null) {
            return $asts;
        }

        $fqcn = $this->classFqcn($class, $ast);
        $files = [];

        foreach ($this->selfReturningFactories($class) as $factory) {
            foreach ($this->index->callersOf($fqcn, $factory) as $callSite) {
                $files[$callSite->callerFile] = true;
            }
        }

        foreach ($this->index->instantiationsOf($fqcn) as $instantiation) {
            $files[$instantiation['file']] = true;
        }

        foreach (array_keys($files) as $file) {
            $fileAst = $this->parseFile($file);

            if ($fileAst !== null) {
                $asts[] = $fileAst;
            }
        }

        return $asts;
    }

    // ---------------------------------------------------------------------
    // V4 — MIXED-SEAM
    // ---------------------------------------------------------------------

    /**
     * @param  array<Node>  $ast
     * @return list<\JesseGall\CodeCommandments\Results\Warning>
     */
    private function mixedSeam(array $ast, string $content): array
    {
        $warnings = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            foreach ($class->getMethods() as $method) {
                if (! $method->isPrivate() && ! $method->isProtected()) {
                    continue;
                }

                foreach ($method->params as $i => $param) {
                    $type = $param->type;

                    if (! $type instanceof Node\Identifier || ! in_array(strtolower($type->toString()), ['mixed', 'object'], true)) {
                        continue;
                    }

                    if (! $param->var instanceof Expr\Variable || ! is_string($param->var->name)) {
                        continue;
                    }

                    $types = $this->callerArgTypes($class, $method->name->toString(), $i, $param->var->name, $ast);

                    if ($types === null || $types === [] || count(array_unique($types)) !== 1) {
                        continue;
                    }

                    $warnings[] = $this->warningAt(
                        $param->getStartLine(),
                        sprintf(
                            '`%s::%s()` takes `$%s` as `%s`, but every resolved caller passes a `%s` — type the parameter `%s` instead of widening the seam to mixed.',
                            $class->name?->toString() ?? 'this class',
                            $method->name->toString(),
                            $param->var->name,
                            strtolower($type->toString()),
                            $types[0],
                            $types[0],
                        ),
                        $this->lineSnippet($content, $param->getStartLine()),
                        'mixed-seam:' . ($class->name?->toString() ?? '') . '::' . $method->name->toString() . '::' . $param->var->name,
                        false,
                    );
                }
            }
        }

        return $warnings;
    }

    /**
     * The resolved project-class type passed by EVERY caller of $class::$methodName at
     * parameter position $i (name $pname). Returns null (bail — no warning) when there
     * are no callers, or any caller's argument is missing, unresolved, or not a single
     * clean class type. Only fires when every caller cleanly agrees.
     *
     * @param  array<Node>  $ast
     * @return list<string>|null
     */
    private function callerArgTypes(Node\Stmt\Class_ $class, string $methodName, int $i, string $pname, array $ast): ?array
    {
        $types = [];
        $found = false;

        foreach ((new NodeFinder)->findInstanceOf($ast, Expr\MethodCall::class) as $call) {
            if (! $call->name instanceof Node\Identifier || $call->name->toString() !== $methodName) {
                continue;
            }

            if (! $call->var instanceof Expr\Variable || $call->var->name !== 'this') {
                continue;
            }

            $found = true;
            $arg = $this->argForParam($call->args, $i, $pname);

            if ($arg === null) {
                return null;
            }

            $resolved = $this->resolvedTypeShort($arg, $call, $ast);

            if ($resolved === null) {
                return null;
            }

            $types[] = $resolved;
        }

        if ($this->index !== null) {
            $fqcn = $this->classFqcn($class, $ast);

            foreach ($this->index->callersOf($fqcn, $methodName) as $callSite) {
                $found = true;
                $resolved = $this->resolveDescriptorType($callSite, $i, $pname);

                if ($resolved === null) {
                    return null;
                }

                $types[] = $resolved;
            }
        }

        return $found ? $types : null;
    }

    /**
     * @param  array<Node\Arg>  $args
     */
    private function argForParam(array $args, int $i, string $name): ?Expr
    {
        foreach ($args as $arg) {
            if ($arg instanceof Node\Arg && $arg->name?->toString() === $name) {
                return $arg->value;
            }
        }

        $positional = $args[$i] ?? null;

        return $positional instanceof Node\Arg && $positional->name === null ? $positional->value : null;
    }

    /**
     * The single clean class short-name of $arg in scope, or null when it is not a
     * resolvable project class (a scalar literal, an untyped/nullable var, a call…).
     *
     * @param  array<Node>  $ast
     */
    private function resolvedTypeShort(Expr $arg, Node $context, array $ast): ?string
    {
        if ($arg instanceof Expr\Variable && is_string($arg->name)) {
            return $this->nameOfTypeNode(ReceiverTypeResolver::paramTypeNode($arg->name, $context, $ast));
        }

        if ($arg instanceof Expr\New_ && $arg->class instanceof Node\Name) {
            return $arg->class->getLast();
        }

        if ($arg instanceof Expr\PropertyFetch
            && $arg->var instanceof Expr\Variable && $arg->var->name === 'this'
            && $arg->name instanceof Node\Identifier
        ) {
            $class = ReceiverTypeResolver::enclosingClass($context, $ast);

            return $class === null ? null : $this->nameOfTypeNode(ReceiverTypeResolver::propertyTypeNode($class, $arg->name->toString()));
        }

        return null;
    }

    /** A clean single class short-name from a type node, or null (scalar / nullable / union / none). */
    private function nameOfTypeNode(?Node $type): ?string
    {
        return $type instanceof Node\Name ? $type->getLast() : null;
    }

    /**
     * Resolve the caller's argument type from a CallSite's recorded descriptors —
     * a `var` (the caller's typed parameter) or a `$this->prop` (the caller class's
     * typed property). Null when missing or unresolvable.
     */
    private function resolveDescriptorType(CallSite $callSite, int $i, string $pname): ?string
    {
        $descriptor = null;

        foreach ($callSite->argExprs as $entry) {
            if (($entry['argName'] ?? null) === $pname) {
                $descriptor = $entry;

                break;
            }
        }

        if ($descriptor === null) {
            $candidate = $callSite->argExprs[$i] ?? null;
            $descriptor = is_array($candidate) && ! isset($candidate['argName']) ? $candidate : null;
        }

        if ($descriptor === null) {
            return null;
        }

        $fileAst = $this->parseFile($callSite->callerFile);

        if ($fileAst === null) {
            return null;
        }

        if (($descriptor['kind'] ?? null) === 'var' && isset($descriptor['name'])) {
            $scope = $this->functionLikeContaining($fileAst, $callSite->startFilePos);

            if ($scope === null) {
                return null;
            }

            foreach ($scope->getParams() as $param) {
                if ($param->var instanceof Expr\Variable && $param->var->name === $descriptor['name']) {
                    return $this->nameOfTypeNode($param->type);
                }
            }

            return null;
        }

        if (($descriptor['kind'] ?? null) === 'prop' && isset($descriptor['prop'])) {
            $short = $this->shortName($callSite->callerClassFqcn);
            $classNode = $this->findClassNode($fileAst, $short);

            return $classNode === null ? null : $this->nameOfTypeNode(ReceiverTypeResolver::propertyTypeNode($classNode, $descriptor['prop']));
        }

        return null;
    }

    /**
     * @return array<Node>|null
     */
    private function parseFile(string $file): ?array
    {
        $content = @file_get_contents($file);

        return is_string($content) ? $this->parse($content) : null;
    }

    /**
     * @param  array<Node>  $ast
     */
    private function functionLikeContaining(array $ast, int $pos): ?Node\FunctionLike
    {
        $best = null;

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\FunctionLike::class) as $fn) {
            if ($pos >= (int) $fn->getStartFilePos() && $pos <= (int) $fn->getEndFilePos()) {
                if ($best === null || (int) $fn->getStartFilePos() >= (int) $best->getStartFilePos()) {
                    $best = $fn;
                }
            }
        }

        return $best;
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts) ?: $fqcn;
    }

    // ---------------------------------------------------------------------
    // V6 — BOOL-UNION
    // ---------------------------------------------------------------------

    /**
     * @param  array<Node>  $ast
     * @return list<\JesseGall\CodeCommandments\Results\Warning>
     */
    private function boolUnion(array $ast, string $content): array
    {
        $warnings = [];
        $finder = new NodeFinder;

        $functionLikes = $finder->find($ast, static fn (Node $n): bool =>
            $n instanceof Node\Stmt\ClassMethod || $n instanceof Node\Stmt\Function_);

        foreach ($functionLikes as $fn) {
            /** @var Node\Stmt\ClassMethod|Node\Stmt\Function_ $fn */
            $label = $fn->name->toString();

            if ($this->isFoundOrNotBoolUnion($fn->returnType)) {
                $warnings[] = $this->boolUnionWarning($label . '()', $fn->returnType->getStartLine(), $content);
            }

            foreach ($fn->params as $param) {
                if ($this->isFoundOrNotBoolUnion($param->type) && $param->var instanceof Expr\Variable && is_string($param->var->name)) {
                    $warnings[] = $this->boolUnionWarning($label . '() $' . $param->var->name, $param->type->getStartLine(), $content);
                }
            }
        }

        return $warnings;
    }

    private function boolUnionWarning(string $label, int $line, string $content): \JesseGall\CodeCommandments\Results\Warning
    {
        return $this->warningAt(
            $line,
            sprintf(
                '`%s` uses a `T|false` union to encode found-or-not — a `false` sentinel beside an object type. Model presence with `Option<T>` (or a Null Object) so the absence is a real type, not a `false` smuggled into the union.',
                $label,
            ),
            $this->lineSnippet($content, $line),
            'bool-union:' . $label,
            false,
        );
    }

    /**
     * Whether $type is exactly `T|false` (literal false) where T is a single class
     * type — the found-or-not sentinel. Deliberately narrow to keep it precise:
     *   - only the literal `false` type, NOT `bool` (a `bool` member is a real value —
     *     a flag, or a closure-or-bool condition — i.e. a poly-form, not absence);
     *   - exactly two members (a `Closure|string|bool` / `int|string|UnitEnum|bool`
     *     poly-form is a value union, not found-or-not);
     *   - T a real class, not `Closure` (a callable poly-form);
     *   - a scalar-or-false (`string|false`, `int|false`) is a stdlib idiom — T must
     *     be a class Name, so those never fire.
     */
    private function isFoundOrNotBoolUnion(?Node $type): bool
    {
        if (! $type instanceof Node\UnionType || count($type->types) !== 2) {
            return false;
        }

        $hasFalse = false;
        $classMember = null;

        foreach ($type->types as $member) {
            if ($member instanceof Node\Identifier && strtolower($member->toString()) === 'false') {
                $hasFalse = true;
            } elseif ($member instanceof Node\Name) {
                $classMember = $member;
            }
        }

        if (! $hasFalse || $classMember === null) {
            return false;
        }

        $short = strtolower($classMember->getLast());

        // `Closure|false` is a callable poly-form; a `Response|false` is the framework
        // render/defer contract (the handler reads `=== false`), not author-chosen
        // absence. The Symfony Response ancestry is not loadable at scan time, so a
        // short-name fallback is the justified signal here.
        return $short !== 'closure'
            && ! str_ends_with($short, 'response')
            && $short !== 'responsable';
    }

    // ---------------------------------------------------------------------
    // V3 — DTO-OR-ARRAY-SEAM
    // ---------------------------------------------------------------------

    /**
     * @param  array<Node>  $ast
     * @return list<\JesseGall\CodeCommandments\Results\Warning>
     */
    private function dtoOrArraySeam(array $ast, string $content): array
    {
        $warnings = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            foreach ($class->getMethods() as $method) {
                // Internal seams only — a public `T|array` is a deliberate flexible entry.
                if (! $method->isPrivate() && ! $method->isProtected()) {
                    continue;
                }

                $label = $method->name->toString();

                if ($this->isDataOrArraySeam($method->returnType, $ast, $class)) {
                    $warnings[] = $this->dtoSeamWarning($label . '()', $method->returnType->getStartLine(), $content);
                }

                foreach ($method->params as $param) {
                    if ($this->isDataOrArraySeam($param->type, $ast, $class) && $param->var instanceof Expr\Variable && is_string($param->var->name)) {
                        $warnings[] = $this->dtoSeamWarning($label . '() $' . $param->var->name, $param->type->getStartLine(), $content);
                    }
                }
            }
        }

        return $warnings;
    }

    private function dtoSeamWarning(string $label, int $line, string $content): \JesseGall\CodeCommandments\Results\Warning
    {
        return $this->warningAt(
            $line,
            sprintf(
                '`%s` is typed `<DataClass>|array` — it accepts a hydrated object OR its raw array form, so it must re-hydrate the array internally. Hydrate once at the boundary and take only the typed object here.',
                $label,
            ),
            $this->lineSnippet($content, $line),
            'dto-array-seam:' . $label,
            false,
        );
    }

    /**
     * Whether $type is `T|array` where T resolves to a Data/boundary class — an
     * internal re-hydration seam. `Arrayable|array`, `Collection|array`, and any
     * non-Data `T|array` do not fire (T must resolve to a Data class).
     *
     * @param  array<Node>  $ast
     */
    private function isDataOrArraySeam(?Node $type, array $ast, Node\Stmt\Class_ $enclosing): bool
    {
        if (! $type instanceof Node\UnionType) {
            return false;
        }

        $hasArray = false;
        $classMembers = [];

        foreach ($type->types as $member) {
            if ($member instanceof Node\Identifier && strtolower($member->toString()) === 'array') {
                $hasArray = true;
            } elseif ($member instanceof Node\Name) {
                $classMembers[] = $member;
            }
        }

        if (! $hasArray) {
            return false;
        }

        foreach ($classMembers as $member) {
            if ($this->isDataOrValueClass($member, $ast, $enclosing)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the class named $name resolves to a Data / boundary class (Spatie Data
     * or FormRequest subclass) — reflection → in-file AST → index.
     *
     * @param  array<Node>  $ast
     */
    private function isDataOrValueClass(Node\Name $name, array $ast, ?Node\Stmt\Class_ $enclosing): bool
    {
        $fqcn = $this->resolveFqcn($name, $ast, $enclosing);

        if ($fqcn !== null && class_exists($fqcn)) {
            foreach (self::BOUNDARY_BASES as $base) {
                if ($fqcn === $base || is_subclass_of($fqcn, $base)) {
                    return true;
                }
            }

            return false;
        }

        $node = $this->findClassNode($ast, $name->getLast());

        if ($node !== null) {
            return $this->isBoundaryDto($node, $ast);
        }

        if ($fqcn !== null && $this->index !== null) {
            $summary = $this->index->classByFqcn(ltrim($fqcn, '\\'));

            if ($summary !== null) {
                $fileContent = @file_get_contents($summary->filePath);

                if (is_string($fileContent)) {
                    $fileAst = $this->parse($fileContent);

                    if ($fileAst !== null) {
                        $classNode = $this->findClassNode($fileAst, $name->getLast());

                        if ($classNode !== null) {
                            return $this->isBoundaryDto($classNode, $fileAst);
                        }
                    }
                }
            }
        }

        return false;
    }

    // ---------------------------------------------------------------------
    // Constructor / type resolution (effective ctor, reflection → AST → index)
    // ---------------------------------------------------------------------

    /**
     * Map of paramName => {nullable, hasDefault, type} for the EFFECTIVE constructor
     * of the class named $name, or null when it cannot be resolved.
     *
     * @param  array<Node>  $ast
     * @return array<string, array{nullable: bool, hasDefault: bool, type: ?string}>|null
     */
    private function resolveConstructorParams(Node\Name $name, array $ast, ?Node\Stmt\Class_ $enclosing): ?array
    {
        $lower = strtolower($name->toString());

        if (in_array($lower, ['self', 'static'], true)) {
            return $enclosing === null ? null : $this->paramsFromClassNode($enclosing, $ast);
        }

        $node = $this->findClassNode($ast, $name->getLast());

        if ($node !== null) {
            return $this->paramsFromClassNode($node, $ast);
        }

        $fqcn = $this->resolveFqcn($name, $ast, $enclosing);

        if ($fqcn !== null && class_exists($fqcn)) {
            return $this->paramsFromReflection($fqcn);
        }

        if ($fqcn !== null && $this->index !== null) {
            $summary = $this->index->classByFqcn(ltrim($fqcn, '\\'));

            if ($summary !== null) {
                $fileContent = @file_get_contents($summary->filePath);

                if (is_string($fileContent)) {
                    $fileAst = $this->parse($fileContent);

                    if ($fileAst !== null) {
                        $classNode = $this->findClassNode($fileAst, $name->getLast());

                        if ($classNode !== null) {
                            return $this->paramsFromClassNode($classNode, $fileAst);
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  array<Node>  $ast
     * @return array<string, array{nullable: bool, hasDefault: bool, type: ?string}>
     */
    private function paramsFromClassNode(Node\Stmt\Class_ $class, array $ast): array
    {
        foreach ($class->getMethods() as $method) {
            if (strtolower($method->name->toString()) !== '__construct') {
                continue;
            }

            $map = [];

            foreach ($method->params as $param) {
                if (! $param->var instanceof Expr\Variable || ! is_string($param->var->name)) {
                    continue;
                }

                $map[$param->var->name] = [
                    'nullable' => $this->isNullableType($param->type),
                    'hasDefault' => $param->default !== null,
                    'type' => $this->simpleTypeName($param->type),
                ];
            }

            return $map;
        }

        // No own constructor — inherit the parent's effective constructor.
        if ($class->extends instanceof Node\Name) {
            return $this->resolveConstructorParams($class->extends, $ast, $class) ?? [];
        }

        return [];
    }

    /**
     * @return array<string, array{nullable: bool, hasDefault: bool, type: ?string}>
     */
    private function paramsFromReflection(string $fqcn): array
    {
        $constructor = (new ReflectionClass($fqcn))->getConstructor();

        if ($constructor === null) {
            return [];
        }

        $map = [];

        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();

            $map[$param->getName()] = [
                'nullable' => $type === null ? true : $type->allowsNull(),
                'hasDefault' => $param->isOptional(),
                'type' => $type instanceof ReflectionNamedType ? strtolower($type->getName()) : null,
            ];
        }

        return $map;
    }

    // ---------------------------------------------------------------------
    // Small AST helpers
    // ---------------------------------------------------------------------

    /** The string-literal value of an array key, resolving a `self::CONST` key. */
    private function arrayKeyName(Expr $key, ?Node\Stmt\Class_ $enclosing, array $ast): ?string
    {
        if ($key instanceof Node\Scalar\String_) {
            return $key->value;
        }

        if ($key instanceof Expr\ClassConstFetch
            && $key->class instanceof Node\Name
            && $key->name instanceof Node\Identifier
            && $enclosing !== null
            && in_array(strtolower($key->class->toString()), ['self', 'static', strtolower($enclosing->name?->toString() ?? '')], true)
        ) {
            $value = $this->classConstValue($enclosing, $key->name->toString());

            return $value instanceof Node\Scalar\String_ ? $value->value : null;
        }

        return null;
    }

    private function classConstValue(Node\Stmt\Class_ $class, string $const): ?Expr
    {
        foreach ($class->stmts as $stmt) {
            if (! $stmt instanceof Node\Stmt\ClassConst) {
                continue;
            }

            foreach ($stmt->consts as $declared) {
                if ($declared->name->toString() === $const) {
                    return $declared->value;
                }
            }
        }

        return null;
    }

    /** Whether $expr is the empty-string identity: `''`, `T_String::EMPTY`, or `T_String::empty()`. */
    private function isEmptyStringLiteral(Expr $expr): bool
    {
        if ($expr instanceof Node\Scalar\String_) {
            return $expr->value === '';
        }

        if ($expr instanceof Expr\ClassConstFetch
            && $expr->class instanceof Node\Name
            && $expr->name instanceof Node\Identifier
        ) {
            return in_array($expr->class->getLast(), self::STRING_EMPTY_WRAPPERS, true)
                && strtoupper($expr->name->toString()) === 'EMPTY';
        }

        if ($expr instanceof Expr\StaticCall
            && $expr->class instanceof Node\Name
            && $expr->name instanceof Node\Identifier
        ) {
            return in_array($expr->class->getLast(), self::STRING_EMPTY_WRAPPERS, true)
                && strtolower($expr->name->toString()) === 'empty'
                && $expr->args === [];
        }

        return false;
    }

    private function isNullableType(?Node $type): bool
    {
        if ($type instanceof Node\NullableType) {
            return true;
        }

        if ($type instanceof Node\UnionType) {
            foreach ($type->types as $member) {
                if ($member instanceof Node\Identifier && strtolower($member->toString()) === 'null') {
                    return true;
                }
            }
        }

        return $type instanceof Node\Identifier && strtolower($type->toString()) === 'null';
    }

    private function isNullDefault(?Expr $default): bool
    {
        return $default instanceof Expr\ConstFetch
            && strtolower($default->name->toString()) === 'null';
    }

    private function simpleTypeName(?Node $type): ?string
    {
        if ($type instanceof Node\Identifier) {
            return strtolower($type->toString());
        }

        return null;
    }

    /**
     * @param  array<Node>  $ast
     */
    private function findClassNode(array $ast, string $shortName): ?Node\Stmt\Class_
    {
        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if ($class->name?->toString() === $shortName) {
                return $class;
            }
        }

        return null;
    }

    /**
     * @param  array<Node>  $ast
     */
    private function resolveFqcn(Node\Name $name, array $ast, ?Node\Stmt\Class_ $enclosing): ?string
    {
        $lower = strtolower($name->toString());

        if (in_array($lower, ['self', 'static'], true)) {
            $short = $enclosing?->name?->toString();

            if ($short === null) {
                return null;
            }

            $namespace = FileImports::namespace($ast);

            return $namespace === null ? $short : $namespace . '\\' . $short;
        }

        if ($name->isFullyQualified()) {
            return ltrim($name->toString(), '\\');
        }

        $imports = FileImports::of($ast);
        $parts = explode('\\', $name->toString());
        $first = $parts[0];

        if (isset($imports[$first])) {
            $parts[0] = $imports[$first];

            return implode('\\', $parts);
        }

        $namespace = FileImports::namespace($ast);

        return $namespace === null ? $name->toString() : $namespace . '\\' . $name->toString();
    }

}
