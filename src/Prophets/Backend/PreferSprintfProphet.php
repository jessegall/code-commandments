<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;
use JesseGall\CodeCommandments\Support\Pipes\Php\FindStringInterpolations;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * Prefer `sprintf()` over double-quoted string interpolation, separating the
 * template from its values and (by default) pulling invisible escape runs into
 * named T_String constants. Opinionated and opt-in — flags interpolated strings
 * that also contain escape sequences by default.
 *
 *
 *
 * @method-generated-start
 * @method static extractWhitespace(bool $on = true)
 * @method static minInterpolations(int $value)
 * @method static requireEscape(bool $on = true)
 * @method static stringClass(string $value)
 * @method-generated-end
 */
#[IntroducedIn('1.29.0')]
class PreferSprintfProphet extends PhpCommandment implements SinRepenter
{
    private const STRING_CLASS = 'JesseGall\\PhpTypes\\T_String';

    public function description(): string
    {
        return 'Prefer sprintf() over string interpolation — separate the template from its values';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
String interpolation hides two things: the values inside the prose, and
invisible whitespace inside the escapes. `sprintf()` pulls both into the
open — the format string becomes a clean, extractable template (handy for
i18n later) and every value lines up as an explicit argument.

Sinful:
    "I tried to fix it but the proposal still has problems:\n\n{$bulleted}"

Righteous:
    sprintf(
        'I tried to fix it but the proposal still has problems:%s%s',
        T_String::PARAGRAPH,
        $bulleted,
    )

Each interpolation becomes a `%s`; each escape run (`\n\n`, `\t`, …)
becomes a `%s` + a named T_String constant, so the format string ends up
single-quoted with nothing invisible left in it. Literal `%` is escaped
to `%%`.

THIS IS OPINIONATED. By default it only flags interpolated strings that
ALSO contain an escape sequence (`\n`, `\t`, `\r`, `\0`) — the sweet spot
where naming the whitespace pays off. `"Hello $name"` is left alone.
Config widens or narrows it:

    'require_escape'      => true,   // only flag when an escape is present
    'extract_whitespace'  => true,   // \n\n -> T_String::PARAGRAPH arg
    'min_interpolations'  => 1,      // minimum interpolated expressions

Heredocs / nowdocs are intentional multi-line templates and are left
alone. [AUTO-FIXABLE] — `repent` does the rewrite and adds the import.

Claude (and any other AI agent): when a double-quoted string mixes prose,
`{$values}`, and `\n` escapes, reach for sprintf with `%s` placeholders
and T_String constants instead of interpolating.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $pipe = (new FindStringInterpolations)->withOptions($this->optionsFromConfig());

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

        $findings = FindStringInterpolations::analyze($ast, $content, $this->optionsFromConfig());

        if ($findings === []) {
            return RepentanceResult::unchanged();
        }

        $imported = $this->existingImports($ast);
        $needed = [];
        $edits = [];
        $penance = [];

        foreach ($findings as $finding) {
            $edits[] = ['start' => $finding['start'], 'end' => $finding['end'], 'text' => $finding['replacement']];
            $penance[] = 'Rewrote interpolation as sprintf()';

            if ($finding['needs_import'] && ! isset($imported[$finding['string_class']])) {
                $needed[$finding['string_class']] = true;
            }
        }

        $insert = $this->importInsertion($ast, $content, array_keys($needed));

        if ($insert !== null) {
            $edits[] = $insert;
        }

        usort($edits, fn ($a, $b) => $b['start'] <=> $a['start']);

        foreach ($edits as $edit) {
            $content = substr($content, 0, $edit['start']) . $edit['text'] . substr($content, $edit['end'] + 1);
        }

        return RepentanceResult::absolved($content, $penance);
    }

    /**
     * @return array{require_escape: bool, extract_whitespace: bool, min_interpolations: int, string_class: string}
     */
    private function optionsFromConfig(): array
    {
        return [
            'require_escape' => (bool) $this->config('require_escape', true),
            'extract_whitespace' => (bool) $this->config('extract_whitespace', true),
            'min_interpolations' => (int) $this->config('min_interpolations', 1),
            'string_class' => (string) $this->config('string_class', self::STRING_CLASS),
        ];
    }

    /**
     * @param  array<string, string>  $groups
     */
    private function messageFor(array $groups): string
    {
        return "String interpolation with {$groups['args']} dynamic part(s) — prefer sprintf() so the template and its values are separate";
    }

    /**
     * @param  array<string, string>  $groups
     */
    private function suggestionFor(array $groups): string
    {
        return "Rewrite as:\n{$groups['sprintf']}";
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
                $imports[$useUse->name->toString()] = $useUse->alias?->toString() ?? $useUse->name->getLast();
            }
        }

        return $imports;
    }

    /**
     * @param  array<Node>  $ast
     * @param  list<string>  $fqcns
     * @return array{start: int, end: int, text: string}|null
     */
    private function importInsertion(array $ast, string $content, array $fqcns): ?array
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

        $namespaces = $nodeFinder->findInstanceOf($ast, Node\Stmt\Namespace_::class);

        if ($namespaces !== [] && $namespaces[0]->name !== null) {
            $semicolon = strpos($content, ';', (int) $namespaces[0]->getStartFilePos());

            if ($semicolon !== false) {
                $pos = $semicolon + 1;

                return ['start' => $pos, 'end' => $pos - 1, 'text' => "\n{$lines}"];
            }
        }

        $open = strpos($content, '<?php');

        if ($open !== false) {
            $pos = $open + 5;

            return ['start' => $pos, 'end' => $pos - 1, 'text' => "\n{$lines}"];
        }

        return null;
    }
}
