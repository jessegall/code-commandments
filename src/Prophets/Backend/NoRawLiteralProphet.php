<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;
use JesseGall\CodeCommandments\Support\Pipes\Php\FindRawLiterals;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * Flag raw magic literals — empties (`''`, `'{}'`, `'[]'`, `[]`, `[[]]`),
 * invisible whitespace (`"\n"`, `"\n\n"`, `"\t"`, `"\r"`, `"\r\n"`, `"\0"`),
 * opt-in separators / spaces, and opt-in sentinel ints (`0`, `1`, `-1`) — plus
 * the empty checks (`=== ''` / `strlen()` / `trim()`), and rewrite them to the
 * named type helpers from jessegall/php-types (`T_String`, `T_Json`, `T_Array`,
 * `T_Int`).
 */
#[IntroducedIn('1.28.0')]
class NoRawLiteralProphet extends PhpCommandment implements SinRepenter
{
    private const STRING_CLASS = 'JesseGall\\PhpTypes\\T_String';

    private const JSON_CLASS = 'JesseGall\\PhpTypes\\T_Json';

    private const ARRAY_CLASS = 'JesseGall\\PhpTypes\\T_Array';

    private const INT_CLASS = 'JesseGall\\PhpTypes\\T_Int';

    private const FLOAT_CLASS = 'JesseGall\\PhpTypes\\T_Float';

    private const BOOL_CLASS = 'JesseGall\\PhpTypes\\T_Bool';

    /**
     * Literal kinds that map to a plain `Class::CONST` (valid in every
     * position, so no const/value juggling). kind => constant name.
     */
    private const CONSTANT_KINDS = [
        'newline' => 'NEWLINE',
        'paragraph' => 'PARAGRAPH',
        'tab' => 'TAB',
        'carriage_return' => 'CARRIAGE_RETURN',
        'crlf' => 'CRLF',
        'null_byte' => 'NULL_BYTE',
        'space' => 'SPACE',
        'comma' => 'COMMA',
        'comma_space' => 'COMMA_SPACE',
        'slash' => 'SLASH',
        'dot' => 'DOT',
        'dash' => 'DASH',
        'int_zero' => 'ZERO',
        'int_one' => 'ONE',
        'int_minus_one' => 'MINUS_ONE',
        'float_zero' => 'ZERO',
    ];

    /**
     * Rewriting `$x ?? T_String::empty()` to `T_String::coalesce($x)` removes
     * the very fallback expression RepeatedFallback would flag — so resolve
     * the raw literal first; its symptoms there are deferred until then.
     *
     * @return list<class-string>
     */
    public function supersedes(): array
    {
        return [RepeatedFallbackProphet::class];
    }

    public function description(): string
    {
        return 'Do not write raw magic literals (empties, newlines, …) — name them with T_String / T_Json / T_Array / T_Int';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A raw `''` carries an unstated intent and gets re-checked defensively
all over a codebase (`if ($this->delimiter === '')`). The literal is the
symptom; the missing name is the disease. Give the empty value a name
and the check a single home — the helpers in jessegall/php-types do
exactly that.

LITERALS — name the empty value:

    ''            ->  T_String::empty()         // value position
    ''            ->  T_String::EMPTY           // constant position (param
                                                // defaults, const, etc.)
    '{}'          ->  T_Json::emptyObject()
    '[]'          ->  T_Json::emptyArray()
    []            ->  T_Array::empty()           // opt-in (flag_empty_array)
    [[]]          ->  T_Array::matrix()          // nested array seeded with one
                                                 // empty inner array

INVISIBLE WHITESPACE — escape sequences you can't see and easily miscount
(`"\n"` vs `"\n "` vs `"\n\n"`). On by default — these map to constants,
so they read the same in every position:

    "\n"          ->  T_String::NEWLINE
    "\n\n"        ->  T_String::PARAGRAPH
    "\t"          ->  T_String::TAB
    "\r" "\r\n"   ->  T_String::CARRIAGE_RETURN / T_String::CRLF
    "\0"          ->  T_String::NULL_BYTE

OPT-IN CATEGORIES — off by default, enable per project:

    ' '           ->  T_String::SPACE          // flag_space
    ',' ', ' '/'  ->  T_String::COMMA / COMMA_SPACE / SLASH …   // flag_separators
    0  1  -1      ->  T_Int::ZERO / ONE / MINUS_ONE             // flag_sentinel_ints
    0.0           ->  T_Float::ZERO                             // flag_sentinel_floats

CHECKS — name the predicate:

    $x === ''             ->  T_String::isEmpty($x)
    $x !== ''             ->  T_String::isNotEmpty($x)
    strlen($x) === 0      ->  T_String::isEmpty($x)
    strlen($x) > 0        ->  T_String::isNotEmpty($x)
    trim($x) === ''       ->  T_String::isBlank($x)        // "empty or
                                                          // whitespace" — the
                                                          // decision, named
    $json === '{}'        ->  T_Json::isEmptyObject($json)
    $json !== '[]'        ->  ! T_Json::isEmptyArray($json)

The predicate reads as intent and gives the comparison one canonical
form, so no two sites can drift (`=== ''` here, `trim(...) === ''`
there). As a first-class callable it replaces ad-hoc filter closures:

    array_filter($parts, fn ($p) => $p !== '')   // raw
    array_filter($parts, T_String::isNotEmpty(...))

THE TRIM TRAP: `trim($x) === ''` bakes in "whitespace counts as empty".
`T_String::isBlank($x)` names that decision in the open. Better still,
store a trimmed value object so the check is unnecessary downstream.

THE CEILING — parse, don't validate. When the same emptiness invariant
guards a value across methods, the literal/predicate is still a floor.
The ceiling is a value object that rejects empty at construction, so the
type carries the proof and the check disappears everywhere downstream:

    final class Delimiter
    {
        private function __construct(public readonly string $value) {}

        public static function from(string $value): self
        {
            if (T_String::isEmpty($value)) {
                throw EmptyDelimiterException::make();
            }

            return new self($value);
        }
    }

WHAT STAYS RIGHTEOUS:

  - The type-helper classes themselves (`T_String`, `T_Json`, ...) — they
    hold the one true literal and are never flagged.
  - `blank()` / `filled()` are not flagged — they contain no literal and
    carry broader semantics. Reach for them or the helpers as you prefer.
  - `T_Json` predicates are semantic: `isEmptyObject('{ }')` is true.

This prophet is [AUTO-FIXABLE]: `repent` rewrites every literal and check
above and adds the `use` imports. Run it deliberately — review the diff.

Claude (and any other AI agent): never type a bare `''`, `'{}'`, or
`'[]'`, and never compare against them with `===` / `strlen` / `trim`.
Reach for T_String / T_Json (and T_Array when empty arrays are flagged),
or lift the value into a type that cannot be empty.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $pipe = (new FindRawLiterals)->withOptions($this->optionsFromConfig());

        return PhpPipeline::make($filePath, $content)
            ->pipe(ParsePhpAst::class)
            ->pipe($pipe)
            ->sinsFromMatches(
                fn ($match) => $this->messageFor($match->groups),
                fn ($match) => $this->suggestionFor($match->groups),
            )
            ->judge();
    }

    public function canRepent(string $filePath): bool
    {
        return pathinfo($filePath, PATHINFO_EXTENSION) === 'php';
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

        $findings = FindRawLiterals::analyze($ast, $content, $this->optionsFromConfig());
        $findings = array_values(array_filter($findings, fn ($f) => $f['fixable']));

        if ($findings === []) {
            return RepentanceResult::unchanged();
        }

        $imported = $this->existingImports($ast);
        $needed = [];
        $edits = [];
        $penance = [];

        foreach ($findings as $finding) {
            $fqcn = in_array($finding['kind'], ['helper_compare', 'coalesce'], true)
                ? $this->fqcnForHelper($finding['helper_class'])
                : $this->classFor($finding['kind']);
            $short = $imported[$fqcn] ?? $this->shortName($fqcn);

            if (! isset($imported[$fqcn])) {
                $needed[$fqcn] = true;
            }

            $replacement = $this->replacementFor($finding, $short);

            $edits[] = ['start' => $finding['start'], 'end' => $finding['end'], 'text' => $replacement];
            $penance[] = "Replaced `{$finding['literal']}` with `{$replacement}`";
        }

        $insert = $this->importInsertion($ast, $content, array_keys($needed), $imported);

        if ($insert !== null) {
            $edits[] = $insert;
        }

        usort($edits, fn ($a, $b) => $b['start'] <=> $a['start']);

        foreach ($edits as $edit) {
            $content = substr($content, 0, $edit['start']) . $edit['text'] . substr($content, $edit['end'] + 1);
        }

        return RepentanceResult::absolved($content, $penance);
    }

    private function classFor(string $kind): string
    {
        return match (true) {
            str_starts_with($kind, 'json') => (string) $this->config('json_class', self::JSON_CLASS),
            str_starts_with($kind, 'int_') => (string) $this->config('int_class', self::INT_CLASS),
            str_starts_with($kind, 'float_') => (string) $this->config('float_class', self::FLOAT_CLASS),
            $kind === 'array_literal', $kind === 'matrix_literal' => (string) $this->config('array_class', self::ARRAY_CLASS),
            default => (string) $this->config('string_class', self::STRING_CLASS),
        };
    }

    /**
     * Resolve a helper short name (`T_Array`, `T_Int`, …) to its configured FQCN.
     */
    private function fqcnForHelper(string $short): string
    {
        return match ($short) {
            'T_Array' => (string) $this->config('array_class', self::ARRAY_CLASS),
            'T_Json' => (string) $this->config('json_class', self::JSON_CLASS),
            'T_Int' => (string) $this->config('int_class', self::INT_CLASS),
            'T_Float' => (string) $this->config('float_class', self::FLOAT_CLASS),
            'T_Bool' => (string) $this->config('bool_class', self::BOOL_CLASS),
            default => (string) $this->config('string_class', self::STRING_CLASS),
        };
    }

    /**
     * @return array{empty_array: bool, whitespace: bool, space: bool, separators: bool, sentinel_ints: bool}
     */
    private function optionsFromConfig(): array
    {
        return [
            'empty_array' => (bool) $this->config('flag_empty_array', false),
            'whitespace' => (bool) $this->config('flag_whitespace', true),
            'space' => (bool) $this->config('flag_space', false),
            'separators' => (bool) $this->config('flag_separators', false),
            'sentinel_ints' => (bool) $this->config('flag_sentinel_ints', false),
            'sentinel_floats' => (bool) $this->config('flag_sentinel_floats', false),
        ];
    }

    /**
     * `Class::CONST` for a constant-mapped literal kind, using configured class.
     */
    private function constantReplacementFor(string $kind): string
    {
        $class = match (true) {
            str_starts_with($kind, 'int_') => (string) $this->config('int_class', self::INT_CLASS),
            str_starts_with($kind, 'float_') => (string) $this->config('float_class', self::FLOAT_CLASS),
            default => (string) $this->config('string_class', self::STRING_CLASS),
        };

        return $this->shortName($class) . '::' . self::CONSTANT_KINDS[$kind];
    }

    /**
     * @param  array{kind: string, position: string, predicate: string, negate: bool, var: string}  $finding
     */
    private function replacementFor(array $finding, string $short): string
    {
        if (isset(self::CONSTANT_KINDS[$finding['kind']])) {
            return $short . '::' . self::CONSTANT_KINDS[$finding['kind']];
        }

        $const = $finding['position'] === 'const';

        return match ($finding['kind']) {
            'string_literal' => $const ? "{$short}::EMPTY" : "{$short}::empty()",
            'json_object_literal' => $const ? "{$short}::EMPTY_OBJECT" : "{$short}::emptyObject()",
            'json_array_literal' => $const ? "{$short}::EMPTY_ARRAY" : "{$short}::emptyArray()",
            'array_literal' => $const ? "{$short}::EMPTY" : "{$short}::empty()",
            'matrix_literal' => $const ? "{$short}::MATRIX" : "{$short}::matrix()",
            'json_object_compare', 'json_array_compare', 'helper_compare' => ($finding['negate'] ? '! ' : '')
                . "{$short}::{$finding['predicate']}({$finding['var']})",
            'coalesce' => "{$short}::coalesce({$finding['var']})",
            default => "{$short}::{$finding['predicate']}({$finding['var']})",
        };
    }

    /**
     * @param  array<string, string>  $groups
     */
    private function messageFor(array $groups): string
    {
        if (isset(self::CONSTANT_KINDS[$groups['kind']])) {
            return "Raw literal `{$groups['literal']}` — name it with " . $this->constantReplacementFor($groups['kind']);
        }

        return match ($groups['kind']) {
            'string_literal' => "Raw empty string literal `{$groups['literal']}` — give it a name with T_String",
            'json_object_literal' => "Raw empty JSON object literal `{$groups['literal']}` — use T_Json",
            'json_array_literal' => "Raw empty JSON array literal `{$groups['literal']}` — use T_Json",
            'array_literal' => 'Raw empty array literal `[]` — use T_Array',
            'matrix_literal' => "Raw nested-array literal `{$groups['literal']}` — use T_Array::MATRIX",
            'strlen_compare' => "Empty-string check via strlen() on {$groups['var']} — use a named predicate",
            'trim_compare' => "Blank check via trim() on {$groups['var']} — use a named predicate",
            'json_object_compare', 'json_array_compare' => "Raw empty-JSON comparison against `{$groups['literal']}` — use a T_Json predicate",
            'helper_compare' => "Comparison against `{$groups['literal']}` — use the predicate {$groups['helper_class']}::{$groups['predicate']}({$groups['var']})",
            'coalesce' => "Null-coalesce to a {$groups['helper_class']} empty (`{$groups['literal']}`) — use {$groups['helper_class']}::coalesce({$groups['var']})",
            default => "Raw empty-string comparison on {$groups['var']} — use a named predicate",
        };
    }

    /**
     * @param  array<string, string>  $groups
     */
    private function suggestionFor(array $groups): string
    {
        if (isset(self::CONSTANT_KINDS[$groups['kind']])) {
            return 'Replace with ' . $this->constantReplacementFor($groups['kind']) . '.';
        }

        $negate = $groups['negate'] === '1' ? '! ' : '';

        return match ($groups['kind']) {
            'string_literal' => $groups['position'] === 'const'
                ? 'Replace with T_String::EMPTY (constant position — a method call is illegal here).'
                : 'Replace with T_String::empty().',
            'json_object_literal' => $groups['position'] === 'const'
                ? 'Replace with T_Json::EMPTY_OBJECT.'
                : 'Replace with T_Json::emptyObject().',
            'json_array_literal' => $groups['position'] === 'const'
                ? 'Replace with T_Json::EMPTY_ARRAY.'
                : 'Replace with T_Json::emptyArray().',
            'array_literal' => $groups['position'] === 'const'
                ? 'Replace with T_Array::EMPTY.'
                : 'Replace with T_Array::empty().',
            'matrix_literal' => $groups['position'] === 'const'
                ? 'Replace with T_Array::MATRIX.'
                : 'Replace with T_Array::matrix().',
            'trim_compare' => "Replace with T_String::{$groups['predicate']}({$groups['var']}) — isBlank() is the named home for 'empty or whitespace'. Better still, store a trimmed value object so the check is unnecessary.",
            'json_object_compare', 'json_array_compare' => "Replace with {$negate}T_Json::{$groups['predicate']}({$groups['var']}).",
            'helper_compare' => "Replace with {$negate}{$groups['helper_class']}::{$groups['predicate']}({$groups['var']}).",
            'coalesce' => "Replace with {$groups['helper_class']}::coalesce({$groups['var']}).",
            default => "Replace with T_String::{$groups['predicate']}({$groups['var']}).",
        };
    }

    private function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }

    /**
     * @param  array<Node>  $ast
     * @return array<string, string> FQCN => alias
     */
    private function existingImports(array $ast): array
    {
        $imports = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Use_::class) as $use) {
            foreach ($use->uses as $useUse) {
                $fqcn = $useUse->name->toString();
                $imports[$fqcn] = $useUse->alias?->toString() ?? $useUse->name->getLast();
            }
        }

        return $imports;
    }

    /**
     * Compute a zero-width edit that inserts the needed `use` statements.
     *
     * @param  array<Node>  $ast
     * @param  list<string>  $fqcns
     * @param  array<string, string>  $imported
     * @return array{start: int, end: int, text: string}|null
     */
    private function importInsertion(array $ast, string $content, array $fqcns, array $imported): ?array
    {
        if ($fqcns === []) {
            return null;
        }

        sort($fqcns);
        $lines = '';

        foreach ($fqcns as $fqcn) {
            $lines .= "\nuse {$fqcn};";
        }

        $nodeFinder = new NodeFinder;
        $uses = $nodeFinder->findInstanceOf($ast, Node\Stmt\Use_::class);

        if ($uses !== []) {
            $pos = max(array_map(static fn (Node $u) => (int) $u->getEndFilePos(), $uses)) + 1;

            return ['start' => $pos, 'end' => $pos - 1, 'text' => $lines];
        }

        // No imports yet — insert after the namespace declaration, else after
        // a declare(), else after the opening tag. Prefix a blank line.
        $namespaces = $nodeFinder->findInstanceOf($ast, Node\Stmt\Namespace_::class);

        if ($namespaces !== [] && $namespaces[0]->name !== null) {
            $semicolon = strpos($content, ';', (int) $namespaces[0]->getStartFilePos());

            if ($semicolon !== false) {
                $pos = $semicolon + 1;

                return ['start' => $pos, 'end' => $pos - 1, 'text' => "\n{$lines}"];
            }
        }

        $declares = $nodeFinder->findInstanceOf($ast, Node\Stmt\Declare_::class);

        if ($declares !== []) {
            $pos = (int) $declares[0]->getEndFilePos() + 1;

            return ['start' => $pos, 'end' => $pos - 1, 'text' => "\n{$lines}"];
        }

        $open = strpos($content, '<?php');

        if ($open !== false) {
            $pos = $open + 5;

            return ['start' => $pos, 'end' => $pos - 1, 'text' => "\n{$lines}"];
        }

        return null;
    }
}
