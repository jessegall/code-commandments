<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\ParameterizedRepenter;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;
use JesseGall\CodeCommandments\Results\RepentInput;
use JesseGall\CodeCommandments\Results\Tier;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * Suggest an enum for a `string`-typed field whose NAME reads as a closed set —
 * `direction`, `status`, `kind`, `mode`, `type`, … — even when no literal and
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
 */
#[IntroducedIn('1.91.0')]
class PreferEnumForClosedSetFieldProphet extends PhpCommandment implements ParameterizedRepenter
{
    /**
     * Field name endings that almost always denote a finite, closed set.
     * Matched case-insensitively at a word boundary (camelCase or snake_case),
     * so `sortDirection` and `node_type` match but `prototype` does not.
     *
     * @var list<string>
     */
    private const DEFAULT_NAMES = [
        'direction', 'status', 'state', 'kind', 'mode', 'type', 'level',
        'severity', 'visibility', 'role', 'format', 'strategy', 'operator',
        'phase', 'stage', 'category', 'variant', 'priority', 'alignment',
        'orientation', 'unit', 'period', 'scope', 'tier',
    ];

    /** @var array<string, string> */
    private array $repentInput = [];

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
                . '(`$direction`, `$status`, `$kind`, `$mode`, `$type`, …) — a value '
                . 'with a known, finite set of cases that is currently stringly-typed.'
            )
            ->leaveWhen(
                'The value is genuinely open free text that merely shares the name '
                . '(a `$type` holding an arbitrary MIME string, a `$format` holding a '
                . 'user-supplied pattern).'
            )
            ->whenUnsure(
                'Ask "is the set of valid values finite and known?" If yes, it is an '
                . 'enum — create a purpose-specific one and type the field as it. If '
                . 'the value is genuinely open, leave it.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A closed-set value — a direction, status, kind, mode, type — belongs in an
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

[AUTO-FIXABLE, needs input] — `repent` can create the enum and retype the
field, but it cannot guess the class name or the cases, so it asks for them:

    repent --prophet=PreferEnumForClosedSetField --file=path/to/Data.php \
        --input create-enum-class=SocketDirection \
        --input cases=input,output

It generates `enum SocketDirection: string { case Input = 'input'; … }` in the
file's namespace and retypes the field to it. (Use `--input field=<name>` to
pick which field when a file has more than one.)

WHAT FIRES — a `string` / `?string` typed property or promoted ctor property
whose name matches the configured closed-set list at a word boundary.

WHAT DOES NOT — non-string types, a plain method parameter, a name not in the
list, or — when the list is configured empty — nothing.

Configure via:

    Backend\PreferEnumForClosedSetFieldProphet::class => [
        'names' => ['direction', 'status', 'kind', 'mode', 'type', /* … */],
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

            $warnings[] = $this->warningAt(
                $field['line'],
                sprintf(
                    'The string field `$%s` reads like a closed set (a %s). Stringly-typed closed sets bypass static analysis, IDE refactors, and exhaustive `match`. If its values are a known finite set, create a purpose-specific enum (e.g. `enum %s: string { case … }`) and type `$%s` as it — `repent --input create-enum-class=%s --input cases=…` does it. If it is genuinely open free text, leave it.',
                    $field['name'],
                    $field['noun'],
                    $enum,
                    $field['name'],
                    $enum,
                ),
                "closed-set-field:{$field['name']}",
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
            RepentInput::required('create-enum-class', 'Name of the enum class to create', 'SocketDirection'),
            RepentInput::required('cases', 'Comma-separated case values for the enum', 'input,output'),
            RepentInput::optional('field', 'Which field to convert (required when the file has more than one)', 'direction'),
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

        $className = trim($this->repentInput['create-enum-class'] ?? '');
        $casesRaw = trim($this->repentInput['cases'] ?? '');

        if ($className === '' || $casesRaw === '') {
            return RepentanceResult::unrepentant('Needs --input create-enum-class=<Class> and --input cases=<a,b,…>');
        }

        $target = $this->pickField($fields);

        if ($target === null) {
            $names = implode(', ', array_map(static fn (array $f): string => '$' . $f['name'], $fields));

            return RepentanceResult::unrepentant("More than one closed-set field here ({$names}) — pick one with --input field=<name>");
        }

        $short = $this->shortClassName($className);
        $cases = $this->parseCases($casesRaw);

        if ($cases === []) {
            return RepentanceResult::unrepentant('No cases parsed from --input cases');
        }

        $namespace = $this->namespaceOf($ast);
        $enumContent = $this->renderEnum($namespace, $short, $cases);
        $enumPath = dirname($filePath) . '/' . $short . '.php';

        // Retype the field: `string`/`?string` → `Short`/`?Short`.
        $typeNode = $target['typeNode'];
        $newType = ($target['nullable'] ? '?' : '') . $short;
        $start = $typeNode->getStartFilePos();
        $end = $typeNode->getEndFilePos();
        $content = substr($content, 0, $start) . $newType . substr($content, $end + 1);

        return RepentanceResult::absolved(
            $content,
            ["Created enum {$short} ({$enumPath}) and retyped \${$target['name']}"],
            createdFiles: [$enumPath => $enumContent],
        );
    }

    /**
     * The `string`-typed, closed-set-named data fields in the file.
     *
     * @param  array<Node>  $ast
     * @return list<array{name: string, line: int, noun: string, typeNode: Node, nullable: bool}>
     */
    private function collectFields(array $ast): array
    {
        $names = $this->closedSetNames();

        if ($names === []) {
            return [];
        }

        $finder = new NodeFinder;
        $fields = [];

        // Promoted constructor properties (declared data fields).
        foreach ($finder->findInstanceOf($ast, Node\Param::class) as $param) {
            if ($param->flags === 0 || ! $param->var instanceof Node\Expr\Variable || ! is_string($param->var->name)) {
                continue;
            }

            $this->addField($fields, $param->type, $param->var->name, $param->getStartLine(), $names);
        }

        // Class properties.
        foreach ($finder->findInstanceOf($ast, Node\Stmt\Property::class) as $property) {
            foreach ($property->props as $prop) {
                $this->addField($fields, $property->type, $prop->name->toString(), $prop->getStartLine(), $names);
            }
        }

        return $fields;
    }

    /**
     * @param  list<array{name: string, line: int, noun: string, typeNode: Node, nullable: bool}>  $fields
     */
    private function addField(array &$fields, ?Node $type, string $name, int $line, array $names): void
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
        ];
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
            static fn (string $n): string => strtolower($n),
            array_filter($configured, 'is_string'),
        ));
    }
}
